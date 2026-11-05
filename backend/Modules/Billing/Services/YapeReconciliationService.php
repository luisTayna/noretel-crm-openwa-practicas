<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;
use Modules\Billing\Entities\YapeReceiverEvent;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Modules\Operations\Entities\OnboardingOrder;

class YapeReconciliationService
{
    public const DNI_RESOLUTION_RESOLVED = 'resolved';
    public const DNI_RESOLUTION_INVALID_DNI = 'invalid_dni';
    public const DNI_RESOLUTION_EVIDENCE_NOT_FOUND = 'evidence_not_found';
    public const DNI_RESOLUTION_EVIDENCE_INCOMPLETE = 'evidence_incomplete';
    public const DNI_RESOLUTION_CUSTOMER_NOT_FOUND = 'customer_not_found';
    public const DNI_RESOLUTION_CUSTOMER_NOT_FOUND_MANUAL_REVIEW = 'customer_not_found_manual_review';
    public const DNI_RESOLUTION_CUSTOMER_MISMATCH = 'customer_mismatch';
    public const DNI_RESOLUTION_NO_PENDING_PAYMENT = 'no_pending_payment';
    public const DNI_RESOLUTION_MULTIPLE_PENDING_EVIDENCES = 'multiple_pending_evidences';
    public const DNI_RESOLUTION_SERVICE_SELECTION_REQUIRED = 'service_selection_required';
    public const DNI_RESOLUTION_SERVICE_SELECTION_INVALID = 'service_selection_invalid';
    public const DNI_RESOLUTION_AMOUNT_MISMATCH = 'amount_mismatch';
    public const DNI_RESOLUTION_AMBIGUOUS_PAYMENT = 'ambiguous_payment';
    public const DNI_RESOLUTION_MAX_ATTEMPTS = 3;
    protected const DUPLICATE_CLIENT_EVIDENCE_WINDOW_HOURS = 24;
    protected bool $deferAutomaticPaymentCompletion = false;
    protected array $resumeReceiverEventIds = [];

    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    public function createIntent(array $data, ?Invoice $invoice = null, ?Contract $contract = null): YapePaymentIntent
    {
        $contract ??= $invoice?->contract;
        $customer = $contract?->customer;
        $amount = round((float) $data['amount'], 2);
        $phone = $this->normalizePhone((string) ($customer?->phone ?? ''));
        $evidence = !empty($data['client_evidence_id'])
            ? YapeClientEvidence::find((int) $data['client_evidence_id'])
            : null;
        $operationCode = $this->normalizeOperationCode($data['operation_code'] ?? $evidence?->operation_code);
        $securityCode = $this->normalizeSecurityCode($data['security_code'] ?? $evidence?->security_code);

        $attributes = [
            'invoice_id' => $data['invoice_id'] ?? $invoice?->id,
            'contract_id' => $data['contract_id'] ?? $contract?->id,
            'customer_id' => $customer?->id,
            'client_evidence_id' => $evidence?->id,
            'expected_amount' => $amount,
            'payment_mode' => $data['payment_mode'] ?? 'single',
            'months_to_pay' => $data['months_to_pay'] ?? null,
            'whatsapp_phone' => $phone,
            'customer_name' => $customer?->full_name,
            'status' => YapePaymentIntent::STATUS_WAITING_CLIENT,
            'operation_code' => $operationCode,
            'security_code' => $securityCode,
            'receiver_account_name' => config('billing.yape.receiver_name'),
            'receiver_account_phone' => $this->normalizePhone((string) config('billing.yape.receiver_phone')),
            'source_payload' => [
                'invoice_id' => $data['invoice_id'] ?? $invoice?->id,
                'contract_id' => $data['contract_id'] ?? $contract?->id,
                'client_evidence_id' => $evidence?->id,
                'source_message_id' => $data['source_message_id'] ?? $evidence?->source_message_id,
                'source_chat_name' => $data['source_chat_name'] ?? $evidence?->source_chat_name,
                'operation_code' => $operationCode,
                'security_code' => $securityCode,
                'amount' => $amount,
                'payment_mode' => $data['payment_mode'] ?? 'single',
                'months_to_pay' => $data['months_to_pay'] ?? null,
                'applied_invoice_ids' => $data['applied_invoice_ids'] ?? null,
                'applied_invoices' => $data['applied_invoices'] ?? null,
                'selection_strategy' => $data['selection_strategy'] ?? null,
                'original_yape_amount' => $data['original_yape_amount'] ?? $amount,
                'applied_amount' => $data['applied_amount'] ?? null,
                'credit_amount' => $data['credit_amount'] ?? null,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => 'yape',
                'notes' => $data['notes'] ?? null,
                'received_amount' => null,
                'change_amount' => 0,
                'user_id' => $data['user_id'] ?? auth()->id(),
            ],
            'expires_at' => now()->addMinutes((int) config('billing.yape.intent_expiration_minutes', 1440)),
            'created_by' => $data['user_id'] ?? auth()->id(),
        ];

        $this->appendYapeDirectDebug('yape_intent_lookup_start', [
            'evidence_id' => $evidence?->id,
            'chat_id' => $data['openwa_chat_id'] ?? $evidence?->source_chat_name,
            'source_message_id' => $data['source_message_id'] ?? $evidence?->source_message_id,
            'operation_code' => $operationCode,
            'security_code' => $securityCode,
            'amount' => $amount,
            'customer_id' => $attributes['customer_id'],
            'invoice_id' => $attributes['invoice_id'],
            'contract_id' => $attributes['contract_id'],
        ]);

        if ($existing = $this->findReusableIntentForCreate($attributes, $evidence)) {
            $this->appendYapeDirectDebug('yape_existing_intent_reused', [
                'evidence_id' => $evidence?->id,
                'intent_id' => $existing->id,
                'intent_number' => $existing->intent_number,
                'status' => $existing->status,
                'reason' => $existing->payment_id ? 'payment_already_registered_or_intent_completed' : 'compatible_existing_intent',
                'matched_by' => $existing->payment_id ? 'existing_payment' : 'compatible_lookup',
                'customer_id' => $existing->customer_id,
                'invoice_id' => $existing->invoice_id,
                'operation_code' => $existing->operation_code,
            ]);

            if ($evidence && !$evidence->yape_payment_intent_id && !$existing->client_evidence_id) {
                $this->attachClientEvidenceToIntent($evidence, $existing);

                return $this->maybeReconcile($this->attachExistingEvidence($existing->fresh()));
            }

            return $this->maybeReconcile($this->attachExistingEvidence($existing->fresh()));
        }

        $intent = $this->createIntentWithRetry($attributes, $evidence);

        return $this->maybeReconcile($this->attachExistingEvidence($intent->fresh()));
    }

    protected function findReusableIntentForCreate(array $attributes, ?YapeClientEvidence $evidence = null): ?YapePaymentIntent
    {
        $amount = round((float) ($attributes['expected_amount'] ?? 0), 2);
        $paymentMode = $attributes['payment_mode'] ?? 'single';
        $operationCode = $this->normalizeOperationCode($attributes['operation_code'] ?? $evidence?->operation_code);
        $securityCode = $this->normalizeSecurityCode($attributes['security_code'] ?? $evidence?->security_code);
        $sourceMessageId = (string) data_get($attributes, 'source_payload.source_message_id', $evidence?->source_message_id);

        if ($evidence?->yape_payment_intent_id) {
            $intent = YapePaymentIntent::find($evidence->yape_payment_intent_id);
            if ($intent) {
                return $this->reusableIntentCandidate($intent, $evidence, 'evidence_has_existing_intent_id', 'evidence_id');
            }
        }

        if ($evidence) {
            $intent = YapePaymentIntent::query()
                ->where('client_evidence_id', $evidence->id)
                ->where('status', '!=', YapePaymentIntent::STATUS_CANCELLED)
                ->latest()
                ->first();

            if ($intent) {
                return $this->reusableIntentCandidate($intent, $evidence, 'same_client_evidence_id', 'client_evidence_id');
            }
        }

        if ($sourceMessageId !== '') {
            $intent = YapePaymentIntent::query()
                ->where('status', '!=', YapePaymentIntent::STATUS_CANCELLED)
                ->where(function ($query) use ($sourceMessageId) {
                    $query->where('source_payload->source_message_id', $sourceMessageId)
                        ->orWhereHas('clientEvidence', function ($evidenceQuery) use ($sourceMessageId) {
                            $evidenceQuery->where('source_message_id', $sourceMessageId);
                        });
                })
                ->latest()
                ->first();

            if ($intent) {
                return $this->reusableIntentCandidate($intent, $evidence, 'same_source_message_id', 'source_message_id');
            }
        }

        if ($operationCode && $amount > 0) {
            $paymentIntent = YapePaymentIntent::query()
                ->where('operation_code', $operationCode)
                ->where('expected_amount', $amount)
                ->where(function ($query) use ($securityCode) {
                    if ($securityCode) {
                        $query->whereNull('security_code')
                            ->orWhere('security_code', $securityCode);
                    }
                })
                ->where(function ($query) {
                    $query->whereNotNull('payment_id')
                        ->orWhere('status', YapePaymentIntent::STATUS_VALIDATED);
                })
                ->latest()
                ->first();

            if ($paymentIntent) {
                $this->appendYapeDirectDebug('yape_payment_already_registered', [
                    'evidence_id' => $evidence?->id,
                    'existing_intent_id' => $paymentIntent->id,
                    'existing_payment_id' => $paymentIntent->payment_id,
                    'operation_code' => $operationCode,
                    'amount' => $amount,
                    'decision' => 'reuse_existing_validated_intent',
                ]);

                return $paymentIntent;
            }
        }

        if ($operationCode || $securityCode) {
            $intentQuery = YapePaymentIntent::query()
                ->where('status', '!=', YapePaymentIntent::STATUS_CANCELLED)
                ->where('expected_amount', $amount)
                ->where('payment_mode', $paymentMode);

            if ($operationCode) {
                $intentQuery->where('operation_code', $operationCode);
            }

            if ($securityCode) {
                $intentQuery->where(function ($query) use ($securityCode) {
                    $query->whereNull('security_code')
                        ->orWhere('security_code', $securityCode);
                });
            }

            $intent = $intentQuery->latest()->first();
            if ($intent) {
                return $this->reusableIntentCandidate($intent, $evidence, 'same_yape_operation_fingerprint', 'operation_security_amount');
            }
        }

        $fallback = YapePaymentIntent::query()
            ->where('customer_id', $attributes['customer_id'] ?? null)
            ->where('invoice_id', $attributes['invoice_id'] ?? null)
            ->where('contract_id', $attributes['contract_id'] ?? null)
            ->where('expected_amount', $amount)
            ->where('payment_mode', $paymentMode)
            ->whereNull('client_evidence_id')
            ->whereNotIn('status', [YapePaymentIntent::STATUS_CANCELLED, YapePaymentIntent::STATUS_VALIDATED])
            ->latest()
            ->first();

        return $fallback
            ? $this->reusableIntentCandidate($fallback, $evidence, 'same_customer_invoice_amount_without_client_evidence', 'customer_invoice_amount')
            : null;
    }

    protected function reusableIntentCandidate(
        YapePaymentIntent $intent,
        ?YapeClientEvidence $evidence,
        string $reason,
        string $matchedBy
    ): ?YapePaymentIntent {
        if (!$evidence) {
            return $intent;
        }

        $intent->loadMissing('clientEvidence');

        if ($intent->client_evidence_id && (int) $intent->client_evidence_id !== (int) $evidence->id) {
            $linked = $intent->clientEvidence;
            $linkedMatch = $linked ? $this->clientEvidenceMatchesIntent($intent, $linked) : ['matched' => false, 'errors' => ['linked_evidence_missing']];
            $incomingMatch = $this->clientEvidenceMatchesIntent($intent, $evidence);
            $samePaymentFingerprint = $this->clientEvidencePaymentFingerprintMatches($linked, $evidence);

            if (!$samePaymentFingerprint || !$incomingMatch['matched']) {
                $this->appendYapeDirectDebug('yape_intent_evidence_mismatch', array_merge(
                    $this->intentEvidenceLogContext($intent, $linked, $linked?->id, $matchedBy, 'existing_intent_not_reused_for_different_evidence'),
                    [
                        'incoming_evidence_id' => $evidence->id,
                        'incoming_source_message_id' => $evidence->source_message_id,
                        'incoming_operation_code' => $evidence->operation_code,
                        'incoming_security_code' => $evidence->security_code,
                        'incoming_amount' => $evidence->parsed_amount,
                        'candidate_reason' => $reason,
                        'linked_match_errors' => $linkedMatch['errors'] ?? [],
                        'incoming_match_errors' => $incomingMatch['errors'] ?? [],
                    ]
                ));

                return null;
            }
        }

        $this->appendYapeDirectDebug('yape_existing_intent_reused', [
            'evidence_id' => $evidence->id,
            'intent_id' => $intent->id,
            'intent_number' => $intent->intent_number,
            'status' => $intent->status,
            'reason' => $reason,
            'matched_by' => $matchedBy,
            'customer_id' => $intent->customer_id,
            'invoice_id' => $intent->invoice_id,
            'operation_code' => $intent->operation_code,
        ]);

        return $intent;
    }

    protected function createIntentWithRetry(array $attributes, ?YapeClientEvidence $evidence = null): YapePaymentIntent
    {
        $maxAttempts = 10;
        $lastException = null;
        $attemptedIntentNumber = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $intent = DB::transaction(function () use ($attributes, $evidence, $attempt, &$attemptedIntentNumber) {
                    if ($existing = $this->findReusableIntentForCreate($attributes, $evidence)) {
                        $this->appendYapeDirectDebug('yape_existing_intent_reused', [
                            'evidence_id' => $evidence?->id,
                            'intent_id' => $existing->id,
                            'intent_number' => $existing->intent_number,
                            'status' => $existing->status,
                            'reason' => 'compatible_existing_intent_found_inside_transaction',
                            'matched_by' => 'transaction_lookup',
                            'customer_id' => $existing->customer_id,
                            'invoice_id' => $existing->invoice_id,
                            'operation_code' => $existing->operation_code,
                        ]);

                        return $existing;
                    }

                    $intentNumber = $this->nextYapeIntentNumber($attempt);
                    $attemptedIntentNumber = $intentNumber;

                    return YapePaymentIntent::create(array_merge($attributes, [
                        'intent_number' => $intentNumber,
                    ]));
                }, 3);

                if ($intent->wasRecentlyCreated) {
                    $this->appendYapeDirectDebug('yape_intent_created', [
                        'intent_id' => $intent->id,
                        'intent_number' => $intent->intent_number,
                        'evidence_id' => $evidence?->id,
                        'customer_id' => $intent->customer_id,
                        'invoice_id' => $intent->invoice_id,
                        'amount' => $intent->expected_amount,
                        'operation_code' => $intent->operation_code,
                        'security_code' => $intent->security_code,
                    ]);
                }

                if ($evidence && !$evidence->yape_payment_intent_id && !$intent->client_evidence_id) {
                    $intent->update(['client_evidence_id' => $evidence->id]);
                    $evidence->update([
                        'yape_payment_intent_id' => $intent->id,
                        'customer_id' => $intent->customer_id,
                        'status' => 'attached',
                    ]);
                    $intent = $intent->fresh(['clientEvidence']) ?: $intent;
                }

                return $intent;
            } catch (QueryException $exception) {
                $lastException = $exception;

                if ($this->isDuplicateIntentNumberException($exception)) {
                    $nextAttemptNumber = $this->peekNextYapeIntentNumber();
                    $this->appendYapeDirectDebug('yape_intent_duplicate_number_retry', [
                        'attempted_intent_number' => $attemptedIntentNumber,
                        'next_attempt_number' => $nextAttemptNumber,
                        'attempt' => $attempt,
                        'error_code' => $exception->errorInfo[1] ?? $exception->getCode(),
                        'error_message' => $exception->getMessage(),
                    ]);

                    continue;
                }

                $this->logIntentCreateFailed($exception, $attributes, $evidence, $attemptedIntentNumber, $attempt);
                throw $exception;
            } catch (\Throwable $exception) {
                $lastException = $exception;
                $this->logIntentCreateFailed($exception, $attributes, $evidence, $attemptedIntentNumber, $attempt);
                throw $exception;
            }
        }

        $this->logIntentCreateFailed($lastException, $attributes, $evidence, $attemptedIntentNumber, $maxAttempts);

        throw $lastException ?: new \RuntimeException('No se pudo crear intent Yape después de varios reintentos.');
    }

    protected function nextYapeIntentNumber(int $attempt = 1): string
    {
        $prefix = 'YPI-' . now()->format('Ymd');
        $lastIntentNumber = YapePaymentIntent::query()
            ->where('intent_number', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->orderByDesc('intent_number')
            ->value('intent_number');

        $lastSequence = 0;

        if (is_string($lastIntentNumber) && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastIntentNumber, $matches)) {
            $lastSequence = (int) $matches[1];
        }

        $generated = $prefix . '-' . str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

        $this->appendYapeDirectDebug('yape_intent_number_generation', [
            'date_prefix' => $prefix,
            'last_existing_number' => $lastIntentNumber,
            'next_sequence' => $lastSequence + 1,
            'generated_intent_number' => $generated,
            'attempt' => $attempt,
        ]);

        return $generated;
    }

    protected function peekNextYapeIntentNumber(): string
    {
        $prefix = 'YPI-' . now()->format('Ymd');
        $lastIntentNumber = YapePaymentIntent::query()
            ->where('intent_number', 'like', $prefix . '-%')
            ->orderByDesc('intent_number')
            ->value('intent_number');

        $lastSequence = 0;

        if (is_string($lastIntentNumber) && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastIntentNumber, $matches)) {
            $lastSequence = (int) $matches[1];
        }

        return $prefix . '-' . str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    protected function isDuplicateIntentNumberException(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062
            && str_contains($exception->getMessage(), 'yape_payment_intents_intent_number_unique');
    }

    protected function logIntentCreateFailed(?\Throwable $exception, array $attributes, ?YapeClientEvidence $evidence, ?string $attemptedIntentNumber, int $attempts): void
    {
        $this->appendYapeDirectDebug('yape_intent_create_failed', [
            'evidence_id' => $evidence?->id,
            'customer_id' => $attributes['customer_id'] ?? null,
            'invoice_id' => $attributes['invoice_id'] ?? null,
            'attempted_intent_number' => $attemptedIntentNumber,
            'attempts' => $attempts,
            'operation_code' => $attributes['operation_code'] ?? null,
            'security_code' => $attributes['security_code'] ?? null,
            'amount' => $attributes['expected_amount'] ?? null,
            'source_message_id' => data_get($attributes, 'source_payload.source_message_id'),
            'message' => $exception?->getMessage(),
            'file' => $exception?->getFile(),
            'line' => $exception?->getLine(),
        ]);
    }

    public function ingestClientEvidence(array $data, ?UploadedFile $file = null): YapeClientEvidence
    {
        if ($existing = $this->findExistingClientEvidenceBySourceMessageId($data['source_message_id'] ?? null)) {
            $this->markClientEvidenceDuplicateHit($existing, $data, 'source_message_id');

            return $this->retryClientEvidenceAttachment($existing);
        }

        $operationCode = $this->normalizeOperationCode($data['operation_code'] ?? null);
        $securityCode = $this->normalizeClientEvidenceSecurityCode(
            $this->normalizeSecurityCode($data['security_code'] ?? null),
            $operationCode
        );
        $amount = isset($data['amount']) ? round((float) $data['amount'], 2) : null;
        $normalizedData = array_merge($data, [
            'amount' => $amount,
            'operation_code' => $operationCode,
            'security_code' => $securityCode,
            'whatsapp_phone' => $this->normalizePhone((string) ($data['whatsapp_phone'] ?? '')),
        ]);

        if ($duplicate = $this->findDuplicateClientEvidence($normalizedData)) {
            $this->markClientEvidenceDuplicateHit($duplicate, $data, 'payment_fingerprint');

            return $this->retryClientEvidenceAttachment($duplicate);
        }

        $path = $this->storeEvidenceImage(
            $data,
            $file,
            config('billing.yape.client_evidence_dir', 'yape/client-evidences'),
            'client'
        );

        try {
            $evidence = YapeClientEvidence::create([
                'whatsapp_phone' => $normalizedData['whatsapp_phone'],
                'source_message_id' => $data['source_message_id'] ?? null,
                'source_chat_name' => $data['source_chat_name'] ?? null,
                'image_path' => $path,
                'raw_text' => $data['raw_text'] ?? null,
                'parsed_amount' => $amount,
                'operation_code' => $operationCode,
                'security_code' => $securityCode,
                'occurred_at' => $this->parseDateTime($data['occurred_at'] ?? null),
                'receiver_name' => $data['receiver_name'] ?? null,
                'receiver_phone' => $this->normalizePhone((string) ($data['receiver_phone'] ?? '')),
                'confidence' => round((float) ($data['confidence'] ?? 0), 2),
                'status' => $data['status'] ?? 'parsed',
                'payload' => $data,
                'processed_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateClientEvidenceMessage($exception, $data['source_message_id'] ?? null)
                && $existing = $this->findExistingClientEvidenceBySourceMessageId($data['source_message_id'] ?? null)) {
                $this->markClientEvidenceDuplicateHit($existing, $data, 'source_message_id');

                return $this->retryClientEvidenceAttachment($existing);
            }

            throw $exception;
        }

        if ($evidence->status === 'pending_ocr') {
            return $evidence->fresh(['intent']);
        }

        $intent = $this->findIntentForClientEvidence($evidence);

        if ($intent) {
            return $this->attachClientEvidenceToIntent($evidence, $intent);
        }

        return $evidence->fresh(['intent']);
    }

    protected function findDuplicateClientEvidence(array $data, ?int $excludeId = null): ?YapeClientEvidence
    {
        $amount = isset($data['amount'])
            ? round((float) $data['amount'], 2)
            : (isset($data['parsed_amount']) ? round((float) $data['parsed_amount'], 2) : 0.0);
        $securityCode = $this->normalizeSecurityCode($data['security_code'] ?? null);
        $operationCode = $this->normalizeOperationCode($data['operation_code'] ?? null);

        if ($amount <= 0 || (!$securityCode && !$operationCode)) {
            return null;
        }

        $chatKeys = $this->chatKeysFromOpenWaData($data);

        if (empty($chatKeys)) {
            return null;
        }

        return YapeClientEvidence::with('intent')
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->where('created_at', '>=', now()->subHours(self::DUPLICATE_CLIENT_EVIDENCE_WINDOW_HOURS))
            ->where('parsed_amount', $amount)
            ->whereNotIn('status', ['duplicate'])
            ->where(function ($query) use ($chatKeys) {
                foreach ($chatKeys as $chatKey) {
                    $query->orWhere('whatsapp_phone', $chatKey)
                        ->orWhere('source_chat_name', $chatKey);
                }
            })
            ->where(function ($query) use ($securityCode, $operationCode) {
                if ($securityCode) {
                    $query->orWhere('security_code', $securityCode);
                }

                if ($operationCode) {
                    $query->orWhere('operation_code', $operationCode);
                }
            })
            ->latest()
            ->first();
    }

    protected function markClientEvidenceDuplicateHit(
        YapeClientEvidence $original,
        array $incomingData = [],
        string $reason = 'duplicate'
    ): void {
        $payload = is_array($original->payload) ? $original->payload : [];
        $duplicateCount = ((int) ($payload['duplicate_count'] ?? 0)) + 1;
        $history = is_array($payload['duplicate_history'] ?? null)
            ? $payload['duplicate_history']
            : [];

        $history[] = [
            'reason' => $reason,
            'source_message_id' => $incomingData['source_message_id'] ?? null,
            'source_chat_name' => $incomingData['source_chat_name'] ?? null,
            'at' => now()->toDateTimeString(),
        ];

        $payload['duplicate_count'] = $duplicateCount;
        $payload['last_duplicate_at'] = now()->toDateTimeString();
        $payload['duplicate_history'] = array_slice($history, -10);

        $original->update(['payload' => $payload]);
    }

    protected function markClientEvidenceAsDuplicate(
        YapeClientEvidence $duplicate,
        YapeClientEvidence $original,
        string $reason = 'payment_fingerprint'
    ): void {
        $payload = is_array($duplicate->payload) ? $duplicate->payload : [];
        $payload['duplicate_of_evidence_id'] = $original->id;
        $payload['duplicate_reason'] = $reason;
        $payload['duplicate_detected_at'] = now()->toDateTimeString();

        $duplicate->update([
            'status' => 'duplicate',
            'payload' => $payload,
        ]);

        $this->markClientEvidenceDuplicateHit($original, [
            'source_message_id' => $duplicate->source_message_id,
            'source_chat_name' => $duplicate->source_chat_name,
        ], $reason);
    }

    protected function findExistingClientEvidenceBySourceMessageId(mixed $sourceMessageId): ?YapeClientEvidence
    {
        $sourceMessageId = is_string($sourceMessageId) ? trim($sourceMessageId) : '';

        if ($sourceMessageId === '') {
            return null;
        }

        return YapeClientEvidence::with('intent')
            ->where('source_message_id', $sourceMessageId)
            ->first();
    }

    protected function retryClientEvidenceAttachment(YapeClientEvidence $evidence): YapeClientEvidence
    {
        $evidence = $evidence->fresh(['intent']) ?: $evidence;

        if ($evidence->yape_payment_intent_id) {
            $intent = $evidence->intent;

            if ($intent) {
                $this->maybeReconcile($this->attachExistingEvidence($intent->fresh()));
            }

            return $evidence->fresh(['intent']);
        }

        if ($evidence->parsed_amount && ($evidence->security_code || $evidence->operation_code)) {
            $intent = $this->findIntentForClientEvidence($evidence);

            if ($intent) {
                return $this->attachClientEvidenceToIntent($evidence, $intent);
            }
        }

        return $evidence->fresh(['intent']);
    }

    protected function isDuplicateClientEvidenceMessage(QueryException $exception, mixed $sourceMessageId): bool
    {
        $sourceMessageId = is_string($sourceMessageId) ? trim($sourceMessageId) : '';

        return $sourceMessageId !== ''
            && str_contains($exception->getMessage(), 'yape_client_evidences_source_message_id_unique');
    }

    public function resolveAmbiguousClientEvidenceByDni(array $data, string $dni): ?YapeClientEvidence
    {
        $result = $this->resolveAmbiguousClientEvidenceByDniWithResult($data, $dni);

        return ($result['resolved'] ?? false) ? ($result['evidence'] ?? null) : null;
    }

    public function resumeClientEvidenceByDni(YapeClientEvidence $evidence, string $dni): array
    {
        return DB::transaction(function () use ($evidence, $dni) {
            $evidence = YapeClientEvidence::query()
                ->with('intent.payment')
                ->lockForUpdate()
                ->findOrFail($evidence->id);

            $equivalent = YapeClientEvidence::query()
                ->where('source_chat_name', $evidence->source_chat_name)
                ->where('operation_code', $evidence->operation_code)
                ->where('security_code', $evidence->security_code)
                ->where('parsed_amount', $evidence->parsed_amount)
                ->when($evidence->occurred_at, fn ($query) => $query->whereBetween(
                    'occurred_at',
                    [$evidence->occurred_at->copy()->subMinutes(2), $evidence->occurred_at->copy()->addMinutes(2)]
                ))
                ->lockForUpdate()
                ->get();

            $evidence = $equivalent
                ->sortBy(fn (YapeClientEvidence $item) => sprintf(
                    '%s-%010d',
                    optional($item->occurred_at)->format('YmdHis.u') ?: '99999999999999',
                    $item->id
                ))
                ->first() ?: $evidence;

            if ((float) $evidence->parsed_amount <= 0
                || ((string) $evidence->operation_code === '' && (string) $evidence->security_code === '')) {
                throw new \RuntimeException('La captura no contiene una huella Yape suficiente para reanudarla.');
            }

            $existingIntent = $evidence->intent ?: YapePaymentIntent::query()
                ->with('payment')
                ->where(function ($query) use ($evidence) {
                    $query->where('client_evidence_id', $evidence->id)
                        ->orWhere(function ($fingerprint) use ($evidence) {
                            $fingerprint->where('operation_code', $evidence->operation_code)
                                ->where('expected_amount', round((float) $evidence->parsed_amount, 2));

                            if ($evidence->security_code) {
                                $fingerprint->where(function ($security) use ($evidence) {
                                    $security->whereNull('security_code')
                                        ->orWhere('security_code', $evidence->security_code);
                                });
                            }
                        });
                })
                ->where('status', '!=', YapePaymentIntent::STATUS_CANCELLED)
                ->lockForUpdate()
                ->latest()
                ->first();

            $isAwaitingDni = data_get($evidence->payload, 'yape_chat_state.state') === 'awaiting_dni'
                && empty(data_get($evidence->payload, 'dni_resolution.resolved_at'));

            // A receiver event may have linked the capture to an existing
            // intent before the customer replied with the DNI. That link is
            // not a completed identification: continue through the DNI flow
            // instead of treating the operation as already processed.
            if ($existingIntent && !$isAwaitingDni) {
                return [
                    'resolved' => true,
                    'reason' => 'already_processed',
                    'evidence' => $evidence,
                    'intent' => $existingIntent,
                    'payment' => $existingIntent->payment,
                    'idempotent' => true,
                ];
            }

            $existingPayment = Payment::query()
                ->where('status', 'completed')
                ->where('amount', round((float) $evidence->parsed_amount, 2))
                ->where(function ($query) use ($evidence) {
                    $query->where('reference', $evidence->operation_code)
                        ->orWhere('payment_details->operation_code', $evidence->operation_code);
                })
                ->lockForUpdate()
                ->latest('payment_date')
                ->first();

            if ($existingPayment) {
                return [
                    'resolved' => true,
                    'reason' => 'already_processed',
                    'evidence' => $evidence,
                    'intent' => null,
                    'payment' => $existingPayment,
                    'idempotent' => true,
                ];
            }

            if (!in_array($evidence->status, ['parsed', 'ambiguous', 'attached'], true)
                || data_get($evidence->payload, 'yape_chat_state.state') !== 'awaiting_dni') {
                throw new \RuntimeException('La captura ya no está esperando DNI. Actualiza la pantalla antes de continuar.');
            }

            $receiverCandidates = YapeReceiverEvent::query()
                ->whereNull('yape_payment_intent_id')
                ->where('parsed_amount', round((float) $evidence->parsed_amount, 2))
                ->whereBetween('occurred_at', [
                    $evidence->occurred_at->copy()->subMinutes(5),
                    $evidence->occurred_at->copy()->addMinutes(5),
                ])
                ->when($evidence->operation_code, function ($query) use ($evidence) {
                    $query->where(function ($operation) use ($evidence) {
                        $operation->whereNull('operation_code')
                            ->orWhere('operation_code', '')
                            ->orWhere('operation_code', $evidence->operation_code);
                    });
                })
                ->when($evidence->security_code, fn ($query) => $query->where('security_code', $evidence->security_code))
                ->lockForUpdate()
                ->get();

            $this->deferAutomaticPaymentCompletion = true;
            $this->resumeReceiverEventIds = $receiverCandidates->pluck('id')->all();

            try {
                $result = $this->resolveAmbiguousClientEvidenceByDniWithResult([
                    'client_evidence_id' => $evidence->id,
                    'openwa_chat_id' => $evidence->source_chat_name,
                    'openwa_chat_id_original' => $evidence->source_chat_name,
                    'source_chat_name' => $evidence->source_chat_name,
                    'whatsapp_phone' => $evidence->whatsapp_phone,
                ], $dni);

                // A known customer without an exact invoice is still a real
                // operation. Keep one observed intent in the historic review
                // queue instead of losing the evidence or creating a payment.
                if (empty($result['intent'])) {
                    $observedIntent = $this->createObservedIntentForDniEvidence($evidence, $dni);

                    if ($observedIntent) {
                        $result['intent'] = $observedIntent;
                        $result['evidence'] = $evidence->fresh(['intent']) ?: $evidence;
                    }
                }
            } finally {
                $this->deferAutomaticPaymentCompletion = false;
                $this->resumeReceiverEventIds = [];
            }

            $resolvedEvidence = ($result['evidence'] ?? $evidence)->fresh(['intent.payment']) ?: $evidence;
            $intent = $resolvedEvidence->intent;
            $historicalReceiverMatched = false;

            // A screenshot may be sent days after the payment. Normal matching
            // deliberately uses a short time window and must remain automatic
            // only for current transactions. If the DNI identifies the customer
            // and there is one older, unused receiver event with the exact
            // operation/amount (and security code when available), keep it for
            // an explicit human confirmation instead of auto-registering it.
            if ($intent && !$intent->payment_id && !$intent->receiver_event_id) {
                $intent = $this->attachHistoricalReceiverForManualReview($intent, $resolvedEvidence);
                $historicalReceiverMatched = (bool) data_get($intent?->source_payload, 'historical_review.is_historical');
            }

            // Only the fully determined path is allowed to enter the normal
            // reconciliation. All other paths remain in the historical queue.
            if (!$historicalReceiverMatched && $this->canAutomaticallyCompleteResumedIntent($result, $intent, $receiverCandidates)) {
                $intent = $this->tryReconcile($intent->fresh());
                $resolvedEvidence = $resolvedEvidence->fresh(['intent.payment']) ?: $resolvedEvidence;
            }

            if ($intent && !$intent->payment_id) {
                $intent->loadMissing('receiverEvent');

                if ($intent->receiver_event_id && $intent->status !== YapePaymentIntent::STATUS_OBSERVED) {
                    $intent->update([
                        'status' => YapePaymentIntent::STATUS_OBSERVED,
                        'observed_reason' => 'Reanudado manualmente con DNI; pendiente de validación en el flujo Yape.',
                    ]);
                }

            }

            if ($intent) {
                $equivalent
                    ->where('id', '!=', $resolvedEvidence->id)
                    ->each(function (YapeClientEvidence $duplicate) use ($intent) {
                        $duplicate->update([
                            'yape_payment_intent_id' => $intent->id,
                            'customer_id' => $intent->customer_id,
                            'status' => 'duplicate',
                        ]);
                    });
            }

            return array_merge($result, [
                'evidence' => $resolvedEvidence->fresh(['intent.payment']),
                'intent' => $intent?->fresh(['payment', 'clientEvidence', 'receiverEvent']),
                'payment' => $intent?->payment,
                'idempotent' => false,
            ]);
        });
    }

    /**
     * Link a late WhatsApp screenshot to a historical receiver notification.
     * This method intentionally never calls reconciliation: an operator must
     * review and validate the payment from the Yape review screen.
     */
    protected function attachHistoricalReceiverForManualReview(
        YapePaymentIntent $intent,
        YapeClientEvidence $evidence
    ): YapePaymentIntent {
        $operationCode = $this->normalizeOperationCode($evidence->operation_code);
        $amount = round((float) $evidence->parsed_amount, 2);

        if (!$operationCode || $amount <= 0 || !$evidence->occurred_at) {
            return $intent;
        }

        $receiverCandidates = YapeReceiverEvent::query()
            ->whereNull('yape_payment_intent_id')
            ->whereNotIn('status', ['cancelled'])
            ->where('parsed_amount', $amount)
            ->where('operation_code', $operationCode)
            ->where('occurred_at', '<', $evidence->occurred_at->copy()->subMinutes(5))
            ->when($evidence->security_code, fn ($query) => $query->where('security_code', $evidence->security_code))
            ->lockForUpdate()
            ->get();

        // Never choose between multiple older transactions. Exact uniqueness is
        // required because this branch bypasses the regular time-window match.
        if ($receiverCandidates->count() !== 1) {
            return $intent;
        }

        $receiver = $receiverCandidates->first();
        $payload = is_array($intent->source_payload) ? $intent->source_payload : [];
        $payload['historical_review'] = [
            'is_historical' => true,
            'receiver_event_id' => $receiver->id,
            'receiver_occurred_at' => optional($receiver->occurred_at)->toDateTimeString(),
            'capture_occurred_at' => optional($evidence->occurred_at)->toDateTimeString(),
            'matched_at' => now()->toDateTimeString(),
            'match_rule' => 'exact_operation_amount_and_security_when_available',
        ];

        $receiver->update([
            'yape_payment_intent_id' => $intent->id,
            'status' => 'attached',
        ]);

        $intent->update([
            'receiver_event_id' => $receiver->id,
            'status' => YapePaymentIntent::STATUS_OBSERVED,
            'observed_reason' => 'Pago histórico detectado: la captura llegó después de la notificación receptora y requiere validación manual.',
            'source_payload' => $payload,
        ]);

        return $intent->fresh(['clientEvidence', 'receiverEvent', 'payment']);
    }

    protected function createObservedIntentForDniEvidence(YapeClientEvidence $evidence, string $dni): ?YapePaymentIntent
    {
        $customers = Customer::query()
            ->where('identity_document', $dni)
            ->lockForUpdate()
            ->get();

        // Never select an arbitrary customer for a duplicated DNI.
        if ($customers->count() !== 1) {
            return null;
        }

        $customer = $customers->first();

        $contracts = Contract::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->get();
        $contract = $contracts->count() === 1 ? $contracts->first() : null;

        $intent = $this->createIntent([
            'customer_id' => $customer->id,
            'customer_name' => $customer->full_name,
            'whatsapp_phone' => $evidence->whatsapp_phone,
            'amount' => round((float) $evidence->parsed_amount, 2),
            'expected_amount' => round((float) $evidence->parsed_amount, 2),
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
            'client_evidence_id' => $evidence->id,
            'payment_mode' => 'single',
            'observed_reason' => 'DNI confirmado, pero el pago requiere revisión antes de registrarse.',
        ], null, $contract);

        $intent->update([
            'status' => YapePaymentIntent::STATUS_OBSERVED,
            'observed_reason' => 'DNI confirmado, pero el pago requiere revisión antes de registrarse.',
        ]);

        return $intent->fresh(['payment']);
    }

    protected function canAutomaticallyCompleteResumedIntent(array $result, ?YapePaymentIntent $intent, $receiverCandidates): bool
    {
        if (!((bool) ($result['resolved'] ?? false)
            && $intent
            && !$intent->payment_id
            && $intent->customer_id
            && $intent->contract_id
            && $intent->invoice_id
            && $intent->receiver_event_id
            && $receiverCandidates->count() === 1
            && (int) $intent->receiver_event_id === (int) $receiverCandidates->first()->id)) {
            return false;
        }

        return Invoice::query()
            ->where('contract_id', $intent->contract_id)
            ->where('status', 'pending')
            ->where('total_amount', round((float) $intent->expected_amount, 2))
            ->lockForUpdate()
            ->count() === 1;
    }

    public function resolveAmbiguousClientEvidenceByDniWithResult(array $data, string $dni): array
    {
        $dni = preg_replace('/\D+/', '', $dni);
        $chatId = (string) ($data['openwa_chat_id'] ?? $data['source_chat_name'] ?? '');

        if (strlen($dni) !== 8) {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_INVALID_DNI);
        }

        $evidence = !empty($data['client_evidence_id'])
            ? YapeClientEvidence::with('intent')
                ->lockForUpdate()
                ->find((int) $data['client_evidence_id'])
            : $this->pendingClientEvidenceForOpenWaChat($data);

        $this->appendYapeDirectDebug('yape_dni_received', [
            'chat_id' => $chatId,
            'dni' => $dni,
            'pending_evidence_id' => $evidence?->id,
        ]);

        if (!$evidence && $this->hasMultiplePendingEvidencesForDniResolution($data)) {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_MULTIPLE_PENDING_EVIDENCES);
        }

        $evidence ??= $this->latestClientEvidenceForDniResolution($data);

        \Log::info('Yape DNI busca evidencia pendiente.', [
            'chat_id_original' => $data['openwa_chat_id_original'] ?? null,
            'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
            'evidence_id' => $evidence?->id,
            'evidence_status' => $evidence?->status,
            'evidence_amount' => $evidence?->parsed_amount,
            'evidence_operation_code' => $evidence?->operation_code,
            'evidence_security_code' => $evidence?->security_code,
        ]);

        if (!$evidence) {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_EVIDENCE_NOT_FOUND);
        }

        if (!$this->clientEvidenceHasDniResolutionFingerprint($evidence)) {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_EVIDENCE_INCOMPLETE, $evidence);
        }

        $candidateCustomers = $this->candidateCustomersForDni($dni);
        $customer = $candidateCustomers->first();
        $hasPendingDebt = $candidateCustomers->isNotEmpty()
            ? $this->customersHavePendingDebt($candidateCustomers)
            : false;

        $this->appendYapeDirectDebug('yape_dni_client_lookup', [
            'dni' => $dni,
            'found_client' => $candidateCustomers->isNotEmpty(),
            'client_id' => $customer?->id,
            'candidate_customers_count' => $candidateCustomers->count(),
            'has_pending_debt' => $hasPendingDebt,
            'pending_evidence_id' => $evidence->id,
            'decision' => $candidateCustomers->isNotEmpty() ? 'customers_found_continue_safe_invoice_selection' : 'customer_not_found_keep_pending',
        ]);

        if ($candidateCustomers->isEmpty()) {
            return $this->registerFailedDniResolutionAttempt(
                $evidence,
                $dni,
                self::DNI_RESOLUTION_CUSTOMER_NOT_FOUND
            );
        }

        if ($candidateCustomers->count() > 1) {
            $this->appendYapeDirectDebug('yape_dni_duplicate_detected', [
                'chat_id' => $chatId,
                'dni' => $dni,
                'evidence_id' => $evidence->id,
                'ocr_amount' => (float) $evidence->parsed_amount,
                'candidate_customers_count' => $candidateCustomers->count(),
                'candidate_customer_ids' => $candidateCustomers->pluck('id')->values()->all(),
            ]);
        }

        $invoiceDecision = $this->decideInvoiceForDniEvidence($candidateCustomers, $evidence, $dni, $chatId);

        if (($invoiceDecision['decision'] ?? null) === 'selection_required') {
            $this->markEvidenceAwaitingServiceSelection(
                $evidence,
                $dni,
                $invoiceDecision['options'] ?? [],
                $invoiceDecision['reason'] ?? 'multiple_amount_matches'
            );

            return $this->dniResolutionResult(
                false,
                self::DNI_RESOLUTION_SERVICE_SELECTION_REQUIRED,
                $evidence->fresh(['intent']),
                0,
                false,
                [
                    'response_message' => $this->serviceSelectionMessage($invoiceDecision['options'] ?? []),
                    'options' => $invoiceDecision['options'] ?? [],
                    'decision' => 'selection_required',
                ]
            );
        }

        if (($invoiceDecision['decision'] ?? null) === 'credit_only') {
            $contract = $invoiceDecision['contract'] ?? null;
            $intent = $contract instanceof Contract
                ? $this->createCreditOnlyIntentForContractEvidence($contract, $evidence, $invoiceDecision['decision'] ?? 'credit_only')
                : null;

            if (!$intent) {
                return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence->fresh(['intent']));
            }

            return $this->markDniResolutionSuccess(
                $this->attachClientEvidenceToIntent($evidence, $intent),
                $dni
            );
        }

        if (($invoiceDecision['decision'] ?? null) === 'no_pending') {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence->fresh(['intent']));
        }

        if (($invoiceDecision['decision'] ?? null) === 'amount_mismatch') {
            $this->markEvidenceAwaitingDni($evidence, [
                'reason' => self::DNI_RESOLUTION_AMOUNT_MISMATCH,
                'last_dni' => $dni,
            ]);

            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_AMOUNT_MISMATCH, $evidence->fresh(['intent']));
        }

        if (($invoiceDecision['decision'] ?? null) === 'ambiguous') {
            $evidence->update(['status' => 'ambiguous']);

            $this->appendYapeDirectDebug('yape_multi_invoice_validation_observed', [
                'chat_id' => $chatId,
                'dni' => $dni,
                'evidence_id' => $evidence->id,
                'ocr_amount' => (float) $evidence->parsed_amount,
                'candidate_decisions' => $invoiceDecision['candidate_decisions'] ?? [],
                'decision' => 'ambiguous_payment_observed',
            ]);

            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_AMBIGUOUS_PAYMENT, $evidence->fresh(['intent']));
        }

        $selectedInvoice = $invoiceDecision['invoice'] ?? null;
        $customer = $selectedInvoice?->contract?->customer ?: $customer;

        if ($evidence->yape_payment_intent_id) {
            $intent = $evidence->intent;

            if (!$intent) {
                return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence);
            }

            if ($intent->customer_id && (int) $intent->customer_id !== (int) $customer->id) {
                if ($intent->status !== YapePaymentIntent::STATUS_VALIDATED) {
                    $evidence->update([
                        'customer_id' => $customer->id,
                        'status' => 'ambiguous',
                    ]);
                }

                return $this->dniResolutionResult(false, self::DNI_RESOLUTION_CUSTOMER_MISMATCH, $evidence->fresh(['intent']));
            }

            $intent->update(array_filter([
                'customer_id' => $intent->customer_id ?: $customer->id,
                'customer_name' => $intent->customer_name ?: $customer->full_name,
                'whatsapp_phone' => $intent->whatsapp_phone ?: $this->normalizePhone((string) $customer->phone),
            ], fn ($value) => $value !== null && $value !== ''));

            $evidence->update([
                'customer_id' => $customer->id,
            ]);

            $this->maybeReconcile($this->attachExistingEvidence($intent->fresh()));

            return $this->markDniResolutionSuccess($evidence->fresh(['intent']), $dni);
        }

        $intent = $selectedInvoice
            ? $this->createIntentFromInvoicesForCustomerEvidence(
                $invoiceDecision['invoices'] ?? [$selectedInvoice],
                $evidence,
                $invoiceDecision['decision'] ?? 'auto_selected_invoice'
            )
            : ($this->findIntentForCustomerEvidence($customer, $evidence)
                ?: $this->createIntentFromPendingInvoiceForCustomerEvidence($customer, $evidence));

        if (!$intent) {
            $this->appendYapeDirectDebug('yape_dni_client_lookup', [
                'dni' => $dni,
                'found_client' => true,
                'client_id' => $customer->id,
                'has_pending_debt' => $hasPendingDebt,
                'pending_evidence_id' => $evidence->id,
                'decision' => 'customer_found_no_matching_pending_payment_keep_pending',
            ]);

            $this->markEvidenceAwaitingDni($evidence, [
                'reason' => self::DNI_RESOLUTION_NO_PENDING_PAYMENT,
                'last_dni' => $dni,
                'customer_id' => $customer->id,
            ]);

            $this->appendYapeDirectDebug('yape_dni_failed_keep_pending', [
                'reason' => self::DNI_RESOLUTION_NO_PENDING_PAYMENT,
                'pending_evidence_id' => $evidence->id,
                'status_after' => $evidence->fresh()?->status,
            ]);

            $evidence->update([
                'customer_id' => $customer->id,
                'status' => 'ambiguous',
            ]);

            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence->fresh(['intent']));
        }

        \Log::info('Yape DNI resolvió pending/intent.', [
            'evidence_id' => $evidence->id,
            'intent_id' => $intent->id,
            'customer_id' => $customer->id,
            'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
            'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
            'expected_amount' => $intent->expected_amount,
            'intent_status' => $intent->status,
        ]);

        return $this->markDniResolutionSuccess(
            $this->attachClientEvidenceToIntent($evidence, $intent),
            $dni
        );
    }

    public function previewDniResolutionForOpenWa(array $data, string $dni): array
    {
        $dni = preg_replace('/\D+/', '', $dni);
        $evidence = $this->pendingClientEvidenceForOpenWaChat($data);
        $candidateCustomers = strlen($dni) === 8
            ? $this->candidateCustomersForDni($dni)
            : collect();
        $customer = $candidateCustomers->first();
        $hasPendingDebt = $candidateCustomers->isNotEmpty()
            ? $this->customersHavePendingDebt($candidateCustomers)
            : false;
        $decision = ($evidence && $candidateCustomers->isNotEmpty())
            ? $this->decideInvoiceForDniEvidence(
                $candidateCustomers,
                $evidence,
                $dni,
                (string) ($data['openwa_chat_id'] ?? $data['source_chat_name'] ?? '')
            )
            : ['decision' => null];
        $safeAutoDecision = in_array($decision['decision'] ?? null, [
            'auto_selected_single_pending',
            'oldest_invoices_sum_match',
        ], true);

        return [
            'dni' => $dni,
            'evidence' => $evidence,
            'pending_evidence_id' => $evidence?->id,
            'found_client' => $candidateCustomers->isNotEmpty(),
            'client_id' => $customer?->id,
            'candidate_customers_count' => $candidateCustomers->count(),
            'has_pending_debt' => $hasPendingDebt,
            'decision' => $decision['decision'] ?? null,
            'should_send_validation_response' => (bool) ($evidence && $candidateCustomers->isNotEmpty() && $hasPendingDebt && $safeAutoDecision),
        ];
    }

    protected function dniResolutionResult(
        bool $resolved,
        string $reason,
        ?YapeClientEvidence $evidence = null,
        int $attempts = 0,
        bool $manualReviewRequired = false,
        array $extra = []
    ): array {
        return array_merge([
            'resolved' => $resolved,
            'reason' => $reason,
            'evidence' => $evidence,
            'attempts' => $attempts,
            'max_attempts' => self::DNI_RESOLUTION_MAX_ATTEMPTS,
            'manual_review_required' => $manualReviewRequired,
        ], $extra);
    }

    protected function registerFailedDniResolutionAttempt(
        YapeClientEvidence $evidence,
        string $dni,
        string $reason
    ): array {
        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $dniResolution = is_array($payload['dni_resolution'] ?? null)
            ? $payload['dni_resolution']
            : [];

        if (($dniResolution['last_dni'] ?? null) === $dni
            && ($dniResolution['last_error'] ?? null) === $reason) {
            $this->markEvidenceAwaitingDni($evidence, [
                'reason' => $reason,
                'last_dni' => $dni,
            ]);

            $this->appendYapeDirectDebug('yape_dni_failed_keep_pending', [
                'reason' => $reason,
                'pending_evidence_id' => $evidence->id,
                'status_after' => $evidence->fresh()?->status,
                'duplicate_dni_attempt' => true,
            ]);

            return $this->dniResolutionResult(
                false,
                $reason,
                $evidence->fresh(['intent']),
                (int) ($dniResolution['attempts'] ?? 0),
                false
            );
        }

        $attempts = ((int) ($dniResolution['attempts'] ?? 0)) + 1;
        $manualReviewRequired = false;
        $history = is_array($dniResolution['history'] ?? null)
            ? $dniResolution['history']
            : [];

        $history[] = [
            'attempt' => $attempts,
            'dni' => $dni,
            'error' => $reason,
            'at' => now()->toDateTimeString(),
        ];

        $payload['dni_resolution'] = [
            'attempts' => $attempts,
            'max_attempts' => self::DNI_RESOLUTION_MAX_ATTEMPTS,
            'last_error' => $reason,
            'last_dni' => $dni,
            'manual_review_required' => $manualReviewRequired,
            'history' => array_slice($history, -10),
        ];
        $payload['yape_chat_state'] = array_merge(
            is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [],
            [
                'state' => 'awaiting_dni',
                'awaiting_dni' => true,
                'last_error' => $reason,
                'last_dni' => $dni,
                'updated_at' => now()->toDateTimeString(),
            ]
        );

        $evidence->update([
            'status' => 'ambiguous',
            'payload' => $payload,
        ]);

        $this->appendYapeDirectDebug('yape_dni_failed_keep_pending', [
            'reason' => $reason,
            'pending_evidence_id' => $evidence->id,
            'status_after' => 'ambiguous',
            'attempts' => $attempts,
        ]);

        return $this->dniResolutionResult(
            false,
            $reason,
            $evidence->fresh(['intent']),
            $attempts,
            false
        );
    }

    protected function markDniResolutionSuccess(YapeClientEvidence $evidence, string $dni): array
    {
        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $dniResolution = is_array($payload['dni_resolution'] ?? null)
            ? $payload['dni_resolution']
            : [];

        $payload['dni_resolution'] = array_merge($dniResolution, [
            'last_error' => null,
            'last_dni' => $dni,
            'manual_review_required' => false,
            'resolved_at' => now()->toDateTimeString(),
        ]);
        $payload['yape_chat_state'] = array_merge(
            is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [],
            [
                'state' => 'resolved',
                'awaiting_dni' => false,
                'awaiting_service_selection' => false,
                'resolved_at' => now()->toDateTimeString(),
            ]
        );

        $evidence->update(['payload' => $payload]);

        return $this->dniResolutionResult(
            true,
            self::DNI_RESOLUTION_RESOLVED,
            $evidence->fresh(['intent']),
            (int) ($payload['dni_resolution']['attempts'] ?? 0),
            false
        );
    }

    protected function latestClientEvidenceForDniResolution(array $data): ?YapeClientEvidence
    {
        $chatKeys = $this->chatKeysFromOpenWaData($data);

        if (empty($chatKeys)) {
            return null;
        }

        return YapeClientEvidence::with('intent')
            ->where('created_at', '>=', now()->subHours(2))
            ->whereIn('status', ['parsed', 'incomplete', 'ambiguous', 'attached', 'matched'])
            ->where(function ($query) use ($chatKeys) {
                foreach ($chatKeys as $chatKey) {
                    $query->orWhere('whatsapp_phone', $chatKey)
                        ->orWhere('source_chat_name', $chatKey);
                }
            })
            ->latest()
            ->first();
    }

    public function clientEvidenceHasDniResolutionFingerprint(YapeClientEvidence $evidence): bool
    {
        return (float) $evidence->parsed_amount > 0
            && ((string) $evidence->security_code !== '' || (string) $evidence->operation_code !== '');
    }

    public function hasPendingClientEvidenceForOpenWaChat(array $data): bool
    {
        return $this->pendingClientEvidenceForOpenWaChat($data) !== null;
    }

    public function pendingClientEvidenceForOpenWaChat(array $data): ?YapeClientEvidence
    {
        $chatKeys = $this->chatKeysFromOpenWaData($data);

        if (empty($chatKeys)) {
            return null;
        }

        return YapeClientEvidence::with('intent')
            ->where('created_at', '>=', now()->subHours(2))
            ->whereIn('status', ['parsed', 'ambiguous', 'attached'])
            ->where(function ($query) use ($chatKeys) {
                foreach ($chatKeys as $chatKey) {
                    $query->orWhere('whatsapp_phone', $chatKey)
                        ->orWhere('source_chat_name', $chatKey);
                }
            })
            ->latest()
            ->get()
            ->first(fn (YapeClientEvidence $evidence) => $this->clientEvidenceIsAwaitingDni($evidence));
    }

    public function chatStateForOpenWaChat(array $data): array
    {
        $pending = $this->pendingClientEvidenceForOpenWaChat($data);
        $state = is_array($pending?->payload) && is_array($pending->payload['yape_chat_state'] ?? null)
            ? $pending->payload['yape_chat_state']
            : [];
        $dniPrompt = is_array($pending?->payload) ? data_get($pending->payload, 'openwa_messages.dni_prompt') : null;
        $lastMessage = is_array($pending?->payload) && is_array($pending->payload['openwa_messages'] ?? null)
            ? collect($pending->payload['openwa_messages'])->filter(fn ($item) => is_array($item))->sortByDesc('sent_at')->first()
            : null;
        $currentState = $pending
            ? (string) ($state['state'] ?? 'awaiting_dni')
            : 'idle';

        return [
            'chat_id' => $data['openwa_chat_id'] ?? $data['source_chat_name'] ?? null,
            'current_state' => $currentState,
            'pending_evidence_id' => $pending?->id,
            'pending_status' => $pending?->status,
            'awaiting_dni' => (bool) $pending,
            'awaiting_service_selection' => $currentState === 'awaiting_service_selection',
            'validating_payment' => $pending?->intent?->status === YapePaymentIntent::STATUS_WAITING_RECEIVER,
            'last_prompt_at' => is_array($dniPrompt) ? ($dniPrompt['sent_at'] ?? null) : null,
            'last_message_context' => is_array($lastMessage) ? ($lastMessage['context'] ?? null) : null,
            'evidence' => $pending,
            'state' => $state,
        ];
    }

    public function logChatStateLoaded(array $data): array
    {
        $state = $this->chatStateForOpenWaChat($data);

        $this->appendYapeDirectDebug('yape_chat_state_loaded', [
            'chat_id' => $state['chat_id'],
            'state' => $state['current_state'],
            'pending_evidence_id' => $state['pending_evidence_id'],
            'pending_status' => $state['pending_status'],
            'awaiting_dni' => $state['awaiting_dni'],
            'awaiting_service_selection' => $state['awaiting_service_selection'] ?? false,
            'validating_payment' => $state['validating_payment'],
            'last_prompt_at' => $state['last_prompt_at'],
            'last_message_context' => $state['last_message_context'],
        ]);

        return $state;
    }

    public function markEvidenceAwaitingDni(YapeClientEvidence $evidence, array $context = []): YapeClientEvidence
    {
        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $payload['yape_chat_state'] = array_merge(
            is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [],
            array_filter([
                'state' => 'awaiting_dni',
                'awaiting_dni' => true,
                'evidence_id' => $evidence->id,
                'chat_id' => $evidence->source_chat_name,
                'last_prompt_at' => data_get($payload, 'openwa_messages.dni_prompt.sent_at'),
                'updated_at' => now()->toDateTimeString(),
            ] + $context, fn ($value) => $value !== null)
        );

        $evidence->update([
            'status' => in_array($evidence->status, ['parsed', 'ambiguous', 'attached'], true)
                ? $evidence->status
                : 'ambiguous',
            'payload' => $payload,
        ]);

        return $evidence->fresh(['intent']) ?: $evidence;
    }

    protected function markEvidenceAwaitingServiceSelection(
        YapeClientEvidence $evidence,
        string $dni,
        array $options,
        string $reason
    ): YapeClientEvidence {
        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $payload['yape_chat_state'] = array_merge(
            is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [],
            [
                'state' => 'awaiting_service_selection',
                'awaiting_dni' => false,
                'awaiting_service_selection' => true,
                'evidence_id' => $evidence->id,
                'chat_id' => $evidence->source_chat_name,
                'dni' => $dni,
                'ocr_amount' => round((float) $evidence->parsed_amount, 2),
                'options' => $options,
                'selection_reason' => $reason,
                'updated_at' => now()->toDateTimeString(),
            ]
        );

        $evidence->update([
            'status' => 'ambiguous',
            'payload' => $payload,
        ]);

        $this->appendYapeDirectDebug('yape_invoice_selection_options_sent', [
            'chat_id' => $evidence->source_chat_name,
            'dni' => $dni,
            'evidence_id' => $evidence->id,
            'ocr_amount' => round((float) $evidence->parsed_amount, 2),
            'options_count' => count($options),
            'options' => $options,
            'decision' => 'awaiting_service_selection',
        ]);

        return $evidence->fresh(['intent']) ?: $evidence;
    }

    public function cancelPendingClientEvidenceForOpenWaChat(array $data, string $reason = 'user_cancelled'): ?YapeClientEvidence
    {
        $pending = $this->pendingClientEvidenceForOpenWaChat($data);

        if (!$pending) {
            return null;
        }

        $payload = is_array($pending->payload) ? $pending->payload : [];
        $payload['yape_chat_state'] = array_merge(
            is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [],
            [
                'state' => 'cancelled',
                'awaiting_dni' => false,
                'cancelled_at' => now()->toDateTimeString(),
                'cancel_reason' => $reason,
            ]
        );

        $pending->update([
            'status' => 'cancelled',
            'payload' => $payload,
        ]);

        return $pending->fresh(['intent']) ?: $pending;
    }

    protected function clientEvidenceIsAwaitingDni(YapeClientEvidence $evidence): bool
    {
        if ($evidence->status === 'cancelled' || $evidence->status === 'duplicate' || $evidence->status === 'manual_review') {
            return false;
        }

        $payload = is_array($evidence->payload) ? $evidence->payload : [];

        if (!empty(data_get($payload, 'dni_resolution.resolved_at'))) {
            return false;
        }

        if (($payload['yape_chat_state']['state'] ?? null) === 'cancelled') {
            return false;
        }

        if (($payload['yape_chat_state']['state'] ?? null) === 'awaiting_service_selection') {
            return $this->clientEvidenceHasDniResolutionFingerprint($evidence)
                && empty($evidence->yape_payment_intent_id);
        }

        return $this->clientEvidenceHasDniResolutionFingerprint($evidence)
            && empty($evidence->yape_payment_intent_id);
    }

    protected function hasMultiplePendingEvidencesForDniResolution(array $data): bool
    {
        $chatKeys = $this->chatKeysFromOpenWaData($data);

        if (empty($chatKeys)) {
            return false;
        }

        $evidences = YapeClientEvidence::query()
            ->where('created_at', '>=', now()->subHours(2))
            ->whereIn('status', ['parsed', 'incomplete', 'ambiguous', 'attached'])
            ->whereNotNull('parsed_amount')
            ->where(function ($query) use ($chatKeys) {
                foreach ($chatKeys as $chatKey) {
                    $query->orWhere('whatsapp_phone', $chatKey)
                        ->orWhere('source_chat_name', $chatKey);
                }
            })
            ->latest()
            ->get(['id', 'parsed_amount', 'operation_code', 'security_code']);

        if ($evidences->count() < 2) {
            return false;
        }

        return $evidences
            ->groupBy(fn (YapeClientEvidence $evidence) => number_format((float) $evidence->parsed_amount, 2, '.', ''))
            ->contains(function ($group) {
                if ($group->count() < 2) {
                    return false;
                }

                $fingerprints = $group
                    ->map(fn (YapeClientEvidence $evidence) => $evidence->security_code ?: $evidence->operation_code)
                    ->filter()
                    ->unique()
                    ->values();

                return $fingerprints->count() > 1;
            });
    }

    protected function chatKeysFromOpenWaData(array $data): array
    {
        return array_values(array_unique(array_filter([
            $data['openwa_chat_id'] ?? null,
            $this->normalizePhone((string) ($data['whatsapp_phone'] ?? '')),
            $data['source_chat_name'] ?? null,
            $this->extractChatIdFromPayload($data['payload'] ?? []),
        ], fn ($value) => is_string($value) && trim($value) !== '')));
    }

    protected function findIntentForCustomerEvidence(Customer $customer, YapeClientEvidence $evidence): ?YapePaymentIntent
    {
        $amount = round((float) $evidence->parsed_amount, 2);

        $intentQuery = YapePaymentIntent::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [YapePaymentIntent::STATUS_VALIDATED, YapePaymentIntent::STATUS_CANCELLED])
            ->whereNull('client_evidence_id');

        if ($amount > 0) {
            $intentQuery->where('expected_amount', $amount);
        }

        if ($evidence->security_code) {
            $intentQuery->where(function ($q) use ($evidence) {
                $q->whereNull('security_code')
                    ->orWhere('security_code', $evidence->security_code);
            });
        }

        if ($evidence->operation_code) {
            $intentQuery->where(function ($q) use ($evidence) {
                $q->whereNull('operation_code')
                    ->orWhere('operation_code', $evidence->operation_code);
            });
        }

        $candidates = $intentQuery->oldest()->get();

        // Fallback: If no candidate but amount is null or zero, check if customer has exactly one open intent
        if ($candidates->isEmpty() && $amount <= 0) {
            $fallbackQuery = YapePaymentIntent::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('status', [YapePaymentIntent::STATUS_VALIDATED, YapePaymentIntent::STATUS_CANCELLED])
                ->whereNull('client_evidence_id');

            $allIntents = $fallbackQuery->get();
            if ($allIntents->count() === 1) {
                $intent = $allIntents->first();
                $evidence->update(['parsed_amount' => $intent->expected_amount]);
                return $intent;
            }
        }

        $resolved = $this->resolveSingleIntentCandidate($candidates, $evidence);

        if ($resolved && $amount <= 0) {
            $evidence->update(['parsed_amount' => $resolved->expected_amount]);
        }

        return $resolved;
    }

    protected function createIntentFromPendingInvoiceForCustomerEvidence(Customer $customer, YapeClientEvidence $evidence): ?YapePaymentIntent
    {
        $amount = round((float) $evidence->parsed_amount, 2);

        $invoicesQuery = Invoice::query()
            ->with(['contract.customer', 'payments', 'creditNotes'])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('contract', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->orderBy('due_date')
            ->orderBy('id');

        $allPendingInvoices = $invoicesQuery->get();

        if ($amount > 0) {
            $invoices = $allPendingInvoices->filter(function (Invoice $invoice) use ($amount) {
                return round((float) $invoice->pending_amount, 2) === $amount;
            })->values();
        } else {
            // Fallback: If amount is null/zero but customer has exactly one pending invoice, use it
            $invoices = $allPendingInvoices;
        }

        if ($invoices->count() !== 1) {
            if ($invoices->count() > 1) {
                $evidence->update(['status' => 'ambiguous']);
            }

            return null;
        }

        $invoice = $invoices->first();
        $targetAmount = $amount > 0 ? $amount : round((float) $invoice->pending_amount, 2);

        if ($amount <= 0) {
            $evidence->update(['parsed_amount' => $targetAmount]);
        }

        return $this->createIntent([
            'invoice_id' => $invoice->id,
            'contract_id' => $invoice->contract_id,
            'client_evidence_id' => $evidence->id,
            'source_message_id' => $evidence->source_message_id,
            'source_chat_name' => $evidence->source_chat_name,
            'openwa_chat_id' => $evidence->source_chat_name,
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
            'amount' => $targetAmount,
            'payment_mode' => 'single',
            'payment_date' => optional($evidence->occurred_at)->toDateString() ?: now()->toDateString(),
            'notes' => 'Intent Yape creado automaticamente al asociar captura por DNI.',
        ], $invoice, $invoice->contract);
    }

    public function resolvePendingServiceSelectionForOpenWa(array $data, string $selectionText): array
    {
        $evidence = $this->pendingClientEvidenceForOpenWaChat($data);
        $chatId = (string) ($data['openwa_chat_id'] ?? $data['source_chat_name'] ?? '');
        $normalizedSelection = trim($selectionText);

        $this->appendYapeDirectDebug('yape_invoice_selection_received', [
            'chat_id' => $chatId,
            'evidence_id' => $evidence?->id,
            'selection_text' => $selectionText,
            'normalized_selection' => $normalizedSelection,
        ]);

        if (!$evidence) {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_EVIDENCE_NOT_FOUND);
        }

        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $state = is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [];

        if (($state['state'] ?? null) !== 'awaiting_service_selection') {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_SERVICE_SELECTION_INVALID, $evidence);
        }

        if (!preg_match('/^\d+$/', $normalizedSelection)) {
            $this->appendYapeDirectDebug('yape_invoice_selection_invalid', [
                'chat_id' => $chatId,
                'evidence_id' => $evidence->id,
                'selection_text' => $selectionText,
                'reason' => 'not_numeric',
            ]);

            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_SERVICE_SELECTION_INVALID, $evidence);
        }

        $optionNumber = (int) $normalizedSelection;
        $options = is_array($state['options'] ?? null) ? $state['options'] : [];
        $selectedOption = collect($options)->first(function ($option) use ($optionNumber) {
            return is_array($option) && (int) ($option['option'] ?? 0) === $optionNumber;
        });

        if (!$selectedOption) {
            $this->appendYapeDirectDebug('yape_invoice_selection_invalid', [
                'chat_id' => $chatId,
                'evidence_id' => $evidence->id,
                'selection_text' => $selectionText,
                'option' => $optionNumber,
                'available_options' => collect($options)->pluck('option')->values()->all(),
                'reason' => 'option_not_found',
            ]);

            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_SERVICE_SELECTION_INVALID, $evidence);
        }

        $optionInvoiceIds = collect($selectedOption['invoice_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($optionInvoiceIds->isEmpty() && !empty($selectedOption['invoice_id'])) {
            $optionInvoiceIds = collect([(int) $selectedOption['invoice_id']]);
        }

        if (($selectedOption['payment_mode'] ?? null) === 'credit_only') {
            $contract = Contract::with(['customer.addresses', 'plan'])
                ->find((int) ($selectedOption['contract_id'] ?? 0));

            if (!$contract || !$contract->customer) {
                $this->appendYapeDirectDebug('yape_invoice_selection_invalid', [
                    'chat_id' => $chatId,
                    'evidence_id' => $evidence->id,
                    'option' => $optionNumber,
                    'contract_id' => $selectedOption['contract_id'] ?? null,
                    'reason' => 'credit_contract_not_found',
                ]);

                return $this->dniResolutionResult(false, self::DNI_RESOLUTION_SERVICE_SELECTION_INVALID, $evidence);
            }

            $intent = $this->createCreditOnlyIntentForContractEvidence($contract, $evidence, 'manual_service_selection_credit_only');

            if (!$intent) {
                return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence->fresh(['intent']));
            }

            return $this->markDniResolutionSuccess(
                $this->attachClientEvidenceToIntent($evidence, $intent),
                (string) ($state['dni'] ?? '')
            );
        }

        $selectedInvoices = Invoice::with(['contract.customer.addresses', 'contract.plan', 'payments', 'creditNotes'])
            ->whereIn('id', $optionInvoiceIds)
            ->get()
            ->sortBy(fn (Invoice $invoice) => $this->invoiceApplicationSortKey($invoice))
            ->values();

        $invoice = $selectedInvoices->first();

        if ($selectedInvoices->isEmpty() || $selectedInvoices->count() !== $optionInvoiceIds->count()) {
            $this->appendYapeDirectDebug('yape_invoice_selection_invalid', [
                'chat_id' => $chatId,
                'evidence_id' => $evidence->id,
                'option' => $optionNumber,
                'invoice_id' => $selectedOption['invoice_id'] ?? null,
                'invoice_ids' => $optionInvoiceIds->all(),
                'reason' => 'invoice_not_found',
            ]);

            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence);
        }

        foreach ($selectedInvoices as $selectedInvoice) {
            if (!in_array($selectedInvoice->status, ['pending', 'partial', 'overdue'], true)) {
                $this->appendYapeDirectDebug('yape_invoice_selection_invalid', [
                    'chat_id' => $chatId,
                    'evidence_id' => $evidence->id,
                    'option' => $optionNumber,
                    'invoice_id' => $selectedInvoice->id,
                    'reason' => 'invoice_not_pending',
                ]);

                return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence);
            }
        }

        $selectedTotal = round((float) $selectedInvoices->sum(fn (Invoice $selectedInvoice) => round((float) $selectedInvoice->pending_amount, 2)), 2);
        $allowsOverpayment = ($selectedOption['payment_mode'] ?? null) === 'overpayment_with_credit';

        if (
            (!$allowsOverpayment && !$this->amountsMatch((float) $evidence->parsed_amount, $selectedTotal))
            || ($allowsOverpayment && (float) $evidence->parsed_amount + 0.009 < $selectedTotal)
        ) {
            $this->appendYapeDirectDebug('yape_invoice_selection_invalid', [
                'chat_id' => $chatId,
                'evidence_id' => $evidence->id,
                'option' => $optionNumber,
                'invoice_ids' => $selectedInvoices->pluck('id')->values()->all(),
                'ocr_amount' => (float) $evidence->parsed_amount,
                'selected_pending_amount' => $selectedTotal,
                'reason' => 'amount_mismatch',
            ]);

            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_AMOUNT_MISMATCH, $evidence);
        }

        $this->appendYapeDirectDebug('yape_invoice_selection_selected', [
            'chat_id' => $chatId,
            'dni' => $state['dni'] ?? null,
            'evidence_id' => $evidence->id,
            'ocr_amount' => (float) $evidence->parsed_amount,
            'selected_customer_id' => $invoice->contract?->customer_id,
            'selected_contract_id' => $invoice->contract_id,
            'selected_invoice_id' => $invoice->id,
            'selected_invoice_ids' => $selectedInvoices->pluck('id')->values()->all(),
            'option' => $optionNumber,
            'decision' => 'manual_service_selection',
        ]);

        $intent = $this->createIntentFromInvoicesForCustomerEvidence($selectedInvoices, $evidence, 'manual_service_selection');

        if (!$intent) {
            return $this->dniResolutionResult(false, self::DNI_RESOLUTION_NO_PENDING_PAYMENT, $evidence->fresh(['intent']));
        }

        $this->appendYapeDirectDebug('yape_invoice_selection_validation_started', [
            'chat_id' => $chatId,
            'evidence_id' => $evidence->id,
            'intent_id' => $intent->id,
            'selected_customer_id' => $intent->customer_id,
            'selected_contract_id' => $intent->contract_id,
            'selected_invoice_id' => $intent->invoice_id,
            'decision' => 'manual_service_selection',
        ]);

        return $this->markDniResolutionSuccess(
            $this->attachClientEvidenceToIntent($evidence, $intent),
            (string) ($state['dni'] ?? '')
        );
    }

    protected function createIntentFromInvoiceForCustomerEvidence(
        Invoice $invoice,
        YapeClientEvidence $evidence,
        string $decision
    ): ?YapePaymentIntent {
        return $this->createIntentFromInvoicesForCustomerEvidence([$invoice], $evidence, $decision);
    }

    protected function createIntentFromInvoicesForCustomerEvidence(
        iterable $invoices,
        YapeClientEvidence $evidence,
        string $decision
    ): ?YapePaymentIntent {
        $invoices = collect($invoices)->filter()->values();

        if ($invoices->isEmpty()) {
            return null;
        }

        $invoices->each->loadMissing(['contract.customer', 'payments', 'creditNotes']);

        $contractIds = $invoices->pluck('contract_id')->unique()->values();

        if ($contractIds->count() !== 1) {
            return null;
        }

        foreach ($invoices as $invoice) {
            if (!in_array($invoice->status, ['pending', 'partial', 'overdue'], true)) {
                return null;
            }
        }

        $firstInvoice = $invoices->first();
        $amount = round((float) $evidence->parsed_amount, 2);
        $targetAmount = $amount > 0
            ? $amount
            : round((float) $invoices->sum(fn (Invoice $invoice) => round((float) $invoice->pending_amount, 2)), 2);

        if ($targetAmount <= 0) {
            return null;
        }

        if ($amount <= 0) {
            $evidence->update(['parsed_amount' => $targetAmount]);
        }

        $appliedAmount = round((float) $invoices->sum(fn (Invoice $invoice) => round((float) $invoice->pending_amount, 2)), 2);
        $creditAmount = round(max($targetAmount - $appliedAmount, 0), 2);
        $paymentMode = $creditAmount > 0.009
            ? 'overpayment_with_credit'
            : ($invoices->count() > 1 ? 'multi_month' : 'single');
        $appliedInvoices = $this->appliedInvoicePayload($invoices);

        $this->appendYapeDirectDebug($paymentMode === 'overpayment_with_credit' ? 'yape_overpayment_intent_created' : ($paymentMode === 'multi_month' ? 'yape_multi_invoice_intent_created' : 'yape_single_invoice_match_found'), [
            'evidence_id' => $evidence->id,
            'ocr_amount' => $targetAmount,
            'customer_id' => $firstInvoice->contract?->customer_id,
            'contract_id' => $firstInvoice->contract_id,
            'pending_invoices_count' => $invoices->count(),
            'matched_invoice_ids' => $invoices->pluck('id')->values()->all(),
            'expected_amount' => $targetAmount,
            'payment_mode' => $paymentMode,
            'selection_strategy' => $decision,
            'original_yape_amount' => $targetAmount,
            'applied_amount' => $appliedAmount,
            'credit_amount' => $creditAmount,
            'decision' => $paymentMode === 'overpayment_with_credit'
                ? 'overpayment_with_credit'
                : ($paymentMode === 'multi_month' ? 'auto_selected_multi_month' : 'auto_selected_single_invoice'),
        ]);

        return $this->createIntent([
            'amount' => $targetAmount,
            'invoice_id' => $firstInvoice->id,
            'contract_id' => $firstInvoice->contract_id,
            'client_evidence_id' => $evidence->id,
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
            'source_message_id' => $evidence->source_message_id,
            'source_chat_name' => $evidence->source_chat_name,
            'payment_mode' => $paymentMode,
            'months_to_pay' => $invoices->count(),
            'applied_invoice_ids' => $invoices->pluck('id')->values()->all(),
            'applied_invoices' => $appliedInvoices,
            'selection_strategy' => $decision,
            'original_yape_amount' => $targetAmount,
            'applied_amount' => $appliedAmount,
            'credit_amount' => $creditAmount,
            'payment_date' => optional($evidence->occurred_at)->toDateString() ?: now()->toDateString(),
            'notes' => 'Intent Yape creado automáticamente al asociar captura por DNI. Decisión: ' . $decision . '.',
        ], $firstInvoice, $firstInvoice->contract);
    }

    protected function createCreditOnlyIntentForContractEvidence(
        Contract $contract,
        YapeClientEvidence $evidence,
        string $decision
    ): ?YapePaymentIntent {
        $contract->loadMissing(['customer', 'plan']);
        $amount = round((float) $evidence->parsed_amount, 2);

        if ($amount <= 0 || !$contract->customer) {
            return null;
        }

        $this->appendYapeDirectDebug('yape_credit_only_intent_created', [
            'evidence_id' => $evidence->id,
            'ocr_amount' => $amount,
            'customer_id' => $contract->customer_id,
            'contract_id' => $contract->id,
            'payment_mode' => 'credit_only',
            'selection_strategy' => $decision,
            'original_yape_amount' => $amount,
            'applied_amount' => 0,
            'credit_amount' => $amount,
            'decision' => 'credit_only',
        ]);

        return $this->createIntent([
            'amount' => $amount,
            'invoice_id' => null,
            'contract_id' => $contract->id,
            'client_evidence_id' => $evidence->id,
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
            'source_message_id' => $evidence->source_message_id,
            'source_chat_name' => $evidence->source_chat_name,
            'payment_mode' => 'credit_only',
            'months_to_pay' => 0,
            'applied_invoice_ids' => [],
            'applied_invoices' => [],
            'selection_strategy' => $decision,
            'original_yape_amount' => $amount,
            'applied_amount' => 0,
            'credit_amount' => $amount,
            'payment_date' => optional($evidence->occurred_at)->toDateString() ?: now()->toDateString(),
            'notes' => 'Intent Yape de saldo a favor creado automáticamente al asociar captura por DNI. Decisión: ' . $decision . '.',
        ], null, $contract);
    }

    protected function candidateCustomersForDni(string $dni)
    {
        return Customer::query()
            ->where('identity_document', $dni)
            ->orderBy('id')
            ->get();
    }

    protected function candidateContractsForCustomers($customers)
    {
        $customerIds = collect($customers)->pluck('id')->filter()->values();

        if ($customerIds->isEmpty()) {
            return collect();
        }

        return Contract::query()
            ->with(['customer.addresses', 'plan'])
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'active')
            ->orderBy('customer_id')
            ->orderBy('id')
            ->get();
    }

    protected function customersHavePendingDebt($customers): bool
    {
        $customerIds = collect($customers)->pluck('id')->filter()->values();

        if ($customerIds->isEmpty()) {
            return false;
        }

        return Invoice::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('contract', function ($query) use ($customerIds) {
                $query->whereIn('customer_id', $customerIds);
            })
            ->exists();
    }

    protected function pendingInvoicesForCustomers($customers)
    {
        $customerIds = collect($customers)->pluck('id')->filter()->values();

        if ($customerIds->isEmpty()) {
            return collect();
        }

        return Invoice::query()
            ->with(['contract.customer.addresses', 'contract.plan', 'payments', 'creditNotes'])
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('contract', function ($query) use ($customerIds) {
                $query->whereIn('customer_id', $customerIds);
            })
            ->orderBy('due_date')
            ->orderBy('issue_date')
            ->orderBy('billing_period')
            ->orderBy('id')
            ->get();
    }

    protected function decideInvoiceForDniEvidence($candidateCustomers, YapeClientEvidence $evidence, string $dni, string $chatId): array
    {
        $pendingInvoices = $this->pendingInvoicesForCustomers($candidateCustomers);
        $ocrAmount = round((float) $evidence->parsed_amount, 2);
        $matchedByAmount = $ocrAmount > 0
            ? $pendingInvoices->filter(fn (Invoice $invoice) => $this->amountsMatch($ocrAmount, (float) $invoice->pending_amount))->values()
            : collect();

        $this->appendYapeDirectDebug('yape_dni_candidates_found', [
            'chat_id' => $chatId,
            'dni' => $dni,
            'evidence_id' => $evidence->id,
            'ocr_amount' => $ocrAmount,
            'candidate_customers_count' => $candidateCustomers->count(),
            'candidate_customer_ids' => $candidateCustomers->pluck('id')->values()->all(),
        ]);

        $this->appendYapeDirectDebug('yape_pending_invoices_found', [
            'chat_id' => $chatId,
            'dni' => $dni,
            'evidence_id' => $evidence->id,
            'ocr_amount' => $ocrAmount,
            'candidate_customers_count' => $candidateCustomers->count(),
            'pending_invoices_count' => $pendingInvoices->count(),
            'matched_by_amount_count' => $matchedByAmount->count(),
            'pending_invoice_ids' => $pendingInvoices->pluck('id')->values()->all(),
        ]);

        $this->appendYapeDirectDebug('yape_multi_month_check_started', [
            'chat_id' => $chatId,
            'dni' => $dni,
            'evidence_id' => $evidence->id,
            'ocr_amount' => $ocrAmount,
            'candidate_customers_count' => $candidateCustomers->count(),
            'pending_invoices_count' => $pendingInvoices->count(),
        ]);

        if ($ocrAmount <= 0) {
            return ['decision' => 'amount_mismatch'];
        }

        if ($pendingInvoices->isEmpty()) {
            $candidateContracts = $this->candidateContractsForCustomers($candidateCustomers);

            if ($candidateContracts->count() === 1) {
                $contract = $candidateContracts->first();

                $this->appendYapeDirectDebug('yape_credit_only_unique_contract_found', [
                    'chat_id' => $chatId,
                    'dni' => $dni,
                    'evidence_id' => $evidence->id,
                    'ocr_amount' => $ocrAmount,
                    'customer_id' => $contract?->customer_id,
                    'contract_id' => $contract?->id,
                    'decision' => 'credit_only',
                ]);

                return [
                    'decision' => 'credit_only',
                    'contract' => $contract,
                    'amount' => $ocrAmount,
                    'applied_amount' => 0,
                    'credit_amount' => $ocrAmount,
                ];
            }

            if ($candidateContracts->count() > 1) {
                $options = $this->contractSelectionOptions($candidateContracts, $ocrAmount, 'credit_only');

                $this->appendYapeDirectDebug('yape_credit_only_selection_required', [
                    'chat_id' => $chatId,
                    'dni' => $dni,
                    'evidence_id' => $evidence->id,
                    'ocr_amount' => $ocrAmount,
                    'contract_ids' => $candidateContracts->pluck('id')->values()->all(),
                    'decision' => 'selection_required',
                ]);

                return [
                    'decision' => 'selection_required',
                    'reason' => 'multiple_contracts_without_pending_debt',
                    'options' => $options,
                ];
            }

            return ['decision' => 'no_pending'];
        }

        // A canonical customer may own several contracts. When there is only
        // one customer for the DNI, the oldest pending invoices across all of
        // their services can safely be paid together if their exact sum
        // matches the Yape amount.
        if ($candidateCustomers->count() === 1) {
            $orderedCustomerInvoices = $pendingInvoices
                ->sortBy(fn (Invoice $invoice) => $this->invoiceApplicationSortKey($invoice))
                ->values();
            $customerOldestBlock = collect();
            $customerRunningTotal = 0.0;

            foreach ($orderedCustomerInvoices as $invoice) {
                $customerRunningTotal = round(
                    $customerRunningTotal + round((float) $invoice->pending_amount, 2),
                    2
                );
                $customerOldestBlock->push($invoice);

                if ($this->amountsMatch($customerRunningTotal, $ocrAmount)) {
                    break;
                }

                if ($customerRunningTotal > $ocrAmount) {
                    $customerOldestBlock = collect();
                    break;
                }
            }

            $customerBlockTotal = round((float) $customerOldestBlock
                ->sum(fn (Invoice $invoice) => round((float) $invoice->pending_amount, 2)), 2);
            $customerContractIds = $customerOldestBlock->pluck('contract_id')->unique()->values();

            if (
                $customerOldestBlock->count() > 1
                && $customerContractIds->count() > 1
                && $this->amountsMatch($customerBlockTotal, $ocrAmount)
            ) {
                $this->appendYapeDirectDebug('yape_customer_multi_service_sum_match_found', [
                    'chat_id' => $chatId,
                    'dni' => $dni,
                    'evidence_id' => $evidence->id,
                    'customer_id' => $candidateCustomers->first()?->id,
                    'ocr_amount' => $ocrAmount,
                    'contract_ids' => $customerContractIds->all(),
                    'invoice_ids' => $customerOldestBlock->pluck('id')->values()->all(),
                    'decision' => 'oldest_invoices_sum_match',
                ]);

                return [
                    'decision' => 'oldest_invoices_sum_match',
                    'invoice' => $customerOldestBlock->first(),
                    'invoices' => $customerOldestBlock->values(),
                ];
            }
        }

        $contractDecisions = collect();

        foreach ($pendingInvoices->groupBy('contract_id') as $contractId => $contractInvoices) {
            $ordered = $contractInvoices
                ->sortBy(fn (Invoice $invoice) => $this->invoiceApplicationSortKey($invoice))
                ->values();
            $totalDebt = round((float) $ordered->sum(fn (Invoice $invoice) => round((float) $invoice->pending_amount, 2)), 2);
            $oldestBlock = collect();
            $runningTotal = 0.0;

            foreach ($ordered as $invoice) {
                $runningTotal = round($runningTotal + round((float) $invoice->pending_amount, 2), 2);
                $oldestBlock->push($invoice);

                if ($this->amountsMatch($runningTotal, $ocrAmount)) {
                    break;
                }

                if ($runningTotal > $ocrAmount) {
                    $oldestBlock = collect();
                    break;
                }
            }

            $oldestMatch = $oldestBlock->isNotEmpty()
                && $this->amountsMatch(
                    (float) $oldestBlock->sum(fn (Invoice $invoice) => round((float) $invoice->pending_amount, 2)),
                    $ocrAmount
                );
            $singleMatches = $ordered->filter(fn (Invoice $invoice) => $this->amountsMatch($ocrAmount, (float) $invoice->pending_amount))->values();

            $this->appendYapeDirectDebug('yape_pending_invoices_loaded', [
                'chat_id' => $chatId,
                'dni' => $dni,
                'evidence_id' => $evidence->id,
                'ocr_amount' => $ocrAmount,
                'contract_id' => (int) $contractId,
                'pending_invoices_count' => $ordered->count(),
                'pending_invoices' => $this->appliedInvoicePayload($ordered),
                'oldest_block_invoice_ids' => $oldestMatch ? $oldestBlock->pluck('id')->values()->all() : [],
                'single_match_invoice_ids' => $singleMatches->pluck('id')->values()->all(),
                'total_debt' => $totalDebt,
            ]);

            if ($ocrAmount > $totalDebt && $totalDebt > 0) {
                $creditAmount = round($ocrAmount - $totalDebt, 2);

                $this->appendYapeDirectDebug('yape_overpayment_detected', [
                    'chat_id' => $chatId,
                    'dni' => $dni,
                    'evidence_id' => $evidence->id,
                    'ocr_amount' => $ocrAmount,
                    'contract_id' => (int) $contractId,
                    'customer_id' => $ordered->first()?->contract?->customer_id,
                    'original_yape_amount' => $ocrAmount,
                    'applied_amount' => $totalDebt,
                    'credit_amount' => $creditAmount,
                    'applied_invoice_ids' => $ordered->pluck('id')->values()->all(),
                    'decision' => 'overpayment_with_credit',
                ]);

                $contractDecisions->push([
                    'decision' => 'overpayment_with_credit',
                    'contract_id' => (int) $contractId,
                    'invoices' => $ordered,
                    'invoice_ids' => $ordered->pluck('id')->values()->all(),
                    'amount' => $ocrAmount,
                    'applied_amount' => $totalDebt,
                    'credit_amount' => $creditAmount,
                ]);
                continue;
            }

            if ($oldestMatch) {
                $contractDecisions->push([
                    'decision' => $oldestBlock->count() > 1 ? 'oldest_invoices_sum_match' : 'auto_selected_single_pending',
                    'contract_id' => (int) $contractId,
                    'invoices' => $oldestBlock,
                    'invoice_ids' => $oldestBlock->pluck('id')->values()->all(),
                    'amount' => $ocrAmount,
                ]);
                continue;
            }

            $this->appendYapeDirectDebug('yape_oldest_first_amount_mismatch', [
                'chat_id' => $chatId,
                'dni' => $dni,
                'evidence_id' => $evidence->id,
                'ocr_amount' => $ocrAmount,
                'contract_id' => (int) $contractId,
                'oldest_invoice_id' => $ordered->first()?->id,
                'single_match_invoice_ids_ignored' => $singleMatches->pluck('id')->values()->all(),
                'reason' => 'amount_does_not_match_cumulative_oldest_pending_invoices',
                'decision' => 'amount_mismatch',
            ]);
        }

        $safeDecisions = $contractDecisions
            ->filter(fn (array $decision) => ($decision['decision'] ?? null) !== 'ambiguous')
            ->values();
        $ambiguousDecisions = $contractDecisions
            ->filter(fn (array $decision) => ($decision['decision'] ?? null) === 'ambiguous')
            ->values();

        $selectableDecisionOptions = $this->invoiceDecisionSelectionOptions($safeDecisions);

        if ($ambiguousDecisions->isEmpty() && $safeDecisions->count() > 1 && count($selectableDecisionOptions) === $safeDecisions->count()) {
            $this->appendYapeDirectDebug('yape_multi_contract_selection_required', [
                'chat_id' => $chatId,
                'dni' => $dni,
                'evidence_id' => $evidence->id,
                'ocr_amount' => $ocrAmount,
                'candidate_decisions' => $contractDecisions->map(function (array $decision) {
                    return collect($decision)->except('invoices')->all();
                })->values()->all(),
                'decision' => 'selection_required',
            ]);

            return [
                'decision' => 'selection_required',
                'reason' => 'multiple_contract_payment_matches',
                'options' => $selectableDecisionOptions,
            ];
        }

        $selectableSingleInvoiceDecisions = $safeDecisions
            ->filter(function (array $decision) {
                return ($decision['decision'] ?? null) === 'auto_selected_single_pending'
                    && collect($decision['invoices'] ?? [])->count() === 1;
            })
            ->values();

        if (
            $ambiguousDecisions->isEmpty()
            && $safeDecisions->count() > 1
            && $selectableSingleInvoiceDecisions->count() === $safeDecisions->count()
        ) {
            $selectableInvoices = $selectableSingleInvoiceDecisions
                ->map(fn (array $decision) => collect($decision['invoices'] ?? [])->first())
                ->filter()
                ->values();

            if ($selectableInvoices->count() === $selectableSingleInvoiceDecisions->count()) {
                $options = $this->invoiceSelectionOptions($selectableInvoices);

                $this->appendYapeDirectDebug('yape_multi_invoice_selection_required', [
                    'chat_id' => $chatId,
                    'dni' => $dni,
                    'evidence_id' => $evidence->id,
                    'ocr_amount' => $ocrAmount,
                    'candidate_decisions' => $contractDecisions->map(function (array $decision) {
                        return collect($decision)->except('invoices')->all();
                    })->values()->all(),
                    'option_invoice_ids' => $selectableInvoices->pluck('id')->values()->all(),
                    'decision' => 'selection_required',
                ]);

                return [
                    'decision' => 'selection_required',
                    'reason' => 'multiple_single_invoice_amount_matches',
                    'options' => $options,
                ];
            }
        }

        if ($ambiguousDecisions->isNotEmpty() || $safeDecisions->count() > 1) {
            $this->appendYapeDirectDebug('yape_multi_invoice_ambiguous_match', [
                'chat_id' => $chatId,
                'dni' => $dni,
                'evidence_id' => $evidence->id,
                'ocr_amount' => $ocrAmount,
                'candidate_decisions' => $contractDecisions->map(function (array $decision) {
                    return collect($decision)->except('invoices')->all();
                })->values()->all(),
                'decision' => 'ambiguous_payment',
            ]);

            return [
                'decision' => 'ambiguous',
                'candidate_decisions' => $contractDecisions->map(function (array $decision) {
                    return collect($decision)->except('invoices')->all();
                })->values()->all(),
            ];
        }

        if ($safeDecisions->count() === 1) {
            $decision = $safeDecisions->first();
            $invoices = collect($decision['invoices'] ?? [])->values();
            $firstInvoice = $invoices->first();
            $event = $invoices->count() > 1
                ? 'yape_oldest_invoices_sum_match_found'
                : 'yape_single_invoice_match_found';

            $this->appendYapeDirectDebug($event, [
                'chat_id' => $chatId,
                'dni' => $dni,
                'evidence_id' => $evidence->id,
                'ocr_amount' => $ocrAmount,
                'candidate_customers_count' => $candidateCustomers->count(),
                'pending_invoices_count' => $pendingInvoices->count(),
                'matched_by_amount_count' => $matchedByAmount->count(),
                'selected_customer_id' => $firstInvoice?->contract?->customer_id,
                'selected_contract_id' => $firstInvoice?->contract_id,
                'selected_invoice_id' => $firstInvoice?->id,
                'matched_invoice_ids' => $invoices->pluck('id')->values()->all(),
                'expected_amount' => $ocrAmount,
                'payment_mode' => $invoices->count() > 1 ? 'multi_month' : 'single',
                'selection_strategy' => $decision['decision'],
                'decision' => $invoices->count() > 1 ? 'auto_selected_multi_month' : 'auto_selected_single_invoice',
            ]);

            return [
                'decision' => $decision['decision'],
                'invoice' => $firstInvoice,
                'invoices' => $invoices,
            ];
        }

        $this->appendYapeDirectDebug('yape_multi_invoice_no_match', [
            'chat_id' => $chatId,
            'dni' => $dni,
            'evidence_id' => $evidence->id,
            'ocr_amount' => $ocrAmount,
            'pending_invoices_count' => $pendingInvoices->count(),
            'pending_invoice_ids' => $pendingInvoices->pluck('id')->values()->all(),
            'decision' => 'amount_mismatch_or_no_safe_combination',
        ]);

        return ['decision' => 'amount_mismatch'];
    }

    protected function invoiceSelectionOptions($invoices): array
    {
        return collect($invoices)->values()->map(function (Invoice $invoice, int $index) {
            $labelData = $this->invoiceSelectionLabelData($invoice);

            return [
                'option' => $index + 1,
                'customer_id' => $invoice->contract?->customer_id,
                'contract_id' => $invoice->contract_id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => round((float) $invoice->pending_amount, 2),
            ] + $labelData;
        })->all();
    }

    protected function invoiceDecisionSelectionOptions($decisions): array
    {
        return collect($decisions)->values()->map(function (array $decision, int $index) {
            $invoices = collect($decision['invoices'] ?? [])->filter()->values();
            $firstInvoice = $invoices->first();

            if (!$firstInvoice instanceof Invoice || $invoices->isEmpty()) {
                return null;
            }

            $labelData = $this->invoiceSelectionLabelData($firstInvoice);
            $appliedAmount = round((float) ($decision['applied_amount'] ?? $invoices->sum(fn (Invoice $invoice) => round((float) $invoice->pending_amount, 2))), 2);
            $creditAmount = round((float) ($decision['credit_amount'] ?? 0), 2);
            $amount = round((float) ($decision['amount'] ?? ($appliedAmount + $creditAmount)), 2);

            return [
                'option' => $index + 1,
                'customer_id' => $firstInvoice->contract?->customer_id,
                'contract_id' => $firstInvoice->contract_id,
                'invoice_id' => $firstInvoice->id,
                'invoice_ids' => $invoices->pluck('id')->values()->all(),
                'invoice_number' => $firstInvoice->invoice_number,
                'amount' => $amount,
                'applied_amount' => $appliedAmount,
                'credit_amount' => $creditAmount,
                'payment_mode' => $creditAmount > 0.009
                    ? 'overpayment_with_credit'
                    : ($invoices->count() > 1 ? 'multi_month' : 'single'),
            ] + $labelData;
        })->filter()->values()->all();
    }

    protected function contractSelectionOptions($contracts, float $amount, string $paymentMode): array
    {
        return collect($contracts)->values()->map(function (Contract $contract, int $index) use ($amount, $paymentMode) {
            $labelData = $this->contractSelectionLabelData($contract, $amount);

            return [
                'option' => $index + 1,
                'customer_id' => $contract->customer_id,
                'contract_id' => $contract->id,
                'invoice_id' => null,
                'invoice_ids' => [],
                'invoice_number' => null,
                'amount' => round($amount, 2),
                'applied_amount' => 0,
                'credit_amount' => round($amount, 2),
                'payment_mode' => $paymentMode,
            ] + $labelData;
        })->all();
    }

    protected function appliedInvoicePayload($invoices): array
    {
        return collect($invoices)->values()->map(function (Invoice $invoice) {
            $pendingAmount = round((float) $invoice->pending_amount, 2);

            return [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => $pendingAmount,
                'pending_amount' => $pendingAmount,
                'due_date' => optional($invoice->due_date)->toDateString(),
                'billing_period' => $invoice->billing_period,
            ];
        })->all();
    }

    protected function invoiceSelectionLabel(Invoice $invoice): string
    {
        return $this->invoiceSelectionLabelData($invoice)['label'];
    }

    protected function invoiceSelectionLabelData(Invoice $invoice): array
    {
        $invoice->loadMissing(['contract.customer.addresses', 'contract.plan']);

        $address = $this->yapeServiceAddress($invoice);
        $locationName = trim((string) ($address?->location_name ?? ''));
        $serviceAddress = trim((string) ($address?->short_address ?? ''));
        $reference = trim((string) ($address?->reference ?? ''));
        $planName = trim((string) ($invoice->contract?->plan?->name ?? 'Servicio de internet'));
        $amount = number_format((float) $invoice->pending_amount, 2);
        $fallbackContract = 'Contrato #' . $invoice->contract_id;

        $parts = collect([
            $locationName,
            $serviceAddress,
            $reference,
            $planName,
            $invoice->invoice_number,
            'S/' . $amount,
        ])->filter(fn ($part) => trim((string) $part) !== '')->values();

        if ($parts->isEmpty() || (!$locationName && !$serviceAddress && !$reference)) {
            $parts = collect([
                $fallbackContract,
                $planName,
                $invoice->invoice_number,
                'S/' . $amount,
            ]);
        }

        $label = $this->limitWhatsappOptionLabel($parts->implode(' - '));

        $this->appendYapeDirectDebug('yape_invoice_selection_option_label_built', [
            'contract_id' => $invoice->contract_id,
            'invoice_id' => $invoice->id,
            'has_location_name' => $locationName !== '',
            'has_address' => $serviceAddress !== '',
            'has_reference' => $reference !== '',
            'label' => $label,
        ]);

        return [
            'plan_name' => $planName,
            'location_name' => $locationName ?: null,
            'service_address' => $serviceAddress ?: null,
            'reference' => $reference ?: null,
            'label' => $label,
        ];
    }

    protected function contractSelectionLabelData(Contract $contract, float $amount): array
    {
        $contract->loadMissing(['customer.addresses', 'plan']);

        $addresses = $contract->customer?->addresses;
        $address = $addresses
            ? ($addresses->firstWhere('address_type', 'installation')
                ?: $addresses->firstWhere('is_primary', true)
                ?: $addresses->firstWhere('address_type', 'billing')
                ?: $addresses->first())
            : null;

        $locationName = trim((string) ($address?->location_name ?? ''));
        $serviceAddress = trim((string) ($address?->short_address ?? ''));
        $reference = trim((string) ($address?->reference ?? ''));
        $planName = trim((string) ($contract->plan?->name ?? 'Servicio de internet'));

        $parts = collect([
            $locationName,
            $serviceAddress,
            $reference,
            $planName,
            'Contrato #' . $contract->id,
            'S/' . number_format($amount, 2),
        ])->filter(fn ($part) => trim((string) $part) !== '')->values();

        return [
            'plan_name' => $planName,
            'location_name' => $locationName ?: null,
            'service_address' => $serviceAddress ?: null,
            'reference' => $reference ?: null,
            'label' => $this->limitWhatsappOptionLabel($parts->implode(' - ')),
        ];
    }

    protected function yapeServiceAddress(Invoice $invoice)
    {
        $addresses = $invoice->contract?->customer?->addresses;

        if (!$addresses) {
            return null;
        }

        return $addresses->firstWhere('address_type', 'installation')
            ?: $addresses->firstWhere('is_primary', true)
            ?: $addresses->firstWhere('address_type', 'billing')
            ?: $addresses->first();
    }

    protected function limitWhatsappOptionLabel(string $label, int $limit = 120): string
    {
        $label = trim(preg_replace('/\s+/', ' ', $label));

        if (mb_strlen($label) <= $limit) {
            return $label;
        }

        $truncated = mb_substr($label, 0, $limit - 1);
        $lastSpace = mb_strrpos($truncated, ' ');

        if ($lastSpace !== false && $lastSpace > 60) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, " -\t\n\r\0\x0B") . '…';
    }

    protected function invoiceApplicationSortKey(?Invoice $invoice): string
    {
        return sprintf(
            '%s|%s|%s|%010d',
            optional($invoice?->due_date)->format('Y-m-d') ?: '9999-12-31',
            optional($invoice?->issue_date)->format('Y-m-d') ?: '9999-12-31',
            (string) ($invoice?->billing_period ?? ''),
            (int) ($invoice?->id ?? 0)
        );
    }

    protected function serviceSelectionMessage(array $options): string
    {
        $lines = [
            'Encontramos más de un servicio asociado a tu DNI.',
            '',
            'Responde con el número del servicio que estás pagando:',
            '',
        ];

        foreach ($options as $option) {
            $lines[] = ((int) ($option['option'] ?? 0)) . '. ' . ($option['label'] ?? 'Servicio pendiente');
        }

        return implode("\n", $lines);
    }

    protected function amountsMatch(float $expected, float $actual): bool
    {
        return abs(round($expected, 2) - round($actual, 2)) < 0.01;
    }

    protected function attachClientEvidenceToIntent(YapeClientEvidence $evidence, YapePaymentIntent $intent): YapeClientEvidence
    {
        $intent->loadMissing('clientEvidence');

        if ($intent->client_evidence_id && (int) $intent->client_evidence_id !== (int) $evidence->id) {
            $linkedMatch = $this->clientEvidenceMatchesIntent($intent, $intent->clientEvidence);
            $incomingMatch = $this->clientEvidenceMatchesIntent($intent, $evidence);

            if (!$incomingMatch['matched']) {
                $this->appendYapeDirectDebug('yape_validation_wrong_evidence_prevented', array_merge(
                    $this->intentEvidenceLogContext($intent, $intent->clientEvidence, $evidence->id, 'attach_client_evidence', 'prevent_attach_different_mismatched_evidence'),
                    [
                        'incoming_evidence_id' => $evidence->id,
                        'incoming_source_message_id' => $evidence->source_message_id,
                        'incoming_operation_code' => $evidence->operation_code,
                        'incoming_security_code' => $evidence->security_code,
                        'incoming_amount' => $evidence->parsed_amount,
                        'mismatch_errors' => $incomingMatch['errors'],
                    ]
                ));

                $intent->update([
                    'status' => YapePaymentIntent::STATUS_OBSERVED,
                    'observed_reason' => 'La evidencia WhatsApp no coincide con la validación Yape esperada.',
                ]);

                return $evidence->fresh(['intent']);
            }

            if ($linkedMatch['matched']) {
                $this->appendYapeDirectDebug('yape_intent_evidence_linked', $this->intentEvidenceLogContext(
                    $intent,
                    $intent->clientEvidence,
                    $intent->client_evidence_id,
                    'direct_relation_already_present',
                    'kept_existing_matching_client_evidence'
                ));

                return $intent->clientEvidence->fresh(['intent']);
            }

            $this->appendYapeDirectDebug('yape_intent_evidence_mismatch', array_merge(
                $this->intentEvidenceLogContext($intent, $intent->clientEvidence, $evidence->id, 'attach_client_evidence', 'replacing_previous_mismatched_client_evidence'),
                [
                    'incoming_evidence_id' => $evidence->id,
                    'incoming_source_message_id' => $evidence->source_message_id,
                    'incoming_operation_code' => $evidence->operation_code,
                    'incoming_security_code' => $evidence->security_code,
                    'incoming_amount' => $evidence->parsed_amount,
                    'linked_match_errors' => $linkedMatch['errors'],
                ]
            ));
        }

        $evidence->update([
            'yape_payment_intent_id' => $intent->id,
            'customer_id' => $intent->customer_id,
            'status' => 'attached',
        ]);

        $intent->update([
            'client_evidence_id' => $evidence->id,
            'operation_code' => $evidence->operation_code ?: $intent->operation_code,
            'security_code' => $evidence->security_code ?: $intent->security_code,
            'status' => $intent->receiver_event_id
                ? $intent->status
                : YapePaymentIntent::STATUS_WAITING_RECEIVER,
        ]);

        $this->appendYapeDirectDebug('yape_intent_evidence_linked', $this->intentEvidenceLogContext(
            $intent->fresh(['clientEvidence']) ?: $intent,
            $evidence,
            $evidence->id,
            'direct_attach',
            'linked_current_evidence_to_intent'
        ));

        $this->maybeReconcile($this->attachExistingEvidence($intent->fresh()));

        return $evidence->fresh(['intent']);
    }

    protected function extractChatIdFromPayload(mixed $payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $message = $payload['message'] ?? $payload['data']['message'] ?? $payload['payload']['message'] ?? $payload['data'] ?? $payload['payload'] ?? null;

        if (!is_array($message)) {
            return null;
        }

        foreach (['from', 'chatId', 'sender.id', 'author', 'participant'] as $path) {
            $value = $this->arrayGet($message, $path);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function arrayGet(array $payload, string $path): mixed
    {
        $current = $payload;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public function ingestReceiverEvent(array $data, ?UploadedFile $file = null): YapeReceiverEvent
    {
        $operationCode = $this->normalizeOperationCode($data['operation_code'] ?? null);
        $securityCode = $this->normalizeSecurityCode($data['security_code'] ?? null);

        if ($operationCode) {
            $existing = YapeReceiverEvent::where('operation_code', $operationCode)->first();
            if ($existing) {
                if (!$existing->yape_payment_intent_id) {
                    $intent = $this->findIntentForReceiverEvent(
                        $existing,
                        $this->normalizeTargetIntentId($data['target_intent_id'] ?? null),
                        $data['source'] ?? null
                    );

                    if ($intent) {
                        $existing->update([
                            'yape_payment_intent_id' => $intent->id,
                            'status' => 'attached',
                        ]);

                        $intent->update([
                            'receiver_event_id' => $existing->id,
                            'operation_code' => $existing->operation_code ?: $intent->operation_code,
                            'security_code' => $existing->security_code ?: $intent->security_code,
                        ]);

                        $this->maybeReconcile($this->attachExistingEvidence($intent->fresh()));
                    }
                }

                return $existing->fresh(['intent']);
            }
        }

        $path = $this->storeEvidenceImage(
            $data,
            $file,
            config('billing.yape.receiver_event_dir', 'yape/receiver-events'),
            'receiver'
        );

        $event = YapeReceiverEvent::create([
            'device_serial' => $data['device_serial'] ?? null,
            'screenshot_path' => $path,
            'raw_text' => $data['raw_text'] ?? null,
            'parsed_amount' => isset($data['amount']) ? round((float) $data['amount'], 2) : null,
            'operation_code' => $operationCode,
            'security_code' => $securityCode,
            'occurred_at' => $this->parseDateTime($data['occurred_at'] ?? null),
            'sender_name' => $data['sender_name'] ?? null,
            'sender_phone' => $this->normalizePhone((string) ($data['sender_phone'] ?? '')),
            'confidence' => round((float) ($data['confidence'] ?? 0), 2),
            'status' => 'parsed',
            'payload' => $data,
            'processed_at' => now(),
        ]);

        $intent = $this->findIntentForReceiverEvent(
            $event,
            $this->normalizeTargetIntentId($data['target_intent_id'] ?? null),
            $data['source'] ?? null
        );

        if ($intent) {
            $event->update([
                'yape_payment_intent_id' => $intent->id,
                'status' => 'attached',
            ]);

            $intent->update([
                'receiver_event_id' => $event->id,
                'operation_code' => $event->operation_code ?: $intent->operation_code,
                'security_code' => $event->security_code ?: $intent->security_code,
            ]);

            $this->maybeReconcile($this->attachExistingEvidence($intent->fresh()));
        }

        return $event->fresh(['intent']);
    }

    protected function attachExistingEvidence(YapePaymentIntent $intent): YapePaymentIntent
    {
        if (!$intent->client_evidence_id && $intent->whatsapp_phone) {
            $this->appendYapeDirectDebug('yape_intent_evidence_lookup', $this->intentEvidenceLogContext(
                $intent,
                null,
                null,
                'direct_relation_missing',
                'starting_client_evidence_fallback_lookup'
            ));

            $clientCandidates = YapeClientEvidence::query()
                ->whereNull('yape_payment_intent_id')
                ->where('whatsapp_phone', $intent->whatsapp_phone)
                ->where('parsed_amount', round((float) $intent->expected_amount, 2))
                ->where(function ($query) use ($intent) {
                    if ($intent->security_code) {
                        $query->where('security_code', $intent->security_code);
                    } elseif ($intent->operation_code) {
                        $query->where('operation_code', $intent->operation_code);
                    } else {
                        $query->where(function ($q) {
                            $q->whereNotNull('security_code')
                                ->orWhereNotNull('operation_code');
                        });
                    }
                })
                ->latest()
                ->get();

            if ($clientCandidates->count() === 1) {
                $client = $clientCandidates->first();
                $match = $this->clientEvidenceMatchesIntent($intent, $client);

                if (!$match['matched']) {
                    $client->update(['status' => 'ambiguous']);
                    $intent->update([
                        'status' => YapePaymentIntent::STATUS_OBSERVED,
                        'observed_reason' => implode(' ', $match['errors']),
                    ]);

                    $this->appendYapeDirectDebug('yape_intent_evidence_mismatch', array_merge(
                        $this->intentEvidenceLogContext($intent, $client, $client->id, 'fallback_lookup', 'fallback_candidate_rejected'),
                        ['mismatch_errors' => $match['errors']]
                    ));

                    return $intent->fresh(['clientEvidence', 'receiverEvent']);
                }

                $client->update([
                    'yape_payment_intent_id' => $intent->id,
                    'customer_id' => $intent->customer_id,
                    'status' => 'attached',
                ]);

                $intent->update([
                    'client_evidence_id' => $client->id,
                    'operation_code' => $client->operation_code ?: $intent->operation_code,
                    'security_code' => $client->security_code ?: $intent->security_code,
                ]);

                $this->appendYapeDirectDebug('yape_intent_evidence_lookup_fallback', $this->intentEvidenceLogContext(
                    $intent->fresh(['clientEvidence']) ?: $intent,
                    $client,
                    $client->id,
                    'fallback_operation_security_amount',
                    'fallback_client_evidence_attached'
                ));
            } elseif ($clientCandidates->count() > 1) {
                $clientCandidates->each->update(['status' => 'ambiguous']);
                $this->appendYapeDirectDebug('yape_intent_evidence_lookup_fallback', [
                    'intent_id' => $intent->id,
                    'intent_number' => $intent->intent_number,
                    'expected_operation_code' => $intent->operation_code,
                    'expected_security_code' => $intent->security_code,
                    'expected_amount' => $intent->expected_amount,
                    'selected_evidence_id' => null,
                    'match_method' => 'fallback_operation_security_amount',
                    'decision' => 'multiple_client_evidence_candidates_marked_ambiguous',
                    'candidate_count' => $clientCandidates->count(),
                ]);
            }
        }

        $intent = $intent->fresh(['clientEvidence', 'receiverEvent']);
        $intent = $this->syncClientSecurityCodeFromOperationSuffix($intent);

        if (!$intent->receiver_event_id) {
            $receiverCandidates = YapeReceiverEvent::query()
                ->whereNull('yape_payment_intent_id')
                ->where('parsed_amount', round((float) $intent->expected_amount, 2));

            if ($this->deferAutomaticPaymentCompletion) {
                $receiverCandidates->whereIn('id', $this->resumeReceiverEventIds ?: [-1]);
            }

            if ($intent->security_code) {
                $receiverCandidates->where('security_code', $intent->security_code);
            } elseif ($intent->operation_code) {
                $receiverCandidates->where('operation_code', $intent->operation_code);
            } else {
                $receiverCandidates->where(function ($q) {
                    $q->whereNotNull('security_code')
                        ->orWhereNotNull('operation_code');
                });
            }

            $receiverCandidates = $receiverCandidates->latest()->get();

            if ($receiverCandidates->count() === 1) {
                $receiver = $receiverCandidates->first();
                $receiver->update([
                    'yape_payment_intent_id' => $intent->id,
                    'status' => 'attached',
                ]);

                $intent->update([
                    'receiver_event_id' => $receiver->id,
                    'operation_code' => $intent->operation_code ?: $receiver->operation_code,
                    'security_code' => $intent->security_code ?: $receiver->security_code,
                ]);
            } elseif ($receiverCandidates->count() > 1 && ($intent->security_code || $intent->operation_code)) {
                $receiverCandidates->each->update(['status' => 'ambiguous']);
            }
        }

        return $intent->fresh(['clientEvidence', 'receiverEvent']);
    }

    protected function maybeReconcile(YapePaymentIntent $intent): YapePaymentIntent
    {
        if ($this->deferAutomaticPaymentCompletion) {
            return $intent->fresh(['clientEvidence', 'receiverEvent', 'payment']);
        }

        return $this->tryReconcile($intent);
    }

    public function tryReconcile(YapePaymentIntent $intent): YapePaymentIntent
    {
        $intent->loadMissing(['clientEvidence', 'receiverEvent', 'invoice.contract.invoices.payments', 'invoice.contract.invoices.creditNotes']);

        if (!$intent->clientEvidence) {
            $intent->update(['status' => YapePaymentIntent::STATUS_WAITING_CLIENT]);
            return $intent->fresh();
        }

        if (!$intent->receiverEvent) {
            $intent->update(['status' => YapePaymentIntent::STATUS_WAITING_RECEIVER]);
            return $intent->fresh();
        }

        if ($intent->status === YapePaymentIntent::STATUS_VALIDATED) {
            return $intent;
        }

        $result = $this->matchResult($intent);

        if (!$result['matched']) {
            $intent->update([
                'status' => YapePaymentIntent::STATUS_OBSERVED,
                'observed_reason' => implode(' ', $result['errors']),
                'match_score' => $result['score'],
                'validation_result' => $result,
            ]);

            return $intent->fresh();
        }

        return DB::transaction(function () use ($intent, $result) {
            $paymentResult = $this->completePayment($intent, $result);

            if (!$paymentResult['success']) {
                $intent->update([
                    'status' => YapePaymentIntent::STATUS_OBSERVED,
                    'observed_reason' => $paymentResult['message'],
                    'match_score' => $result['score'],
                    'validation_result' => $result,
                ]);

                return $intent->fresh();
            }

            $payment = $paymentResult['payment'] ?? null;
            $intent->update([
                'payment_id' => $payment?->id,
                'invoice_id' => $payment?->invoice_id ?: $intent->invoice_id,
                'status' => YapePaymentIntent::STATUS_VALIDATED,
                'operation_code' => $intent->clientEvidence->operation_code ?: $intent->receiverEvent->operation_code,
                'security_code' => $intent->clientEvidence->security_code ?: $intent->receiverEvent->security_code,
                'match_score' => $result['score'],
                'validation_result' => $result,
                'validated_at' => now(),
                'observed_reason' => null,
            ]);

            $intent->clientEvidence->update(['status' => 'matched']);
            $intent->receiverEvent->update(['status' => 'matched']);
            if ($payment) {
                $this->syncOnboardingOrderAfterPayment($payment);
            }

            return $intent->fresh(['payment', 'clientEvidence', 'receiverEvent']);
        });
    }

    public function manuallyValidateWithoutReceiverEvent(YapePaymentIntent $intent, ?int $userId = null): YapePaymentIntent
    {
        $intent->loadMissing(['clientEvidence', 'receiverEvent', 'payment']);

        if ($intent->status === YapePaymentIntent::STATUS_VALIDATED || $intent->payment_id) {
            throw new \RuntimeException('Este Yape ya fue validado anteriormente.');
        }

        if (!$intent->clientEvidence) {
            throw new \RuntimeException('No se puede validar manualmente sin evidencia de WhatsApp del cliente.');
        }

        if (!$intent->invoice_id && !in_array(($intent->payment_mode ?? 'single'), ['multi_month', 'overpayment_with_credit', 'credit_only'], true)) {
            throw new \RuntimeException('El intent no tiene recibo o selección de recibos para aplicar el pago.');
        }

        $result = [
            'matched' => true,
            'score' => 100,
            'errors' => [],
            'manual_override' => true,
            'manual_reason' => 'Validación manual sin evento receptor Yape.',
            'manual_validated_by' => $userId,
            'manual_validated_at' => now()->toDateTimeString(),
            'client_evidence_id' => $intent->client_evidence_id,
            'receiver_event_id' => null,
        ];

        $expectedAmount = round((float) $intent->expected_amount, 2);
        $clientAmount = round((float) $intent->clientEvidence->parsed_amount, 2);

        if ($expectedAmount <= 0) {
            throw new \RuntimeException('El monto esperado del intent no es válido.');
        }

        if ($clientAmount > 0 && abs($expectedAmount - $clientAmount) >= 0.01) {
            $result['errors'][] = "Monto de captura (S/ {$clientAmount}) difiere de esperado (S/ {$expectedAmount}).";
            $result['score'] = 80;
        }

        $intentOperation = $this->normalizeOperationCode($intent->operation_code);
        $clientOperation = $this->normalizeOperationCode($intent->clientEvidence->operation_code);

        if ($intentOperation && $clientOperation && $intentOperation !== $clientOperation) {
            $result['errors'][] = "Código de operación de captura ({$clientOperation}) difiere de esperado ({$intentOperation}).";
            $result['score'] = 80;
        }

        return DB::transaction(function () use ($intent, $result) {
            $paymentResult = $this->completePayment($intent, $result);

            if (!$paymentResult['success']) {
                throw new \RuntimeException($paymentResult['message'] ?? 'No se pudo registrar el pago Yape manual.');
            }

            $payment = $paymentResult['payment'] ?? null;
            $intent->update([
                'payment_id' => $payment?->id,
                'invoice_id' => $payment?->invoice_id ?: $intent->invoice_id,
                'status' => YapePaymentIntent::STATUS_VALIDATED,
                'operation_code' => $intent->operation_code ?: $intent->clientEvidence->operation_code,
                'security_code' => $intent->security_code ?: $intent->clientEvidence->security_code,
                'match_score' => $result['score'],
                'validation_result' => $result,
                'validated_at' => now(),
                'observed_reason' => null,
            ]);

            $intent->clientEvidence->update(['status' => 'matched']);

            if ($payment) {
                $this->syncOnboardingOrderAfterPayment($payment);
            }

            return $intent->fresh(['payment', 'clientEvidence', 'receiverEvent']);
        });
    }

    public function cancelIntentManually(YapePaymentIntent $intent, ?int $userId = null, string $reason = 'manual_cancel'): YapePaymentIntent
    {
        $intent->loadMissing(['clientEvidence', 'receiverEvent']);

        if ($intent->status === YapePaymentIntent::STATUS_VALIDATED || $intent->payment_id) {
            throw new \RuntimeException('No se puede anular un Yape que ya fue validado y pagado.');
        }

        return DB::transaction(function () use ($intent, $userId, $reason) {
            $intent->update([
                'status' => YapePaymentIntent::STATUS_CANCELLED,
                'observed_reason' => $reason,
            ]);

            if ($intent->clientEvidence) {
                $payload = is_array($intent->clientEvidence->payload) ? $intent->clientEvidence->payload : [];
                $payload['yape_chat_state'] = array_merge(
                    is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [],
                    [
                        'state' => 'cancelled',
                        'awaiting_dni' => false,
                        'cancelled_at' => now()->toDateTimeString(),
                        'cancel_reason' => $reason,
                        'cancelled_by_user_id' => $userId,
                    ]
                );

                $intent->clientEvidence->update([
                    'status' => 'cancelled',
                    'payload' => $payload,
                ]);
            }

            if ($intent->receiverEvent) {
                $intent->receiverEvent->update([
                    'status' => 'cancelled',
                ]);
            }

            return $intent->fresh(['clientEvidence', 'receiverEvent']);
        });
    }

    /**
     * Close one Yape client evidence from the support review queue.
     *
     * It only closes the Yape case: the evidence, and the pending intent attached
     * to it when there is one. It never deletes payments and never touches invoices,
     * which is exactly what the review queue promises to the operator.
     *
     * @throws \RuntimeException when the case already produced a payment.
     */
    public function cancelClientEvidenceManually(
        YapeClientEvidence $evidence,
        ?int $userId = null,
        string $reason = 'manual_cancel'
    ): YapeClientEvidence {
        $evidence->loadMissing(['intent']);
        $intent = $evidence->intent;

        if ($intent && ($intent->status === YapePaymentIntent::STATUS_VALIDATED || $intent->payment_id)) {
            throw new \RuntimeException('No se puede anular un caso Yape que ya generó un pago registrado.');
        }

        return DB::transaction(function () use ($evidence, $intent, $userId, $reason) {
            $payload = is_array($evidence->payload) ? $evidence->payload : [];
            $payload['yape_chat_state'] = array_merge(
                is_array($payload['yape_chat_state'] ?? null) ? $payload['yape_chat_state'] : [],
                [
                    'state' => 'cancelled',
                    'awaiting_dni' => false,
                    'cancelled_at' => now()->toDateTimeString(),
                    'cancel_reason' => $reason,
                    'cancelled_by_user_id' => $userId,
                ]
            );

            $evidence->update([
                'status' => 'cancelled',
                'payload' => $payload,
                'processed_at' => $evidence->processed_at ?: now(),
            ]);

            if ($intent && $intent->status !== YapePaymentIntent::STATUS_CANCELLED) {
                $intent->update([
                    'status' => YapePaymentIntent::STATUS_CANCELLED,
                    'observed_reason' => $reason,
                ]);

                if ($intent->receiver_event_id) {
                    YapeReceiverEvent::where('id', $intent->receiver_event_id)
                        ->update(['status' => 'cancelled']);
                }
            }

            return $evidence->fresh(['intent']) ?: $evidence;
        });
    }

    protected function completePayment(YapePaymentIntent $intent, array $result): array
    {
        if ($intent->status === YapePaymentIntent::STATUS_VALIDATED || $intent->payment_id) {
            return [
                'success' => false,
                'message' => 'Este Yape ya fue validado anteriormente.',
            ];
        }

        if ($intent->invoice_id) {
            $invoice = Invoice::find($intent->invoice_id);

            if ($invoice && in_array($invoice->status, ['paid', 'cancelled', 'void'], true)) {
                return [
                    'success' => false,
                    'message' => 'El recibo asociado ya no tiene deuda pendiente.',
                ];
            }
        }

        $payload = $intent->source_payload ?? [];
        $operationCode = $intent->clientEvidence?->operation_code
            ?: ($intent->receiverEvent?->operation_code ?: $intent->operation_code);
        $paymentDate = optional($intent->clientEvidence?->occurred_at ?: $intent->receiverEvent?->occurred_at)->toDateString()
            ?: ($payload['payment_date'] ?? now()->toDateString());

        $baseData = array_merge($payload, [
            'amount' => (float) $intent->expected_amount,
            'payment_date' => $paymentDate,
            'payment_method' => 'yape',
            'reference' => $operationCode,
            'user_id' => $payload['user_id'] ?? $intent->created_by,
            'payment_details' => [
                'yape_intent_id' => $intent->id,
                'client_evidence_id' => $intent->client_evidence_id,
                'receiver_event_id' => $intent->receiver_event_id,
                'operation_code' => $operationCode,
                'security_code' => $intent->clientEvidence?->security_code ?: $intent->receiverEvent?->security_code,
                'match_score' => $result['score'],
                'validation_result' => $result,
                'cashier_context' => !empty($result['manual_override'])
                    ? 'manual_yape_validation'
                    : 'automatic_yape_validation',
            ],
        ]);

        if (($intent->payment_mode ?? null) === 'credit_only') {
            return $this->completeCreditOnlyPayment($intent, $baseData);
        }

        if (in_array(($intent->payment_mode ?? 'single'), ['multi_month', 'overpayment_with_credit'], true)) {
            $invoiceIds = collect(data_get($intent->source_payload, 'applied_invoice_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $this->appendYapeDirectDebug('yape_multi_invoice_payment_started', [
                'intent_id' => $intent->id,
                'evidence_id' => $intent->client_evidence_id,
                'expected_amount' => (float) $intent->expected_amount,
                'contract_id' => $intent->contract_id,
                'customer_id' => $intent->customer_id,
                'matched_invoice_ids' => $invoiceIds,
                'payment_mode' => $intent->payment_mode,
                'selection_strategy' => data_get($intent->source_payload, 'selection_strategy'),
            ]);

            if (empty($invoiceIds)) {
                return [
                    'success' => false,
                    'message' => 'El intent Yape multimes no tiene recibos seleccionados para aplicar.'
                ];
            }

            $baseData['allow_overpayment_credit'] = ($intent->payment_mode ?? null) === 'overpayment_with_credit';

            $paymentResult = $this->paymentService->registerYapeMultiInvoicePayment($baseData, $invoiceIds);

            $this->appendYapeDirectDebug(($paymentResult['success'] ?? false) ? 'yape_multi_invoice_payment_created' : 'yape_multi_invoice_payment_failed', [
                'intent_id' => $intent->id,
                'evidence_id' => $intent->client_evidence_id,
                'expected_amount' => (float) $intent->expected_amount,
                'contract_id' => $intent->contract_id,
                'customer_id' => $intent->customer_id,
                'matched_invoice_ids' => $invoiceIds,
                'payment_mode' => $intent->payment_mode,
                'payment_group_id' => $paymentResult['payment_group_id'] ?? null,
                'payment_id' => $paymentResult['payment']?->id ?? null,
                'applied_invoices' => $paymentResult['applied_invoices'] ?? [],
                'original_yape_amount' => $paymentResult['original_yape_amount'] ?? null,
                'applied_amount' => $paymentResult['applied_amount'] ?? null,
                'credit_amount' => $paymentResult['credit_amount'] ?? null,
                'credit_movement_id' => $paymentResult['credit_movement_id'] ?? null,
                'message' => $paymentResult['message'] ?? null,
                'decision' => ($paymentResult['success'] ?? false) ? 'multi_invoice_payment_created' : 'multi_invoice_payment_failed',
            ]);

            if ($paymentResult['success'] ?? false) {
                if (($paymentResult['credit_amount'] ?? 0) > 0) {
                    $payload = is_array($intent->source_payload) ? $intent->source_payload : [];
                    $payload = array_merge($payload, [
                        'payment_mode' => 'overpayment_with_credit',
                        'original_yape_amount' => $paymentResult['original_yape_amount'] ?? (float) $intent->expected_amount,
                        'applied_amount' => $paymentResult['applied_amount'] ?? null,
                        'credit_amount' => $paymentResult['credit_amount'] ?? null,
                        'credit_movement_id' => $paymentResult['credit_movement_id'] ?? null,
                        'payment_group_id' => $paymentResult['payment_group_id'] ?? null,
                    ]);
                    $intent->forceFill(['source_payload' => $payload])->save();

                    $this->appendYapeDirectDebug('yape_overpayment_credit_created', [
                        'intent_id' => $intent->id,
                        'customer_id' => $intent->customer_id,
                        'contract_id' => $intent->contract_id,
                        'original_yape_amount' => $paymentResult['original_yape_amount'] ?? null,
                        'applied_amount' => $paymentResult['applied_amount'] ?? null,
                        'credit_amount' => $paymentResult['credit_amount'] ?? null,
                        'credit_movement_id' => $paymentResult['credit_movement_id'] ?? null,
                        'payment_group_id' => $paymentResult['payment_group_id'] ?? null,
                        'applied_invoice_ids' => $invoiceIds,
                    ]);
                }

                $this->appendYapeDirectDebug('yape_multi_invoice_marked_paid', [
                    'intent_id' => $intent->id,
                    'matched_invoice_ids' => $invoiceIds,
                    'payment_group_id' => $paymentResult['payment_group_id'] ?? null,
                    'applied_invoices' => $paymentResult['applied_invoices'] ?? [],
                    'credit_amount' => $paymentResult['credit_amount'] ?? 0,
                    'credit_movement_id' => $paymentResult['credit_movement_id'] ?? null,
                ]);
            }

            return $paymentResult;
        }

        $baseData['invoice_id'] = $intent->invoice_id;
        $baseData['status'] = 'completed';

        return $this->paymentService->registerPayment($baseData);
    }

    protected function completeCreditOnlyPayment(YapePaymentIntent $intent, array $baseData): array
    {
        $contract = Contract::with(['customer', 'plan', 'contractedServices'])->find($intent->contract_id);
        $customer = $contract?->customer;
        $amount = round((float) $intent->expected_amount, 2);

        if (!$contract || !$customer || $amount <= 0) {
            return [
                'success' => false,
                'message' => 'No se pudo identificar el contrato para registrar el saldo a favor Yape.',
            ];
        }

        $advanceMonths = $this->paymentService->exactAdvancePaymentMonths($contract, $amount);

        if ($advanceMonths !== null) {
            $advanceResult = $this->paymentService->registerMultiMonthPayment(array_merge($baseData, [
                'contract_id' => $contract->id,
                'invoice_id' => null,
                'amount' => $amount,
                'months_to_pay' => $advanceMonths,
                'notes' => 'Pago adelantado generado automáticamente desde validación Yape.',
            ]));

            if (! ($advanceResult['success'] ?? false)) {
                return $advanceResult;
            }

            $payment = $advanceResult['payment'] ?? null;
            $payload = is_array($intent->source_payload) ? $intent->source_payload : [];
            $intent->forceFill([
                'payment_mode' => 'multi_month',
                'months_to_pay' => $advanceMonths,
                'invoice_id' => $payment?->invoice_id,
                'source_payload' => array_merge($payload, [
                    'payment_mode' => 'advance_payment',
                    'selection_strategy' => 'automatic_advance_exact_amount',
                    'months_to_pay' => $advanceMonths,
                    'original_yape_amount' => $amount,
                    'applied_amount' => $amount,
                    'credit_amount' => 0,
                    'credit_movement_id' => null,
                    'payment_group_id' => data_get($payment?->payment_details, 'payment_group_id'),
                    'applied_invoice_ids' => collect($advanceResult['applied_invoices'] ?? [])
                        ->pluck('invoice_id')
                        ->values()
                        ->all(),
                    'applied_invoices' => $advanceResult['applied_invoices'] ?? [],
                ]),
            ])->save();

            $this->appendYapeDirectDebug('yape_advance_payment_created', [
                'intent_id' => $intent->id,
                'customer_id' => $customer->id,
                'contract_id' => $contract->id,
                'payment_id' => $payment?->id,
                'invoice_id' => $payment?->invoice_id,
                'amount' => $amount,
                'months_to_pay' => $advanceMonths,
                'applied_invoices' => $advanceResult['applied_invoices'] ?? [],
                'decision' => 'automatic_advance_exact_amount',
            ]);

            return $advanceResult;
        }

        $creditMovement = app(CustomerCreditService::class)->addCredit([
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'source_type' => 'yape_payment_intent',
            'source_id' => $intent->id,
            'amount' => $amount,
            'description' => 'Saldo a favor generado por Yape sin deuda pendiente.',
            'created_by' => $baseData['user_id'] ?? $intent->created_by,
            'metadata' => [
                'source' => 'yape',
                'yape_intent_id' => $intent->id,
                'evidence_id' => $intent->client_evidence_id,
                'operation_code' => $baseData['reference'] ?? $intent->operation_code,
                'security_code' => data_get($baseData, 'payment_details.security_code'),
                'original_yape_amount' => $amount,
                'applied_amount' => 0,
                'credit_amount' => $amount,
                'applied_invoice_ids' => [],
            ],
        ]);

        $payload = is_array($intent->source_payload) ? $intent->source_payload : [];
        $intent->forceFill([
            'source_payload' => array_merge($payload, [
                'payment_mode' => 'credit_only',
                'original_yape_amount' => $amount,
                'applied_amount' => 0,
                'credit_amount' => $amount,
                'credit_movement_id' => $creditMovement->id,
                'credit_balance_after' => (float) $creditMovement->balance_after,
                'applied_invoice_ids' => [],
            ]),
        ])->save();

        $this->appendYapeDirectDebug('yape_payment_application_credit_created', [
            'intent_id' => $intent->id,
            'evidence_id' => $intent->client_evidence_id,
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'original_yape_amount' => $amount,
            'applied_amount' => 0,
            'credit_amount' => $amount,
            'credit_movement_id' => $creditMovement->id,
            'balance_after' => (float) $creditMovement->balance_after,
            'decision' => 'credit_only',
        ]);

        return [
            'success' => true,
            'message' => 'Saldo a favor Yape registrado correctamente.',
            'payment' => null,
            'payments' => collect(),
            'applied_invoices' => [],
            'payment_group_id' => null,
            'original_yape_amount' => $amount,
            'applied_amount' => 0,
            'credit_amount' => $amount,
            'credit_movement' => $creditMovement,
            'credit_movement_id' => $creditMovement->id,
        ];
    }

    protected function matchResult(YapePaymentIntent $intent): array
    {
        $client = $intent->clientEvidence;
        $receiver = $intent->receiverEvent;
        $errors = [];
        $score = 100;
        $threshold = (float) config('billing.yape.confidence_threshold', 0.88);
        $tolerance = (int) config('billing.yape.time_tolerance_minutes', 10);
        $clientEvidenceMatch = $this->clientEvidenceMatchesIntent($intent, $client);

        if (!$clientEvidenceMatch['matched']) {
            foreach ($clientEvidenceMatch['errors'] as $error) {
                $errors[] = $error;
            }
            $score -= 80;

            $this->appendYapeDirectDebug('yape_validation_wrong_evidence_prevented', array_merge(
                $this->intentEvidenceLogContext($intent, $client, $client?->id, 'match_result_precheck', 'prevent_validation_with_wrong_client_evidence'),
                [
                    'mismatch_errors' => $clientEvidenceMatch['errors'],
                ]
            ));
        }

        if (round((float) $client->parsed_amount, 2) !== round((float) $intent->expected_amount, 2)
            || round((float) $receiver->parsed_amount, 2) !== round((float) $intent->expected_amount, 2)) {
            $errors[] = 'El monto no coincide con el recibo pendiente.';
            $score -= 35;
        }

        if (!$client->security_code || !$receiver->security_code || $client->security_code !== $receiver->security_code) {
            $errors[] = 'El código de seguridad no coincide entre WhatsApp y la notificación Yape.';
            $score -= 45;
        }

        if (!$client->operation_code) {
            $errors[] = 'La captura del cliente no tiene número de operación.';
            $score -= 20;
        }

        if ($receiver->operation_code && $client->operation_code && $client->operation_code !== $receiver->operation_code) {
            $errors[] = 'El número de operación del receptor no coincide con WhatsApp.';
            $score -= 35;
        }

        if ((float) $client->confidence < $threshold || (float) $receiver->confidence < $threshold) {
            $errors[] = 'La confianza OCR es baja.';
            $score -= 15;
        }

        if ($client->occurred_at && $receiver->occurred_at) {
            if (!$this->isYapeTimeDifferenceAcceptable($client->occurred_at, $receiver->occurred_at, $tolerance)) {
                $errors[] = "La diferencia de hora supera {$tolerance} minutos.";
                $score -= 15;
            }
        }

        $receiverPhone = $this->normalizePhone((string) config('billing.yape.receiver_phone'));

        if ($receiverPhone && $client->receiver_phone && $client->receiver_phone !== $receiverPhone) {
            $errors[] = 'El receptor de la captura del cliente no coincide con la cuenta Yape configurada.';
            $score -= 10;
        }

        if ($client->operation_code && YapePaymentIntent::where('operation_code', $client->operation_code)
            ->where('id', '!=', $intent->id)
            ->where('status', YapePaymentIntent::STATUS_VALIDATED)
            ->exists()) {
            $errors[] = 'El número de operación ya fue usado para validar otro pago.';
            $score -= 50;
        }

        return [
            'matched' => empty($errors),
            'score' => max($score, 0),
            'errors' => $errors,
            'checked_at' => now()->toDateTimeString(),
            'rules' => [
                'amount_exact' => true,
                'security_code_exact' => true,
                'operation_reference_required_from_client' => true,
                'receiver_operation_optional' => true,
                'time_tolerance_minutes' => $tolerance,
                'business_timezone' => $this->yapeBusinessTimezone(),
                'client_occurred_at_normalized' => $client->occurred_at
                    ? $this->normalizeYapeComparisonTime($client->occurred_at)->toDateTimeString()
                    : null,
                'receiver_occurred_at_normalized' => $receiver->occurred_at
                    ? $this->normalizeYapeComparisonTime($receiver->occurred_at)->toDateTimeString()
                    : null,
                'confidence_threshold' => $threshold,
            ],
        ];
    }

    protected function isYapeTimeDifferenceAcceptable(Carbon $clientOccurredAt, Carbon $receiverOccurredAt, int $tolerance): bool
    {
        $clientTime = $this->normalizeYapeComparisonTime($clientOccurredAt);
        $receiverTime = $this->normalizeYapeComparisonTime($receiverOccurredAt);
        $minutes = abs($clientTime->diffInMinutes($receiverTime));

        return $minutes <= $tolerance;
    }

    protected function findIntentForClientEvidence(YapeClientEvidence $evidence): ?YapePaymentIntent
    {
        // Desactivado el matching automático por número de teléfono para evitar asociaciones incorrectas.
        // Se requiere obligatoriamente la resolución por DNI del cliente para emparejar el pago.
        return null;
    }

    protected function findIntentForReceiverEvent(
        YapeReceiverEvent $event,
        ?int $targetIntentId = null,
        ?string $source = null
    ): ?YapePaymentIntent {
        if (!$event->parsed_amount || (!$event->security_code && !$event->operation_code)) {
            $event->update(['status' => 'incomplete']);
            return null;
        }

        if ($targetIntentId) {
            $target = YapePaymentIntent::query()
                ->whereKey($targetIntentId)
                ->whereNotIn('status', [YapePaymentIntent::STATUS_VALIDATED, YapePaymentIntent::STATUS_CANCELLED])
                ->whereNull('receiver_event_id')
                ->where('expected_amount', round((float) $event->parsed_amount, 2))
                ->when($event->security_code, function ($query) use ($event) {
                    $query->where(function ($q) use ($event) {
                        $q->whereNull('security_code')
                            ->orWhere('security_code', $event->security_code);
                    });
                })
                ->when(!$event->security_code && $event->operation_code, function ($query) use ($event) {
                    $query->where(function ($q) use ($event) {
                        $q->whereNull('operation_code')
                            ->orWhere('operation_code', $event->operation_code);
                    });
                })
                ->first();

            if ($target) {
                return $target;
            }
        }

        $query = YapePaymentIntent::query()
            ->whereNotIn('status', [YapePaymentIntent::STATUS_VALIDATED, YapePaymentIntent::STATUS_CANCELLED])
            ->whereNull('receiver_event_id')
            ->where('expected_amount', round((float) $event->parsed_amount, 2));

        if ($event->security_code) {
            $query->where(function ($q) use ($event) {
                $q->whereNull('security_code')
                    ->orWhere('security_code', $event->security_code);
            });
        } elseif ($event->operation_code) {
            $query->where(function ($q) use ($event) {
                $q->whereNull('operation_code')
                    ->orWhere('operation_code', $event->operation_code);
            });
        }

        return $this->resolveSingleIntentCandidate($query->oldest()->get(), $event);
    }

    protected function resolveSingleIntentCandidate($candidates, $evidenceOrEvent): ?YapePaymentIntent
    {
        if ($candidates->count() === 0) {
            return null;
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $deduplicatedIntent = $this->deduplicateSameInvoiceIntentCandidates($candidates);

        if ($deduplicatedIntent) {
            return $deduplicatedIntent;
        }

        $evidenceOrEvent->update(['status' => 'ambiguous']);

        return null;
    }

    protected function deduplicateSameInvoiceIntentCandidates($candidates): ?YapePaymentIntent
    {
        $invoiceIds = $candidates->pluck('invoice_id')->filter()->unique()->values();
        $customerIds = $candidates->pluck('customer_id')->filter()->unique()->values();
        $amounts = $candidates
            ->map(fn (YapePaymentIntent $intent) => number_format((float) $intent->expected_amount, 2, '.', ''))
            ->unique()
            ->values();

        if ($invoiceIds->count() !== 1 || $customerIds->count() > 1 || $amounts->count() !== 1) {
            return null;
        }

        $winner = $candidates
            ->sortByDesc(function (YapePaymentIntent $intent) {
                $hasReceiver = $intent->receiver_event_id ? 1 : 0;
                $hasClient = $intent->client_evidence_id ? 1 : 0;
                $createdAt = optional($intent->created_at)->timestamp ?? 0;

                return ($hasReceiver * 10000000000000)
                    + ($hasClient * 1000000000000)
                    + ($createdAt * 1000)
                    + (int) $intent->id;
            })
            ->first();

        if (!$winner) {
            return null;
        }

        $staleIds = $candidates
            ->reject(fn (YapePaymentIntent $intent) => (int) $intent->id === (int) $winner->id)
            ->filter(fn (YapePaymentIntent $intent) => !$intent->client_evidence_id && !$intent->receiver_event_id)
            ->pluck('id')
            ->values();

        if ($staleIds->isNotEmpty()) {
            YapePaymentIntent::whereKey($staleIds)->update([
                'status' => YapePaymentIntent::STATUS_CANCELLED,
                'observed_reason' => 'Cancelado automáticamente por intento Yape duplicado para el mismo recibo y monto.',
                'updated_at' => now(),
            ]);
        }

        return $winner->fresh();
    }

    protected function storeEvidenceImage(array $data, ?UploadedFile $file, string $directory, string $prefix): string
    {
        $disk = config('billing.yape.storage_disk', 'local');

        if ($file) {
            return $file->store($directory, $disk);
        }

        if (!empty($data['image_base64'])) {
            $base64 = trim((string) $data['image_base64']);
            $mimetype = $this->imageBase64Mimetype($base64)
                ?: (string) ($data['media_mimetype'] ?? data_get($data, 'payload.openwa.data.media.mimetype') ?? '');
            $binary = base64_decode(preg_replace('#^data:image/[-+\w.]+;base64,#i', '', $base64), true);

            if ($binary === false || $binary === '') {
                \Log::warning('No se pudo guardar evidencia Yape: base64 inválido.', [
                    'source_message_id' => $data['source_message_id'] ?? null,
                    'source_chat_name' => $data['source_chat_name'] ?? null,
                    'media_mimetype' => $mimetype ?: null,
                    'base64_prefix' => substr($base64, 0, 30),
                ]);

                return '';
            }

            $extension = $this->extensionFromImageMimeType($mimetype);
            $safeMessageId = $this->safeFilenameToken((string) ($data['source_message_id'] ?? ''));
            $fingerprint = substr(sha1($binary), 0, 12);
            $token = $safeMessageId ?: $fingerprint;
            $path = trim($directory, '/') . '/' . $prefix . '-' . now()->format('YmdHis') . '-' . $token . '.' . $extension;
            Storage::disk($disk)->put($path, $binary);
            return $path;
        }

        if (!empty($data['image_path']) && is_file($data['image_path'])) {
            $extension = strtolower(pathinfo($data['image_path'], PATHINFO_EXTENSION) ?: 'png');
            $extension = preg_match('/^(jpg|jpeg|png|webp)$/', $extension) ? $extension : 'png';
            $path = trim($directory, '/') . '/' . $prefix . '-' . now()->format('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
            Storage::disk($disk)->put($path, file_get_contents($data['image_path']));
            return $path;
        }

        return $data['image_path'] ?? '';
    }

    protected function imageBase64Mimetype(string $base64): ?string
    {
        if (preg_match('#^data:(image/[-+\w.]+);base64,#i', $base64, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }

    protected function extensionFromImageMimeType(?string $mimetype): string
    {
        return match (strtolower((string) $mimetype)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    protected function safeFilenameToken(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);
        $value = trim((string) $value, '-_.');

        return substr($value, 0, 120);
    }

    protected function parseDateTime(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value, $this->yapeBusinessTimezone());
        } catch (\Throwable) {
            return null;
        }
    }

    protected function normalizeYapeComparisonTime(Carbon $dateTime): Carbon
    {
        return $dateTime->copy()->timezone($this->yapeBusinessTimezone());
    }

    protected function yapeBusinessTimezone(): string
    {
        return config('app.timezone') ?: 'America/Lima';
    }

    protected function clientEvidenceMatchesIntent(YapePaymentIntent $intent, ?YapeClientEvidence $evidence): array
    {
        $errors = [];

        if (!$evidence) {
            return [
                'matched' => false,
                'errors' => ['La validación Yape no tiene evidencia WhatsApp vinculada.'],
            ];
        }

        $expectedOperationCode = $this->normalizeOperationCode(
            $intent->operation_code ?: data_get($intent->source_payload, 'operation_code')
        );
        $expectedSecurityCode = $this->normalizeSecurityCode(
            $intent->security_code ?: data_get($intent->source_payload, 'security_code')
        );
        $expectedAmount = round((float) $intent->expected_amount, 2);
        $linkedAmount = round((float) $evidence->parsed_amount, 2);

        if ($expectedOperationCode && $evidence->operation_code && $expectedOperationCode !== $evidence->operation_code) {
            $errors[] = 'La operación de la evidencia WhatsApp no coincide con la operación esperada del intent.';
        }

        if ($expectedSecurityCode && $evidence->security_code && $expectedSecurityCode !== $evidence->security_code) {
            $errors[] = 'El código de seguridad de la evidencia WhatsApp no coincide con el intent.';
        }

        if ($expectedAmount > 0 && $linkedAmount > 0 && $expectedAmount !== $linkedAmount) {
            $errors[] = 'El monto de la evidencia WhatsApp no coincide con el intent.';
        }

        return [
            'matched' => $errors === [],
            'errors' => $errors,
        ];
    }

    protected function clientEvidencePaymentFingerprintMatches(?YapeClientEvidence $left, ?YapeClientEvidence $right): bool
    {
        if (!$left || !$right) {
            return false;
        }

        $sameOperation = $left->operation_code
            && $right->operation_code
            && $left->operation_code === $right->operation_code;
        $sameSecurity = $left->security_code
            && $right->security_code
            && $left->security_code === $right->security_code;
        $sameAmount = round((float) $left->parsed_amount, 2) === round((float) $right->parsed_amount, 2);

        return $sameAmount && ($sameOperation || $sameSecurity);
    }

    protected function intentEvidenceLogContext(
        YapePaymentIntent $intent,
        ?YapeClientEvidence $linkedEvidence,
        ?int $selectedEvidenceId,
        string $matchMethod,
        string $decision
    ): array {
        return [
            'intent_id' => $intent->id,
            'intent_number' => $intent->intent_number,
            'expected_operation_code' => $intent->operation_code ?: data_get($intent->source_payload, 'operation_code'),
            'expected_security_code' => $intent->security_code ?: data_get($intent->source_payload, 'security_code'),
            'expected_amount' => $intent->expected_amount,
            'linked_evidence_id' => $linkedEvidence?->id,
            'linked_source_message_id' => $linkedEvidence?->source_message_id,
            'linked_operation_code' => $linkedEvidence?->operation_code,
            'linked_security_code' => $linkedEvidence?->security_code,
            'linked_amount' => $linkedEvidence?->parsed_amount,
            'selected_evidence_id' => $selectedEvidenceId,
            'selected_operation_code' => $linkedEvidence?->operation_code,
            'selected_security_code' => $linkedEvidence?->security_code,
            'match_method' => $matchMethod,
            'decision' => $decision,
        ];
    }

    public function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51' . $digits;
        }

        return $digits;
    }

    protected function normalizeOperationCode(?string $operationCode): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $operationCode);
        return $digits ?: null;
    }

    protected function normalizeSecurityCode(?string $securityCode): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $securityCode);
        return $digits ?: null;
    }

    protected function normalizeClientEvidenceSecurityCode(?string $securityCode, ?string $operationCode): ?string
    {
        $operationSuffix = $this->securityCodeFromOperationCode($operationCode);

        if (!$operationSuffix) {
            return $securityCode;
        }

        return $securityCode === $operationSuffix ? $securityCode : $operationSuffix;
    }

    protected function syncClientSecurityCodeFromOperationSuffix(YapePaymentIntent $intent): YapePaymentIntent
    {
        $client = $intent->clientEvidence;

        if (!$client || !$client->operation_code) {
            return $intent;
        }

        $operationSuffix = $this->securityCodeFromOperationCode($client->operation_code);

        if (!$operationSuffix || $client->security_code === $operationSuffix) {
            return $intent;
        }

        $client->update(['security_code' => $operationSuffix]);
        $intent->update(['security_code' => $operationSuffix]);

        return $intent->fresh(['clientEvidence', 'receiverEvent']);
    }

    protected function securityCodeFromOperationCode(?string $operationCode): ?string
    {
        $digits = $this->normalizeOperationCode($operationCode);

        if (!$digits || strlen($digits) < 3) {
            return null;
        }

        return substr($digits, -3);
    }

    protected function normalizeTargetIntentId(mixed $targetIntentId): ?int
    {
        $id = (int) $targetIntentId;

        return $id > 0 ? $id : null;
    }

    protected function syncOnboardingOrderAfterPayment($payment): void
    {
        $payment->loadMissing('invoice.contract.invoices.payments', 'invoice.contract.invoices.creditNotes');
        $invoice = $payment->invoice?->fresh(['contract.invoices.payments', 'contract.invoices.creditNotes']);

        if (!$invoice || !$invoice->contract) {
            return;
        }

        $order = OnboardingOrder::query()
            ->where('invoice_id', $invoice->id)
            ->orWhere('contract_id', $invoice->contract_id)
            ->latest()
            ->first();

        if (!$order || $invoice->status !== 'paid') {
            return;
        }

        $hasPendingInvoices = $invoice->contract->invoices
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->isNotEmpty();

        $order->update([
            'status' => $hasPendingInvoices ? OnboardingOrder::STATUS_INVOICED : OnboardingOrder::STATUS_PAID,
            'paid_at' => $hasPendingInvoices ? $order->paid_at : now(),
        ]);
    }

    public function enrichEvidenceWithOcr(YapeClientEvidence $evidence): bool
    {
        $ocrMode = strtolower((string) config('billing.yape.ocr_mode', 'local'));

        if ($ocrMode === 'remote') {
            return $this->enrichEvidenceWithRemoteOcr($evidence);
        }

        if (!(bool) config('billing.yape.openwa.ocr_enabled', true)) {
            $evidence->update(['status' => 'parsed']);
            return false;
        }

        $disk = config('billing.yape.storage_disk', 'local');

        if (!Storage::disk($disk)->exists($evidence->image_path)) {
            \Log::warning('No se pudo ejecutar OCR: la imagen de evidencia no existe.', [
                'evidence_id' => $evidence->id,
                'image_path' => $evidence->image_path,
            ]);
            $evidence->update(['status' => 'parsed']);
            return false;
        }

        $absolutePath = Storage::disk($disk)->path($evidence->image_path);

        try {
            $process = new \Symfony\Component\Process\Process([
                (string) config('billing.yape.openwa.python_path', 'python'),
                (string) config('billing.yape.openwa.ocr_parser_path'),
                'client',
                $absolutePath,
            ]);
            $process->setTimeout((int) config('billing.yape.openwa.ocr_timeout', 30));
            $process->run();

            if (!$process->isSuccessful()) {
                \Log::warning('El proceso OCR de Python falló.', [
                    'evidence_id' => $evidence->id,
                    'exit_code' => $process->getExitCode(),
                    'error_output' => trim($process->getErrorOutput()),
                ]);
                $evidence->update(['status' => 'parsed']);
                return false;
            }

            $output = trim($process->getOutput());
            $errorOutput = trim($process->getErrorOutput());

            $lines = preg_split('/\r\n|\r|\n/', $output);
            $jsonLine = null;

            foreach (array_reverse($lines) as $line) {
                $line = trim($line);

                if (str_starts_with($line, '{') && str_ends_with($line, '}')) {
                    $jsonLine = $line;
                    break;
                }
            }

            if ($jsonLine) {
                $jsonLine = mb_convert_encoding($jsonLine, 'UTF-8', 'UTF-8');
                $jsonLine = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $jsonLine);
            }

            $parsed = $jsonLine ? json_decode($jsonLine, true, 512, JSON_INVALID_UTF8_SUBSTITUTE) : null;

            if (!$this->isUsableOcrResult($parsed)) {
                \Log::warning('El parser OCR retornó JSON inválido o fallido.', [
                    'evidence_id' => $evidence->id,
                    'output' => $output,
                    'error_output' => $errorOutput,
                    'json_error' => json_last_error_msg(),
                ]);
                $evidence->update(['status' => 'incomplete']);
                return false;
            }

            $this->applyOcrResultToEvidence($evidence, $parsed, 'local');

            return true;
        } catch (\Throwable $exception) {
            \Log::error('Excepción durante el proceso asíncrono de OCR Yape.', [
                'evidence_id' => $evidence->id,
                'error' => $exception->getMessage(),
            ]);
            $evidence->update(['status' => 'parsed']);
            return false;
        }
    }

    protected function enrichEvidenceWithRemoteOcr(YapeClientEvidence $evidence): bool
    {
        $disk = config('billing.yape.storage_disk', 'local');
        $remoteUrl = trim((string) config('billing.yape.ocr_remote_url'));
        $remoteSecret = (string) config('billing.yape.ocr_remote_secret');

        if ($remoteUrl === '' || $remoteSecret === '') {
            \Log::warning('OCR remoto Yape no configurado.', [
                'evidence_id' => $evidence->id,
                'remote_url_configured' => $remoteUrl !== '',
                'remote_secret_configured' => $remoteSecret !== '',
            ]);
            $evidence->update(['status' => 'incomplete']);
            return false;
        }

        if (!Storage::disk($disk)->exists($evidence->image_path)) {
            $this->appendYapeDirectDebug('yape_ocr_remote_request', [
                'mode' => 'remote',
                'url' => $remoteUrl,
                'image_path' => $evidence->image_path,
                'image_exists' => false,
                'image_size_bytes' => null,
                'secret_present' => $remoteSecret !== '',
                'error' => 'image_not_found',
            ]);

            \Log::warning('No se pudo ejecutar OCR remoto: la imagen de evidencia no existe.', [
                'evidence_id' => $evidence->id,
                'image_path' => $evidence->image_path,
            ]);
            $evidence->update(['status' => 'incomplete']);
            return false;
        }

        $imageSize = Storage::disk($disk)->size($evidence->image_path);

        $this->appendYapeDirectDebug('yape_ocr_remote_request', [
            'evidence_id' => $evidence->id,
            'chat_id' => $evidence->source_chat_name,
            'mode' => 'remote',
            'url' => $remoteUrl,
            'image_path' => $evidence->image_path,
            'image_exists' => true,
            'image_size_bytes' => $imageSize,
            'secret_present' => $remoteSecret !== '',
        ]);

        $imageBinary = Storage::disk($disk)->get($evidence->image_path);
        $filename = basename((string) $evidence->image_path) ?: 'yape-evidence.png';
        $timeoutSeconds = $this->remoteOcrTimeoutSeconds();

        try {
            $response = Http::withHeaders([
                'X-Yape-Ocr-Secret' => $remoteSecret,
            ])
                ->connectTimeout(min(3, $timeoutSeconds))
                ->timeout($timeoutSeconds)
                ->attach('image', $imageBinary, $filename)
                ->post($remoteUrl, [
                    'mode' => 'client',
                    'evidence_id' => (string) $evidence->id,
                ]);

            $parsed = $response->json();
            $summary = is_array($parsed)
                ? $this->summarizeOcrResult($parsed)
                : ['raw_body_length' => mb_strlen($response->body())];

            $this->appendYapeDirectDebug('yape_ocr_remote_response', array_merge([
                'http_status' => $response->status(),
                'error' => null,
                'message' => null,
            ], $summary));

            $this->logOpenWaYape('info', 'yape_ocr_remote_response', [
                'evidence_id' => $evidence->id,
                'status' => $response->status(),
                'image_path' => $evidence->image_path,
                'ocr_response' => $summary,
            ]);

            if (!$response->successful() || !$this->isUsableOcrResult($parsed)) {
                $this->appendYapeDirectDebug('yape_validation_decision', [
                    'capture_invalid' => true,
                    'reason' => !$response->successful() ? 'ocr_http_failed' : 'ocr_not_usable',
                    'threshold' => (float) config('billing.yape.confidence_threshold', 0.88),
                    'confidence' => is_array($parsed) ? ($parsed['confidence'] ?? null) : null,
                    'amount' => is_array($parsed) ? ($parsed['amount'] ?? null) : null,
                    'operation_code' => is_array($parsed) ? ($parsed['operation_code'] ?? null) : null,
                    'security_code' => is_array($parsed) ? ($parsed['security_code'] ?? null) : null,
                    'should_create_pending' => false,
                    'pending_id' => null,
                    'intent_id' => null,
                ]);

                $this->logOpenWaYape('warning', 'yape_ocr_remote_invalid', [
                    'evidence_id' => $evidence->id,
                    'status' => $response->status(),
                    'ocr_response' => $summary,
                ]);
                $evidence->update(['status' => 'incomplete']);
                return false;
            }

            $this->applyOcrResultToEvidence($evidence, $parsed, 'remote');

            return true;
        } catch (ConnectionException $exception) {
            $this->appendYapeDirectDebug('yape_ocr_remote_timeout', [
                'evidence_id' => $evidence->id,
                'chat_id' => $evidence->source_chat_name,
                'image_path' => $evidence->image_path,
                'timeout_seconds' => $timeoutSeconds,
                'error' => $exception->getMessage(),
            ]);

            $this->appendYapeDirectDebug('yape_ocr_remote_response', [
                'http_status' => null,
                'ok' => null,
                'confidence' => null,
                'amount' => null,
                'operation_code' => null,
                'security_code' => null,
                'raw_text_length' => null,
                'error' => $exception->getMessage(),
                'message' => 'remote_ocr_timeout',
            ]);

            \Log::warning('Timeout durante OCR remoto Yape.', [
                'evidence_id' => $evidence->id,
                'image_path' => $evidence->image_path,
                'timeout_seconds' => $timeoutSeconds,
                'error' => $exception->getMessage(),
            ]);

            $evidence->update(['status' => 'incomplete']);
            return false;
        } catch (\Throwable $exception) {
            $this->appendYapeDirectDebug('yape_ocr_remote_response', [
                'http_status' => null,
                'ok' => null,
                'confidence' => null,
                'amount' => null,
                'operation_code' => null,
                'security_code' => null,
                'raw_text_length' => null,
                'error' => $exception->getMessage(),
                'message' => 'remote_ocr_exception',
            ]);

            \Log::error('Excepción durante OCR remoto Yape.', [
                'evidence_id' => $evidence->id,
                'image_path' => $evidence->image_path,
                'error' => $exception->getMessage(),
            ]);
            $evidence->update(['status' => 'incomplete']);
            return false;
        }
    }

    protected function remoteOcrTimeoutSeconds(): int
    {
        $configured = (int) config(
            'billing.yape.ocr_remote_timeout',
            config('billing.yape.openwa.ocr_timeout', 8)
        );

        return max(3, min($configured ?: 8, 10));
    }

    protected function isUsableOcrResult(mixed $parsed): bool
    {
        if (!is_array($parsed) || empty($parsed['ok'])) {
            return false;
        }

        $rawText = trim((string) ($parsed['raw_text'] ?? ''));
        $confidence = (float) ($parsed['confidence'] ?? 0);
        $hasAmount = isset($parsed['amount']) && (float) $parsed['amount'] > 0;
        $hasOperationCode = $this->normalizeOperationCode($parsed['operation_code'] ?? null) !== null;
        $hasSecurityCode = $this->normalizeSecurityCode($parsed['security_code'] ?? null) !== null;

        return $rawText !== ''
            && $confidence >= $this->confidenceThreshold()
            && $this->rawTextHasYapeSignals($rawText)
            && ($hasAmount || $hasOperationCode || $hasSecurityCode);
    }

    public function clientEvidenceIsStrictValidYapeCapture(YapeClientEvidence $evidence): bool
    {
        return trim((string) $evidence->raw_text) !== ''
            && (float) $evidence->confidence >= $this->confidenceThreshold()
            && $this->rawTextHasYapeSignals((string) $evidence->raw_text)
            && (
                (float) $evidence->parsed_amount > 0
                || (string) $evidence->operation_code !== ''
                || (string) $evidence->security_code !== ''
            );
    }

    protected function confidenceThreshold(): float
    {
        return (float) config('billing.yape.confidence_threshold', 0.88);
    }

    protected function rawTextHasYapeSignals(string $rawText): bool
    {
        $text = mb_strtolower($rawText);

        foreach ([
            'yape',
            'yapeaste',
            'destino yape',
            'nro. de operación',
            'nro de operación',
            'nro. de operacion',
            'nro de operacion',
            'código de seguridad',
            'codigo de seguridad',
            'datos de la transacción',
            'datos de la transaccion',
        ] as $signal) {
            if (str_contains($text, mb_strtolower($signal))) {
                return true;
            }
        }

        return false;
    }

    protected function applyOcrResultToEvidence(YapeClientEvidence $evidence, array $parsed, string $source): void
    {
        $operationCode = $this->normalizeOperationCode($parsed['operation_code'] ?? null);
        $securityCode = $this->normalizeClientEvidenceSecurityCode(
            $this->normalizeSecurityCode($parsed['security_code'] ?? null),
            $operationCode
        );
        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $payload['ocr'] = [
            'source' => $source,
            'processed_at' => now()->toDateTimeString(),
            'response' => $parsed,
        ];

        $evidence->update([
            'raw_text' => $parsed['raw_text'] ?? $evidence->raw_text,
            'parsed_amount' => isset($parsed['amount']) ? round((float) $parsed['amount'], 2) : $evidence->parsed_amount,
            'operation_code' => $operationCode ?: $evidence->operation_code,
            'security_code' => $securityCode ?: $evidence->security_code,
            'occurred_at' => $this->parseDateTime($parsed['occurred_at'] ?? null) ?: $evidence->occurred_at,
            'receiver_name' => $parsed['receiver_name'] ?? $evidence->receiver_name,
            'receiver_phone' => $this->normalizePhone((string) ($parsed['receiver_phone'] ?? '')) ?: $evidence->receiver_phone,
            'confidence' => isset($parsed['confidence']) ? round((float) $parsed['confidence'], 2) : $evidence->confidence,
            'status' => 'parsed',
            'payload' => $payload,
        ]);
    }

    protected function summarizeOcrResult(array $parsed): array
    {
        return [
            'ok' => $parsed['ok'] ?? null,
            'confidence' => $parsed['confidence'] ?? null,
            'amount' => $parsed['amount'] ?? null,
            'operation_code' => $parsed['operation_code'] ?? null,
            'security_code' => $parsed['security_code'] ?? null,
            'raw_text_length' => isset($parsed['raw_text']) ? mb_strlen((string) $parsed['raw_text']) : null,
        ];
    }

    protected function customerHasPendingDebt(Customer $customer): bool
    {
        return Invoice::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('contract', function ($query) use ($customer) {
                $query->where('customer_id', $customer->id);
            })
            ->exists();
    }

    protected function logOpenWaYape(string $level, string $message, array $context = []): void
    {
        try {
            \Log::channel('openwa_yape')->{$level}($message, $context);
        } catch (\Throwable) {
            \Log::{$level}($message, $context);
        }
    }

    protected function appendYapeDirectDebug(string $event, array $context = []): void
    {
        try {
            file_put_contents(
                storage_path('logs/openwa-yape-direct-debug.log'),
                json_encode(array_merge([
                    'event' => $event,
                    'date' => now()->toDateTimeString(),
                ], $context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
            // La traza directa nunca debe romper conciliación.
        }
    }

    public function processEvidenceReconciliation(YapeClientEvidence $evidence): YapeClientEvidence
    {
        $evidence = $evidence->fresh(['intent']) ?: $evidence;

        $this->appendYapeDirectDebug('yape_reconciliation_evidence_selected', [
            'step' => 'start',
            'current_evidence_id' => $evidence->id,
            'selected_evidence_id' => $evidence->id,
            'selection_reason' => 'incoming_evidence',
            'status' => $evidence->status,
            'source_message_id' => $evidence->source_message_id,
            'source_chat_name' => $evidence->source_chat_name,
            'whatsapp_phone' => $evidence->whatsapp_phone,
            'amount' => $evidence->parsed_amount,
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
        ]);

        if ($evidence->status === 'duplicate') {
            $payload = is_array($evidence->payload) ? $evidence->payload : [];
            $originalId = (int) ($payload['duplicate_of_evidence_id'] ?? 0);
            $original = $originalId > 0
                ? YapeClientEvidence::with('intent')->find($originalId)
                : null;

            $this->appendYapeDirectDebug('yape_reconciliation_evidence_selected', [
                'step' => 'existing_duplicate_status',
                'current_evidence_id' => $evidence->id,
                'selected_evidence_id' => $original?->id ?: $evidence->id,
                'selection_reason' => $original ? 'incoming_already_marked_duplicate' : 'duplicate_original_missing',
                'duplicate_of_evidence_id' => $originalId ?: null,
                'selected_status' => $original?->status ?: $evidence->status,
            ]);

            return $original ?: $evidence;
        }

        if ($evidence->yape_payment_intent_id) {
            $intent = $evidence->intent;

            if ($intent) {
                $this->maybeReconcile($this->attachExistingEvidence($intent->fresh()));
            }

            return $evidence->fresh(['intent']);
        }

        if ($duplicate = $this->findDuplicateClientEvidence([
            'amount' => $evidence->parsed_amount,
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
            'whatsapp_phone' => $evidence->whatsapp_phone,
            'source_chat_name' => $evidence->source_chat_name,
            'payload' => $evidence->payload,
        ], $evidence->id)) {
            $payload = is_array($evidence->payload) ? $evidence->payload : [];
            $payload['possible_duplicate'] = [
                'of_evidence_id' => $duplicate->id,
                'reason' => 'payment_fingerprint_after_ocr',
                'detected_at' => now()->toDateTimeString(),
                'duplicate_status' => $duplicate->status,
                'duplicate_intent_id' => $duplicate->yape_payment_intent_id,
                'duplicate_source_message_id' => $duplicate->source_message_id,
                'duplicate_source_chat_name' => $duplicate->source_chat_name,
            ];
            $evidence->update(['payload' => $payload]);

            $this->markClientEvidenceDuplicateHit($duplicate, [
                'source_message_id' => $evidence->source_message_id,
                'source_chat_name' => $evidence->source_chat_name,
            ], 'payment_fingerprint_after_ocr_candidate');

            $this->appendYapeDirectDebug('yape_reconciliation_evidence_selected', [
                'step' => 'payment_fingerprint_after_ocr',
                'current_evidence_id' => $evidence->id,
                'selected_evidence_id' => $evidence->id,
                'candidate_duplicate_evidence_id' => $duplicate->id,
                'selection_reason' => 'new_openwa_message_keeps_current_evidence_for_dni_prompt',
                'candidate_duplicate_status' => $duplicate->status,
                'candidate_duplicate_intent_id' => $duplicate->yape_payment_intent_id,
                'candidate_duplicate_has_dni_prompt' => !empty(data_get($duplicate->payload, 'openwa_messages.dni_prompt.sent_at')),
                'current_source_message_id' => $evidence->source_message_id,
                'candidate_source_message_id' => $duplicate->source_message_id,
            ]);

            $evidence = $evidence->fresh(['intent']) ?: $evidence;
        }

        $intent = $this->findIntentForClientEvidence($evidence);

        if ($intent) {
            $attached = $this->attachClientEvidenceToIntent($evidence, $intent);

            $this->appendYapeDirectDebug('yape_reconciliation_evidence_selected', [
                'step' => 'attached_intent',
                'current_evidence_id' => $evidence->id,
                'selected_evidence_id' => $attached->id,
                'selection_reason' => 'matched_pending_intent_for_current_evidence',
                'intent_id' => $attached->yape_payment_intent_id,
                'intent_status' => $attached->intent?->status,
            ]);

            return $attached;
        }

        $selected = $evidence->fresh(['intent']);

        $this->appendYapeDirectDebug('yape_reconciliation_evidence_selected', [
            'step' => 'finish_without_intent',
            'current_evidence_id' => $evidence->id,
            'selected_evidence_id' => $selected?->id ?: $evidence->id,
            'selection_reason' => 'no_matching_intent_keep_current_evidence',
            'status' => $selected?->status ?: $evidence->status,
        ]);

        return $selected;
    }
}
