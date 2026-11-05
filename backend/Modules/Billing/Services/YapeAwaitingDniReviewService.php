<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;
use Modules\Billing\Entities\YapeReceiverEvent;
use Modules\Customer\Entities\Customer;

/**
 * Read-only projection for support staff. It never creates or changes Yape records.
 */
class YapeAwaitingDniReviewService
{
    private const DNI_WINDOW_HOURS = 2;
    private const OPERATION_TOLERANCE_SECONDS = 120;

    /** @var array<string, object> */
    private array $phoneResolutionCache = [];
    private Collection $batchChatEvidences;
    private Collection $batchIntents;
    private Collection $batchReceiverEvents;
    private Collection $batchCustomers;
    private Collection $batchPayments;

    public function build(
        string $search = '',
        string $caseStatus = 'all',
        int $page = 1,
        int $perPage = 15
    ): array
    {
        $this->phoneResolutionCache = [];
        $this->resetBatchContext();
        $page = max($page, 1);
        $perPage = in_array($perPage, [10, 15, 20], true) ? $perPage : 15;

        $groups = $this->groupEquivalentEvidences($this->awaitingDniEvidences());

        // Every counter and the visible page must describe the same population.
        // Projecting only the current page made the summary cards contradict the
        // total printed next to them, and the numbers changed on every page turn.
        $this->prepareBatchContext($groups);

        $allCases = $groups
            ->map(fn (Collection $group) => $this->buildCase($group))
            ->filter(fn (object $case) => $this->matchesSearch($case, $search))
            ->filter(fn (object $case) => $this->matchesStatus($case, $caseStatus))
            ->values();

        $total = $allCases->count();
        $cases = $allCases->forPage($page, $perPage)->values();
        $summaryCases = $allCases;

        $paginator = new LengthAwarePaginator(
            $cases,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => 'case_page',
            ]
        );

        $lidAudit = $this->lidAudit();

        return [
            'cases' => $cases,
            'paginator' => $paginator,
            'summary' => [
                'operations' => $total,
                'physical_captures' => $summaryCases->sum('physicalEvidenceCount'),
                'ready' => $summaryCases->where('state.key', 'ready')->count(),
                'review' => $summaryCases->filter(fn (object $case) => in_array($case->state->key, ['review', 'awaiting_dni'], true))->count(),
                'unidentified' => $summaryCases->where('state.key', 'unidentified')->count(),
                'processed' => $summaryCases->where('state.key', 'processed')->count(),
                'with_candidate' => $summaryCases->filter(fn (object $case) => $case->candidates->isNotEmpty())->count(),
                'expired_window' => $summaryCases->filter(fn (object $case) => !$case->dniWindowActive)->count(),
                'lid_evidences' => $lidAudit['lid_evidences'],
                'lid_digits_as_phone' => $lidAudit['lid_digits_as_phone'],
                'lid_unassociated' => $lidAudit['lid_unassociated'],
            ],
        ];
    }

    /**
     * Captures still waiting for the DNI that identifies their customer.
     *
     * An intent can be attached as soon as Android and WhatsApp match, but the
     * identification is not complete until the DNI resolution is recorded, so
     * those captures stay actionable here.
     */
    private function awaitingDniEvidences(array $columns = ['*']): Collection
    {
        return YapeClientEvidence::query()
            ->whereIn('status', ['parsed', 'ambiguous', 'attached'])
            ->where('payload->yape_chat_state->state', 'awaiting_dni')
            ->latest()
            ->get($columns);
    }

    /**
     * Cases waiting for DNI, counted the way this tab actually groups them:
     * resends of the same operation collapse into a single case.
     *
     * The tab badge used to count raw evidence rows, so it announced a different
     * number than the list it links to. Only the columns the grouping rules read
     * are selected, which keeps the badge cheap for the default landing tab.
     */
    public function countCases(): int
    {
        return $this->groupEquivalentEvidences(
            $this->awaitingDniEvidences([
                'id',
                'operation_code',
                'security_code',
                'parsed_amount',
                'source_chat_name',
                'occurred_at',
                'created_at',
            ])
        )->count();
    }

    /**
     * Every evidence belonging to the same case as the given one, resends included.
     *
     * Closing only the primary evidence would leave its resends awaiting DNI, and
     * they would regroup into a brand new case on the next page load.
     *
     * @return Collection<int, YapeClientEvidence>
     */
    public function caseEvidencesFor(YapeClientEvidence $evidence): Collection
    {
        $group = $this->groupEquivalentEvidences($this->awaitingDniEvidences())
            ->first(fn (Collection $candidate) => $candidate->contains(
                fn (YapeClientEvidence $item) => (int) $item->id === (int) $evidence->id
            ));

        return $group && $group->isNotEmpty() ? $group->values() : collect([$evidence]);
    }

    private function resetBatchContext(): void
    {
        $this->batchChatEvidences = collect();
        $this->batchIntents = collect();
        $this->batchReceiverEvents = collect();
        $this->batchCustomers = collect();
        $this->batchPayments = collect();
    }

    private function prepareBatchContext(Collection $groups): void
    {
        $evidences = $groups->flatten(1)->filter();

        if ($evidences->isEmpty()) {
            return;
        }

        $chatIds = $evidences->pluck('source_chat_name')->filter()->unique()->values();
        $operations = $evidences->pluck('operation_code')->filter()->unique()->values();
        $amounts = $evidences->pluck('parsed_amount')->filter()->map(fn ($amount) => round((float) $amount, 2))->unique()->values();
        $times = $evidences->map(fn (YapeClientEvidence $evidence) => $this->evidenceTime($evidence));
        $from = $times->min()?->copy()->subMinutes(5);
        $to = $times->max()?->copy()->addMinutes(5);

        $this->batchChatEvidences = YapeClientEvidence::query()
            ->whereIn('source_chat_name', $chatIds)
            ->get(['id', 'customer_id', 'source_chat_name', 'whatsapp_phone', 'payload', 'created_at']);

        if ($operations->isNotEmpty() || $chatIds->isNotEmpty()) {
            $this->batchIntents = YapePaymentIntent::query()
                ->with(['clientEvidence:id,source_chat_name', 'payment'])
                ->where(function ($query) use ($operations, $chatIds) {
                    if ($operations->isNotEmpty()) {
                        $query->whereIn('operation_code', $operations);
                    }

                    if ($chatIds->isNotEmpty()) {
                        $method = $operations->isNotEmpty() ? 'orWhereHas' : 'whereHas';
                        $query->{$method}('clientEvidence', fn ($evidence) => $evidence->whereIn('source_chat_name', $chatIds));
                        $query->orWhereIn('source_payload->source_chat_name', $chatIds);
                    }
                })
                ->get();
        }

        if ($amounts->isNotEmpty() && $from && $to) {
            $this->batchReceiverEvents = YapeReceiverEvent::query()
                ->whereIn('parsed_amount', $amounts)
                ->whereBetween('occurred_at', [$from, $to])
                ->oldest('occurred_at')
                ->get();
        }

        if ($operations->isNotEmpty()) {
            $this->batchPayments = Payment::query()
                ->with('invoice.contract.customer')
                ->where('status', 'completed')
                ->where(function ($query) use ($operations) {
                    $query->whereIn('reference', $operations)
                        ->orWhereIn('payment_details->operation_code', $operations);
                })
                ->latest('payment_date')
                ->get();
        }

        $customerIds = $this->batchChatEvidences->pluck('customer_id')
            ->merge($this->batchIntents->pluck('customer_id'))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $dnis = $this->batchChatEvidences
            ->map(fn (YapeClientEvidence $evidence) => preg_replace('/\D+/', '', (string) data_get($evidence->payload, 'dni_resolution.last_dni')))
            ->filter(fn (string $dni) => strlen($dni) === 8)->unique()->values();
        $phones = $groups->map(function (Collection $group) {
            $primary = $this->sortEvidences($group)->first();
            return $this->phoneResolutionFor($primary, $group)->phone ?? null;
        })->filter()->flatMap(fn (string $phone) => $this->phoneVariants($phone))->unique()->values();

        if ($customerIds->isEmpty() && $dnis->isEmpty() && $phones->isEmpty()) {
            return;
        }

        $this->batchCustomers = Customer::query()
            ->where(function ($query) use ($customerIds, $dnis, $phones) {
                $hasClause = false;
                foreach ([['id', $customerIds], ['identity_document', $dnis], ['phone', $phones]] as [$column, $values]) {
                    if ($values->isEmpty()) {
                        continue;
                    }
                    $hasClause ? $query->orWhereIn($column, $values) : $query->whereIn($column, $values);
                    $hasClause = true;
                }
            })
            ->with([
                'contracts' => fn ($query) => $query->where('status', 'active'),
                'contracts.invoices' => fn ($query) => $query
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->with(['payments', 'creditNotes']),
            ])
            ->get()
            ->keyBy('id');
    }

    private function groupEquivalentEvidences(Collection $evidences): Collection
    {
        return $evidences
            ->groupBy(fn (YapeClientEvidence $evidence) => $this->operationFingerprint($evidence))
            ->flatMap(function (Collection $fingerprintEvidences) {
                $clusters = collect();

                foreach ($this->sortEvidences($fingerprintEvidences) as $evidence) {
                    $clusterIndex = $clusters->search(function (Collection $cluster) use ($evidence) {
                        $primary = $cluster->first();

                        return $this->sameChat($primary, $evidence)
                            && abs($this->evidenceTime($primary)->getTimestamp() - $this->evidenceTime($evidence)->getTimestamp()) <= self::OPERATION_TOLERANCE_SECONDS;
                    });

                    if ($clusterIndex === false) {
                        $clusters->push(collect([$evidence]));
                        continue;
                    }

                    $clusters->get($clusterIndex)->push($evidence);
                }

                return $clusters;
            })
            ->values();
    }

    private function buildCase(Collection $evidences): object
    {
        $ordered = $this->sortEvidences($evidences)->values();
        $primary = $ordered->first();
        $latest = $ordered->last();
        $receiverEvents = $this->receiverEventsFor($primary, $ordered);
        $phoneResolution = $this->phoneResolutionFor($primary, $ordered);
        $candidates = $this->candidatesFor($primary, $phoneResolution);
        $deadline = optional($latest->created_at)->copy()->addHours(self::DNI_WINDOW_HOURS);
        $primaryCandidate = $candidates->count() === 1 ? $candidates->first() : null;
        $proposedInvoice = $primaryCandidate?->compatibleInvoices->count() === 1
            ? $primaryCandidate->compatibleInvoices->first()
            : null;
        $compatibleReceiverEvents = $receiverEvents
            ->filter(fn (YapeReceiverEvent $event) => $this->receiverIsStrictlyCompatible($event, $primary))
            ->values();
        $receiverEvent = $compatibleReceiverEvents->count() === 1
            ? $compatibleReceiverEvents->first()
            : null;
        $existingIntent = $this->existingIntentFor($primary);
        $existingPayments = $this->existingPaymentsFor($primary, $primaryCandidate);

        $case = (object) [
            'primaryEvidence' => $primary,
            'resends' => $ordered->skip(1)->values(),
            'physicalEvidenceCount' => $ordered->count(),
            'operationCode' => $primary->operation_code,
            'securityCode' => $primary->security_code,
            'amount' => (float) $primary->parsed_amount,
            'occurredAt' => $primary->occurred_at,
            'chatId' => $primary->source_chat_name,
            'phoneResolution' => $phoneResolution,
            'receiverEvents' => $receiverEvents,
            'receiverEvent' => $receiverEvent,
            'candidates' => $candidates,
            'primaryCandidate' => $primaryCandidate,
            'proposedInvoice' => $proposedInvoice,
            'existingIntent' => $existingIntent,
            'existingPayments' => $existingPayments,
            'ambiguous' => $phoneResolution->ambiguous || $candidates->count() > 1,
            'dniWindowDeadline' => $deadline,
            'dniWindowActive' => $deadline?->isFuture() ?? false,
            'duplicateCapture' => $ordered->count() > 1,
        ];

        $case->state = $this->stateFor($case);
        $case->canResume = !$case->existingIntent
            && $case->existingPayments->isEmpty()
            && (float) $primary->parsed_amount > 0
            && ((string) $primary->operation_code !== '' || (string) $primary->security_code !== '');

        return $case;
    }

    private function stateFor(object $case): object
    {
        if ($case->existingIntent?->payment_id || $case->existingPayments->isNotEmpty()) {
            return $this->state('processed', 'Ya procesado', 'Esta operación parece haber sido registrada anteriormente.', 'Consulta el pago existente.');
        }

        if ($case->existingIntent) {
            return $this->state('processed', 'Ya procesado', 'Esta operación ya tiene una validación Yape.', 'Abre la validación existente.');
        }

        if ($case->candidates->isEmpty()) {
            return $this->state('unidentified', 'Cliente no identificado', 'No fue posible identificar con seguridad al cliente.', 'Busca al cliente o espera el DNI.');
        }

        if ($case->ambiguous) {
            return $this->state('review', 'Necesita revisión', 'Hay más de un cliente posible y se necesita una decisión manual.', 'No valides hasta confirmar al cliente.');
        }

        if ($case->primaryCandidate && !$case->proposedInvoice) {
            return $this->state('review', 'Requiere revisión', 'El posible cliente tiene deuda, pero el monto no coincide exactamente con un recibo.', 'Ingresa el DNI; el flujo Yape mantendrá el caso para revisión normal.');
        }

        if ($case->receiverEvent && $case->primaryCandidate?->confidence === 'alta') {
            return $this->state('ready', 'Listo para reanudar', 'La captura, el receptor y un posible cliente coinciden.', 'Ingresa el DNI para continuar en el flujo Yape normal.');
        }

        return $this->state('awaiting_dni', 'Esperando DNI', 'La captura está guardada, pero todavía necesita identificación o revisión.', 'Ingresa el DNI para reanudar el flujo normal.');
    }

    private function state(string $key, string $label, string $explanation, string $recommendation): object
    {
        return (object) compact('key', 'label', 'explanation', 'recommendation');
    }

    private function existingIntentFor(YapeClientEvidence $evidence): ?YapePaymentIntent
    {
        if (!$evidence->operation_code) {
            return null;
        }

        return $this->batchIntents
            ->where('operation_code', $evidence->operation_code)
            ->filter(fn (YapePaymentIntent $intent) => $intent->payment_id
                || $intent->status === YapePaymentIntent::STATUS_VALIDATED)
            ->sortByDesc('id')
            ->first();
    }

    private function existingPaymentsFor(YapeClientEvidence $evidence, ?object $candidate): Collection
    {
        if (!$evidence->operation_code) {
            return collect();
        }

        return $this->batchPayments
            ->filter(function (Payment $payment) use ($evidence, $candidate) {
                $operation = (string) (data_get($payment->payment_details, 'operation_code') ?: $payment->reference);

                return $operation === (string) $evidence->operation_code
                    && round((float) $payment->amount, 2) === round((float) $evidence->parsed_amount, 2)
                    && (!$candidate || (int) $payment->invoice?->contract?->customer_id === (int) $candidate->customerId);
            })
            ->take(5)
            ->values();
    }

    private function candidatesFor(YapeClientEvidence $primary, object $phoneResolution): Collection
    {
        $signals = [];
        $chatId = trim((string) $primary->source_chat_name);
        $chatEvidences = collect();

        if ($chatId !== '') {
            $chatEvidences = $this->batchChatEvidences
                ->filter(fn (YapeClientEvidence $evidence) => (string) $evidence->source_chat_name === $chatId)
                ->values();

            foreach ($chatEvidences->whereNotNull('customer_id') as $evidence) {
                $this->addSignal($signals, (int) $evidence->customer_id, 'history', 'Coincidencia por historial del mismo chat', 100);
            }

            $historicalIntents = $this->batchIntents
                ->filter(fn (YapePaymentIntent $intent) => $intent->customer_id
                    && ((string) data_get($intent->source_payload, 'source_chat_name') === $chatId
                        || (string) $intent->clientEvidence?->source_chat_name === $chatId));

            foreach ($historicalIntents as $intent) {
                $this->addSignal($signals, (int) $intent->customer_id, 'intent_history', 'Coincidencia por historial del mismo chat', 95);
            }

            $dnis = $chatEvidences
                ->map(fn (YapeClientEvidence $evidence) => preg_replace('/\D+/', '', (string) data_get($evidence->payload, 'dni_resolution.last_dni')))
                ->filter(fn (string $dni) => strlen($dni) === 8)
                ->unique()
                ->values();

            foreach ($this->batchCustomers->filter(fn (Customer $customer) => $dnis->contains((string) $customer->identity_document)) as $customer) {
                $this->addSignal($signals, (int) $customer->id, 'dni', 'Coincidencia por DNI recibido', 90);
            }
        }

        if ($phoneResolution->resolved) {
            $variants = $this->phoneVariants($phoneResolution->phone);

            foreach ($this->batchCustomers->filter(fn (Customer $customer) => $variants->contains((string) $customer->phone)) as $customer) {
                $this->addSignal(
                    $signals,
                    (int) $customer->id,
                    'confirmed_phone',
                    $phoneResolution->reason,
                    100
                );
            }
        }

        if (empty($signals)) {
            return collect();
        }

        // EloquentCollection::only() reindexes the selected models from zero.
        // Keep the ID-keyed batch intact because the signal map is keyed by customer ID.
        $customers = $this->batchCustomers;

        return collect($signals)
            ->map(function (array $candidateSignals, int $customerId) use ($customers, $primary) {
                $customer = $customers->get($customerId);

                if (!$customer) {
                    return null;
                }

                $invoices = $customer->contracts->flatMap->invoices;
                $debt = round((float) $invoices->sum(fn (Invoice $invoice) => $this->pendingAmount($invoice)), 2);
                $compatibleInvoices = $invoices
                    ->filter(fn (Invoice $invoice) => round($this->pendingAmount($invoice), 2) === round((float) $primary->parsed_amount, 2))
                    ->values();
                $score = max(array_column($candidateSignals, 'score'));
                $confidence = $this->confidenceFor($score, $debt, $compatibleInvoices->isNotEmpty());
                $reasons = collect($candidateSignals)->pluck('reason')->unique()->values();

                if ($debt <= 0) {
                    $reasons->push('Cliente sin deuda pendiente; requiere revisión');
                } elseif ($compatibleInvoices->isEmpty()) {
                    $reasons->push('No hay factura pendiente compatible con el monto');
                }

                return (object) [
                    'customer' => $customer,
                    'customerId' => $customer->id,
                    'name' => $customer->full_name,
                    'maskedDni' => $this->mask($customer->identity_document, 2),
                    'maskedPhone' => $this->mask($customer->phone, 3),
                    'activeContracts' => $customer->contracts,
                    'debt' => $debt,
                    'compatibleInvoices' => $compatibleInvoices,
                    'reasons' => $reasons,
                    'confidence' => $confidence,
                ];
            })
            ->filter()
            ->sortByDesc(fn (object $candidate) => $this->confidenceWeight($candidate->confidence))
            ->values();
    }

    private function receiverEventsFor(YapeClientEvidence $primary, Collection $evidences): Collection
    {
        $from = $evidences->map(fn (YapeClientEvidence $evidence) => $this->evidenceTime($evidence))->min();
        $to = $evidences->map(fn (YapeClientEvidence $evidence) => $this->evidenceTime($evidence))->max();

        return $this->batchReceiverEvents
            ->filter(function (YapeReceiverEvent $event) use ($primary, $from, $to) {
                $fingerprintMatches = ($primary->operation_code && $event->operation_code === $primary->operation_code)
                    || ($primary->security_code && $event->security_code === $primary->security_code);

                return round((float) $event->parsed_amount, 2) === round((float) $primary->parsed_amount, 2)
                    && $event->occurred_at
                    && $event->occurred_at->betweenIncluded($from->copy()->subMinutes(5), $to->copy()->addMinutes(5))
                    && $fingerprintMatches;
            })
            ->values();
    }

    private function receiverIsStrictlyCompatible(YapeReceiverEvent $event, YapeClientEvidence $evidence): bool
    {
        if ($event->yape_payment_intent_id
            || abs(round((float) $event->parsed_amount, 2) - round((float) $evidence->parsed_amount, 2)) >= 0.01
            || !$evidence->security_code
            || !$event->security_code
            || trim((string) $evidence->security_code) !== trim((string) $event->security_code)) {
            return false;
        }

        if ($event->operation_code && $evidence->operation_code
            && trim((string) $event->operation_code) !== trim((string) $evidence->operation_code)) {
            return false;
        }

        return !$event->occurred_at || !$evidence->occurred_at
            || abs($evidence->occurred_at->diffInMinutes($event->occurred_at, false)) <= 5;
    }

    private function phoneResolutionFor(YapeClientEvidence $primary, Collection $group): object
    {
        $chatId = strtolower(trim((string) $primary->source_chat_name));

        if ($chatId !== '' && isset($this->phoneResolutionCache[$chatId])) {
            return $this->phoneResolutionCache[$chatId];
        }

        $unresolved = (object) [
            'resolved' => false,
            'ambiguous' => false,
            'phone' => null,
            'maskedPhone' => null,
            'source' => null,
            'reason' => str_ends_with($chatId, '@lid')
                ? 'Chat LID sin teléfono resuelto'
                : 'Teléfono no resuelto',
            'confidence' => null,
            'session' => null,
            'firstConfirmedAt' => null,
            'lastConfirmedAt' => null,
        ];

        if ($chatId === '') {
            return $unresolved;
        }

        $chatEvidences = $this->batchChatEvidences
            ->filter(fn (YapeClientEvidence $evidence) => (string) $evidence->source_chat_name === (string) $primary->source_chat_name)
            ->values();

        $payloadMappings = $chatEvidences
            ->concat($group)
            ->unique('id')
            ->map(fn (YapeClientEvidence $evidence) => $this->confirmedPhoneResolutionFromEvidence($evidence))
            ->filter()
            ->values();

        $phones = $payloadMappings->pluck('phone')->unique()->values();

        if ($phones->count() > 1) {
            $unresolved->ambiguous = true;
            $unresolved->reason = 'Mapeo LID ambiguo — no continuar automáticamente';

            return $this->phoneResolutionCache[$chatId] = $unresolved;
        }

        if ($payloadMappings->isNotEmpty()) {
            $first = $payloadMappings->sortBy('confirmed_at')->first();
            $last = $payloadMappings->sortByDesc('confirmed_at')->first();
            $phone = $phones->first();

            return $this->phoneResolutionCache[$chatId] = (object) [
                'resolved' => true,
                'ambiguous' => false,
                'phone' => $phone,
                'maskedPhone' => $this->mask($phone, 6),
                'source' => $last['source'],
                'reason' => str_ends_with($chatId, '@lid')
                    ? 'Teléfono confirmado por OpenWA para el mismo chat LID'
                    : 'Coincidencia por teléfono confirmado',
                'confidence' => 'high',
                'session' => $last['session'],
                'firstConfirmedAt' => $first['confirmed_at'],
                'lastConfirmedAt' => $last['confirmed_at'],
            ];
        }

        // This administrative page must never block on OpenWA. A batch of
        // unresolved LID chats previously issued one remote HTTP request per
        // case, making the page wait for multiple timeouts. Only identities
        // already persisted with the evidence are considered here.
        return $this->phoneResolutionCache[$chatId] = $unresolved;
    }

    private function confirmedPhoneResolutionFromEvidence(YapeClientEvidence $evidence): ?array
    {
        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $chatId = strtolower(trim((string) $evidence->source_chat_name));

        if (str_ends_with($chatId, '@c.us')) {
            $phone = $this->normalizeConfirmedPhone($chatId);

            return $phone ? [
                'phone' => $phone,
                'source' => 'chat_id_c_us',
                'session' => data_get($payload, 'whatsapp_phone_resolution.session')
                    ?? data_get($payload, 'openwa.sessionId'),
                'confirmed_at' => optional($evidence->created_at)->toIso8601String(),
            ] : null;
        }

        if (! str_ends_with($chatId, '@lid')) {
            return null;
        }

        $senderPaths = [
            'openwa.data.sender',
            'openwa.data.message.sender',
            'openwa.payload.data.sender',
        ];

        foreach ($senderPaths as $path) {
            $sender = data_get($payload, $path);

            if (! is_array($sender)) {
                continue;
            }

            $resolvedLid = strtolower(trim((string) ($sender['lid'] ?? '')));
            $phone = $this->normalizeConfirmedPhone($sender['id'] ?? null);
            $phoneNumber = preg_replace('/\D+/', '', (string) ($sender['phoneNumber'] ?? ''));
            $source = $sender['source'] ?? null;
            $confidence = $sender['confidence'] ?? null;

            if ($resolvedLid !== $chatId
                || $phone === null
                || $phone !== $phoneNumber
                || $source !== 'whatsapp_web_js.getContactLidAndPhone'
                || $confidence !== 'high') {
                continue;
            }

            return [
                'phone' => $phone,
                'source' => $source,
                'session' => data_get($payload, 'whatsapp_phone_resolution.session')
                    ?? data_get($payload, 'openwa.sessionId'),
                'confirmed_at' => data_get($payload, 'whatsapp_phone_resolution.confirmed_at')
                    ?? optional($evidence->created_at)->toIso8601String(),
            ];
        }

        return null;
    }

    private function normalizeConfirmedPhone(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || str_ends_with(strtolower($value), '@lid')) {
            return null;
        }

        if (!str_ends_with(strtolower($value), '@c.us')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', substr($value, 0, -5));

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51'.$digits;
        }

        return preg_match('/^51\d{9}$/', $digits) ? $digits : null;
    }

    private function lidAudit(): array
    {
        $evidences = YapeClientEvidence::query()
            ->where('source_chat_name', 'like', '%@lid')
            ->get(['id', 'source_chat_name', 'whatsapp_phone', 'customer_id', 'yape_payment_intent_id']);

        $misclassified = $evidences->filter(function (YapeClientEvidence $evidence) {
            $lidDigits = preg_replace('/\D+/', '', substr((string) $evidence->source_chat_name, 0, -4));
            $storedPhone = preg_replace('/\D+/', '', (string) $evidence->whatsapp_phone);

            return $lidDigits !== '' && $storedPhone !== '' && $lidDigits === $storedPhone;
        });

        return [
            'lid_evidences' => $evidences->count(),
            'lid_digits_as_phone' => $misclassified->count(),
            'lid_unassociated' => $evidences
                ->whereNull('customer_id')
                ->whereNull('yape_payment_intent_id')
                ->count(),
        ];
    }

    private function phoneVariants(string $phone): Collection
    {
        $variants = collect([$phone]);

        if (str_starts_with($phone, '51') && strlen($phone) === 11) {
            $variants->push(substr($phone, 2));
        }

        return $variants;
    }

    private function confidenceFor(int $score, float $debt, bool $hasCompatibleInvoice): string
    {
        $confidence = $score >= 90 ? 'alta' : ($score >= 60 ? 'media' : 'baja');

        if ($confidence === 'alta' && ($debt <= 0 || !$hasCompatibleInvoice)) {
            return 'media';
        }

        if ($confidence === 'media' && $debt <= 0) {
            return 'baja';
        }

        return $confidence;
    }

    private function confidenceWeight(string $confidence): int
    {
        return ['alta' => 3, 'media' => 2, 'baja' => 1][$confidence] ?? 0;
    }

    private function addSignal(array &$signals, int $customerId, string $type, string $reason, int $score): void
    {
        if ($customerId <= 0) {
            return;
        }

        $signals[$customerId] ??= [];

        if (!collect($signals[$customerId])->contains(fn (array $signal) => $signal['type'] === $type)) {
            $signals[$customerId][] = compact('type', 'reason', 'score');
        }
    }

    private function operationFingerprint(YapeClientEvidence $evidence): string
    {
        if (!$evidence->operation_code || !$evidence->security_code || (float) $evidence->parsed_amount <= 0) {
            return 'evidence:'.$evidence->id;
        }

        return implode('|', [$evidence->operation_code, $evidence->security_code, number_format((float) $evidence->parsed_amount, 2, '.', '')]);
    }

    private function sameChat(YapeClientEvidence $first, YapeClientEvidence $second): bool
    {
        $firstChat = trim((string) $first->source_chat_name);
        $secondChat = trim((string) $second->source_chat_name);

        return $firstChat === '' && $secondChat === ''
            ? true
            : $firstChat !== '' && $firstChat === $secondChat;
    }

    private function evidenceTime(YapeClientEvidence $evidence): Carbon
    {
        return $evidence->occurred_at?->copy() ?? $evidence->created_at?->copy() ?? now();
    }

    private function sortEvidences(Collection $evidences): Collection
    {
        return $evidences->sort(function (YapeClientEvidence $first, YapeClientEvidence $second) {
            $timeComparison = $this->evidenceTime($first)->getTimestamp() <=> $this->evidenceTime($second)->getTimestamp();

            return $timeComparison !== 0
                ? $timeComparison
                : ((int) $first->id <=> (int) $second->id);
        });
    }

    private function matchesSearch(object $case, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $needle = mb_strtolower($search);
        $values = collect([
            $case->operationCode,
            $case->securityCode,
            $case->chatId,
            $case->amount,
        ])->merge($case->candidates->flatMap(fn (object $candidate) => [
            $candidate->name,
            $candidate->customer->identity_document,
            $candidate->customer->phone,
        ]));

        return $values->filter()
            ->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $needle));
    }

    private function matchesStatus(object $case, string $status): bool
    {
        if ($status === '' || $status === 'all') {
            return true;
        }

        return match ($status) {
            'ready' => $case->state->key === 'ready',
            'review' => in_array($case->state->key, ['review', 'awaiting_dni'], true),
            'unidentified' => $case->state->key === 'unidentified',
            'processed' => $case->state->key === 'processed',
            'duplicates' => $case->duplicateCapture,
            'expired' => !$case->dniWindowActive,
            default => true,
        };
    }

    private function pendingAmount(Invoice $invoice): float
    {
        if (!$invoice->relationLoaded('payments') || !$invoice->relationLoaded('creditNotes')) {
            return round((float) $invoice->pending_amount, 2);
        }

        $paid = $invoice->payments
            ->where('status', 'completed')
            ->sum(fn (Payment $payment) => (float) $payment->amount);
        $credited = $invoice->creditNotes
            ->where('status', 'applied')
            ->sum(fn ($creditNote) => (float) $creditNote->amount);

        return round((float) $invoice->total_amount - (float) $paid - (float) $credited, 2);
    }

    private function mask(?string $value, int $visible): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 'No registrado';
        }

        return str_repeat('•', max(0, mb_strlen($value) - $visible)).mb_substr($value, -$visible);
    }
}
