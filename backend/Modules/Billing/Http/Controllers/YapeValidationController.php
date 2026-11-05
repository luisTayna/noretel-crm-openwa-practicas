<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Routing\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;
use Modules\Billing\Entities\YapeReceiverEvent;
use Modules\Billing\Services\OpenWaWebhookPayloadService;
use Modules\Billing\Services\YapeAutomationReportService;
use Modules\Billing\Services\YapeAwaitingDniReviewService;
use Modules\Billing\Services\YapeReconciliationService;
use Modules\Billing\Services\OpenWaService;
use Modules\Customer\Entities\Customer;

class YapeValidationController extends Controller
{
    protected const OPENWA_YAPE_CONTROLLER_MARKER = 'yape-base64-fix-2026-06-12';
    protected const YAPE_DNI_PROMPT_MESSAGE = 'Recibimos tu captura Yape. Para terminar de validarla y emitir tu boleta, envíanos el DNI del titular.';
    protected const YAPE_DNI_RETRY_MESSAGE = 'No encontramos ese DNI. Verifícalo y envíanoslo nuevamente.';
    protected const YAPE_NO_PENDING_DEBT_MESSAGE = 'No encontramos un recibo pendiente para ese DNI. Verifica los datos o comunícate con soporte.';
    protected const YAPE_AWAITING_DNI_IMAGE_MESSAGE = 'Ya tenemos una captura en revisión. Envíanos el DNI del titular para emitir tu boleta y continuar.';
    protected const YAPE_AWAITING_DNI_TEXT_MESSAGE = 'Para emitir tu boleta y continuar, envíanos el DNI del titular.';
    protected const YAPE_PENDING_CANCELLED_MESSAGE = 'Listo, puedes enviar una nueva captura Yape.';
    protected const YAPE_NO_PENDING_CANCEL_MESSAGE = 'Puedes enviar una captura Yape para iniciar la validación.';
    protected const YAPE_OCR_FAILED_MESSAGE = 'No pudimos leer bien la captura. Por favor envíanos una imagen más clara.';
    protected const YAPE_CAPTURE_INCOMPLETE_MESSAGE = 'No pudimos leer bien la captura. Por favor envíanos una imagen más clara.';
    protected const YAPE_VALIDATION_IN_PROGRESS_MESSAGE = 'Gracias. Estamos validando tu pago.';
    protected const YAPE_PAYMENT_VALIDATED_MESSAGE = 'Tu pago fue validado correctamente. Gracias.';
    protected const YAPE_MULTI_PAYMENT_VALIDATED_MESSAGE = 'Tu pago fue validado correctamente. Se aplicó a los recibos pendientes cubiertos por el monto enviado. Gracias.';
    protected const YAPE_DNI_WITHOUT_CAPTURE_MESSAGE = 'Para validar un pago, primero envíanos la captura Yape.';
    protected const YAPE_DUPLICATE_CAPTURE_MESSAGE = 'Esa captura no se puede validar. Por favor envíanos una captura reciente.';
    protected const YAPE_SERVICE_SELECTION_INVALID_MESSAGE = 'Opción no válida. Responde solo con el número del servicio que estás pagando.';
    protected const YAPE_AMOUNT_MISMATCH_MESSAGE = 'Encontramos recibos pendientes para tu DNI, pero ninguno coincide con el monto enviado. Verifica tu captura o comunícate con soporte.';
    protected const YAPE_AMBIGUOUS_PAYMENT_MESSAGE = 'Encontramos más de una forma de aplicar tu pago. Para evitar errores, tu pago será revisado por soporte.';

    protected const YAPE_DNI_ERROR_MESSAGES = [
        OpenWaWebhookPayloadService::DNI_ERROR_TOO_SHORT => 'Verifica el DNI. Debe tener 8 dígitos.',
        OpenWaWebhookPayloadService::DNI_ERROR_TOO_LONG => 'Verifica el DNI. Debe tener 8 dígitos.',
        OpenWaWebhookPayloadService::DNI_ERROR_MISSING => 'Para emitir tu boleta y continuar, envíanos el DNI del titular.',
        OpenWaWebhookPayloadService::DNI_ERROR_MULTIPLE => 'Encontramos más de un número de 8 dígitos. Por seguridad, envía solo el DNI del cliente correcto.',
    ];

    protected const YAPE_DNI_RESOLUTION_MESSAGES = [
        YapeReconciliationService::DNI_RESOLUTION_CUSTOMER_NOT_FOUND => 'No encontramos un cliente registrado con ese DNI. Verifica el número y envíalo nuevamente.',
        YapeReconciliationService::DNI_RESOLUTION_CUSTOMER_NOT_FOUND_MANUAL_REVIEW => 'No pudimos asociar el pago automáticamente con el DNI enviado. Tu captura quedó registrada para revisión por soporte.',
        YapeReconciliationService::DNI_RESOLUTION_EVIDENCE_NOT_FOUND => 'No encontramos una captura Yape pendiente para asociar. Envía nuevamente la captura Yape y luego el DNI del cliente.',
        YapeReconciliationService::DNI_RESOLUTION_EVIDENCE_INCOMPLETE => 'Recibimos tu captura Yape, pero no pudimos leer claramente el monto y el código de seguridad. Por favor envía una captura más nítida y completa del comprobante Yape.',
        YapeReconciliationService::DNI_RESOLUTION_CUSTOMER_MISMATCH => 'El DNI del cliente no coincide con la captura Yape pendiente. Verifica el número y envíalo nuevamente.',
        YapeReconciliationService::DNI_RESOLUTION_NO_PENDING_PAYMENT => 'No encontramos un pago pendiente para ese DNI del cliente y monto. Verifica los datos o comunícate con soporte.',
        YapeReconciliationService::DNI_RESOLUTION_MULTIPLE_PENDING_EVIDENCES => 'Encontramos más de una captura Yape pendiente. Por seguridad, envía nuevamente la captura correcta y luego el DNI del cliente.',
        YapeReconciliationService::DNI_RESOLUTION_SERVICE_SELECTION_REQUIRED => 'Encontramos más de un servicio asociado a tu DNI. Responde con el número del servicio que estás pagando.',
        YapeReconciliationService::DNI_RESOLUTION_SERVICE_SELECTION_INVALID => self::YAPE_SERVICE_SELECTION_INVALID_MESSAGE,
        YapeReconciliationService::DNI_RESOLUTION_AMOUNT_MISMATCH => self::YAPE_AMOUNT_MISMATCH_MESSAGE,
        YapeReconciliationService::DNI_RESOLUTION_AMBIGUOUS_PAYMENT => self::YAPE_AMBIGUOUS_PAYMENT_MESSAGE,
    ];

    public function __construct(
        protected YapeReconciliationService $yapeReconciliationService,
        protected OpenWaWebhookPayloadService $openWaWebhookPayloadService,
        protected OpenWaService $openWaService,
        protected YapeAwaitingDniReviewService $yapeAwaitingDniReviewService
    ) {}

    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $perPage = (int) $request->get('per_page', 15);

        $query = YapePaymentIntent::with([
            'customer',
            'invoice.contract.customer',
            'payment',
            'clientEvidence',
            'receiverEvent',
        ])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('intent_number', 'like', "%{$search}%")
                    ->orWhere('operation_code', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('whatsapp_phone', 'like', "%{$search}%")
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        $intents = $query->paginate($perPage)->withQueryString();

        $statuses = $this->statuses();
        $summary = [
            YapePaymentIntent::STATUS_WAITING_CLIENT => YapePaymentIntent::where('status', YapePaymentIntent::STATUS_WAITING_CLIENT)->count(),
            YapePaymentIntent::STATUS_WAITING_RECEIVER => YapePaymentIntent::where('status', YapePaymentIntent::STATUS_WAITING_RECEIVER)->count(),
            YapePaymentIntent::STATUS_OBSERVED => YapePaymentIntent::where('status', YapePaymentIntent::STATUS_OBSERVED)->count(),
            YapePaymentIntent::STATUS_VALIDATED => YapePaymentIntent::where('status', YapePaymentIntent::STATUS_VALIDATED)->count(),
        ];

        $manualReviewEvidences = YapeClientEvidence::query()
            ->where('status', 'manual_review')
            ->latest()
            ->limit(10)
            ->get();

        return view('billing::yape.index', compact('intents', 'search', 'status', 'statuses', 'summary', 'manualReviewEvidences'));
    }

    /**
     * Effectiveness report of the Yape automation: how much of the collection
     * work it really closes without human intervention.
     */
    public function automationReport(Request $request, YapeAutomationReportService $reportService)
    {
        $report = $reportService->build(
            $request->get('from'),
            $request->get('to')
        );

        $statuses = $this->statuses();

        return view('billing::yape.automation-report', compact('report', 'statuses'));
    }

    public function show(YapePaymentIntent $intent)
    {
        $intent->load([
            'customer',
            'invoice.contract.customer',
            'contract.customer',
            'payment',
            'clientEvidence',
            'receiverEvent',
            'createdBy',
        ]);

        return view('billing::yape.show', compact('intent'));
    }

    public function reviewQueue(Request $request)
    {
        $search = trim((string) $request->get('search'));
        // The sidebar entry is "Pagos Yape por revisar", so open the
        // lightweight payment-review queue by default. The DNI projection is
        // available only when its dedicated tab is selected.
        $tab = $request->get('tab', 'review');
        $caseStatus = trim((string) $request->get('case_status', 'all'));
        $perPage = (int) $request->get('per_page', 15);
        $casePage = max((int) $request->get('case_page', 1), 1);

        $status = $request->get('status');
        $statuses = [
            YapePaymentIntent::STATUS_WAITING_CLIENT,
            YapePaymentIntent::STATUS_WAITING_RECEIVER,
            YapePaymentIntent::STATUS_OBSERVED,
        ];
        $customerQueues = collect();
        $summary = collect($statuses)->mapWithKeys(fn ($value) => [$value => 0]);
        $awaitingDniCases = collect();
        $awaitingDniSummary = [];
        $awaitingDniPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            $casePage,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'case_page']
        );

        if ($tab === 'review') {
            // The review tab only needs intents that are already linked to a
            // payment workflow. Do not build the much heavier DNI projection.
            // The badge must still count cases the same way the DNI tab groups
            // them, otherwise the same badge shows a different number depending
            // on which tab the operator came from.
            $awaitingDniCount = $this->yapeAwaitingDniReviewService->countCases();
            $awaitingDniPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                collect(),
                $awaitingDniCount,
                $perPage,
                $casePage,
                ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'case_page']
            );
            $intentQuery = YapePaymentIntent::with('customer')->whereIn('status', $statuses)->latest();

            if (in_array($status, $statuses, true)) {
                $intentQuery->where('status', $status);
            }

            if ($search !== '') {
                $intentQuery->where(function ($builder) use ($search) {
                    $builder->where('customer_name', 'like', "%{$search}%")
                        ->orWhere('whatsapp_phone', 'like', "%{$search}%")
                        ->orWhere('operation_code', 'like', "%{$search}%")
                        ->orWhere('intent_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('identity_document', 'like', "%{$search}%"));
                });
            }

            $intents = $intentQuery->get();
            $customerQueues = $intents->groupBy(fn (YapePaymentIntent $intent) => $intent->customer_id ?: 'unassigned-' . $intent->id)
                ->map(function ($items) {
                    $first = $items->first();

                    return (object) [
                        'customer' => $first->customer,
                        'name' => $first->customer?->full_name ?: $first->customer_name ?: 'Cliente sin identificar',
                        'phone' => $first->whatsapp_phone,
                        'count' => $items->count(),
                        'oldestAt' => $items->sortBy('created_at')->first()->created_at,
                        'firstIntent' => $first,
                    ];
                })->sortBy('oldestAt')->values();
            $summary = collect($statuses)->mapWithKeys(fn ($value) => [$value => $intents->where('status', $value)->count()]);
        } else {
            // Captures waiting for DNI require the full evidence projection,
            // so it is intentionally built only for its own tab.
            $awaitingDniReview = $this->yapeAwaitingDniReviewService->build(
                $search,
                $caseStatus,
                $casePage,
                $perPage
            );
            $awaitingDniCases = $awaitingDniReview['cases'];
            $awaitingDniSummary = $awaitingDniReview['summary'];
            $awaitingDniPaginator = $awaitingDniReview['paginator'];
        }

        return view('billing::yape.review-queue', compact(
            'caseStatus',
            'perPage',
            'search',
            'tab',
            'status',
            'customerQueues',
            'summary',
            'awaitingDniCases',
            'awaitingDniSummary',
            'awaitingDniPaginator'
        ));
    }

    public function resumeReviewCase(Request $request, YapeClientEvidence $evidence)
    {
        $data = $request->validate([
            'dni' => ['required', 'regex:/^\d{8}$/'],
        ], [
            'dni.regex' => 'El DNI debe contener exactamente 8 dígitos.',
        ]);

        try {
            $result = $this->yapeReconciliationService->resumeClientEvidenceByDni($evidence, $data['dni']);
            $intent = $result['intent'] ?? null;
            $payment = $result['payment'] ?? null;

            if ($payment) {
                return redirect()
                    ->route('billing.payments.show', $payment)
                    ->with(($result['idempotent'] ?? false) ? 'info' : 'success', ($result['idempotent'] ?? false)
                        ? 'Esta operación ya fue procesada anteriormente.'
                        : 'Pago validado correctamente y recibo enviado.');
            }

            if ($intent) {
                return redirect()
                    ->route('billing.yape.show', $intent)
                    ->with('success', ($result['idempotent'] ?? false)
                        ? 'La operación ya tenía una validación Yape; no se creó otra.'
                        : 'El caso necesita revisión antes de registrar el pago.');
            }

            return redirect()
                ->route('billing.yape.review-queue')
                ->with('error', $this->yapeDniResolutionMessage((string) ($result['reason'] ?? '')));
        } catch (\Throwable $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }
    }

    /**
     * Close a Yape case from the review queue without touching payments or invoices.
     *
     * The whole case is closed, resends included: cancelling only the evidence the
     * operator clicked would leave its siblings awaiting DNI and they would come
     * back as a new case on the next page load.
     */
    public function cancelReviewEvidence(Request $request, YapeClientEvidence $evidence)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:120'],
            'reason_other' => ['nullable', 'string', 'min:5', 'max:500', 'required_if:reason,Otro'],
        ], [
            'reason.required' => 'Seleccione el motivo de la anulación.',
            'reason_other.required_if' => 'Explique el motivo cuando selecciona "Otro".',
            'reason_other.min' => 'La explicación debe tener al menos 5 caracteres.',
        ]);

        $reason = $data['reason'] === 'Otro'
            ? 'Otro: ' . trim((string) $data['reason_other'])
            : $data['reason'];

        try {
            $evidences = $this->yapeAwaitingDniReviewService->caseEvidencesFor($evidence);

            $cancelled = DB::transaction(function () use ($evidences, $reason) {
                $count = 0;

                foreach ($evidences as $caseEvidence) {
                    $this->yapeReconciliationService->cancelClientEvidenceManually(
                        $caseEvidence,
                        auth()->id(),
                        $reason
                    );
                    $count++;
                }

                return $count;
            });

            $this->appendYapeDirectDebug('yape_review_case_cancelled', [
                'evidence_id' => $evidence->id,
                'cancelled_evidences' => $cancelled,
                'reason' => $reason,
                'user_id' => auth()->id(),
                'decision' => 'review_case_cancelled',
            ]);

            return redirect()
                ->route('billing.yape.review-queue', ['tab' => 'awaiting-dni'])
                ->with('success', $cancelled > 1
                    ? "Caso Yape anulado. Se cerraron {$cancelled} capturas de la misma operación."
                    : 'Caso Yape anulado correctamente.');
        } catch (\Throwable $exception) {
            return redirect()
                ->route('billing.yape.review-queue', ['tab' => 'awaiting-dni'])
                ->with('error', 'No se pudo anular el caso: ' . $exception->getMessage());
        }
    }

    public function reviewCustomer(Customer $customer)
    {
        $intents = YapePaymentIntent::with(['invoice', 'clientEvidence', 'receiverEvent'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', [YapePaymentIntent::STATUS_WAITING_CLIENT, YapePaymentIntent::STATUS_WAITING_RECEIVER, YapePaymentIntent::STATUS_OBSERVED])
            ->oldest()->get();
        abort_unless($intents->isNotEmpty(), 404);

        return view('billing::yape.review-customer', compact('customer', 'intents'));
    }

    public function manualValidate(YapePaymentIntent $intent)
    {
        try {
            $intent = $this->yapeReconciliationService->manuallyValidateWithoutReceiverEvent($intent, auth()->id());
            $intent->loadMissing('clientEvidence');

            $openWaData = $this->openWaService->openWaDataFromClientEvidence($intent->clientEvidence);

            $validatedMessageSent = $this->openWaService->sendEvidenceTextMessageOnce(
                $intent->clientEvidence,
                $openWaData,
                $this->yapePaymentValidatedMessage($intent),
                'payment_validated'
            );

            $receiptResult = $this->sendPaymentReceiptWithAudit(
                $openWaData,
                $intent,
                'manual_validation'
            );

            $this->appendYapeDirectDebug('yape_manual_validation_completed', [
                'intent_id' => $intent->id,
                'intent_number' => $intent->intent_number,
                'payment_id' => $intent->payment_id,
                'client_evidence_id' => $intent->client_evidence_id,
                'validated_by' => auth()->id(),
                'validated_message_sent' => $validatedMessageSent,
                'receipt_result' => $receiptResult,
            ]);

            $redirect = redirect()->route('billing.yape.show', $intent->id);

            if ($receiptResult['success'] ?? false) {
                return $redirect->with('success', 'Pago Yape validado y constancia enviada automáticamente por WhatsApp.');
            }

            return $redirect->with(
                'warning',
                'El pago fue registrado correctamente, pero la constancia no pudo enviarse por WhatsApp: '
                    .($receiptResult['error'] ?? 'error no identificado').'. Puedes reintentar el envío desde esta pantalla.'
            );
        } catch (\Throwable $exception) {
            $this->appendYapeDirectDebug('yape_manual_validation_failed', [
                'intent_id' => $intent->id,
                'intent_number' => $intent->intent_number,
                'validated_by' => auth()->id(),
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('billing.yape.show', $intent->id)
                ->with('error', 'No se pudo validar manualmente: ' . $exception->getMessage());
        }
     }

    public function resendReceipt(YapePaymentIntent $intent)
    {
        $intent->loadMissing(['payment', 'clientEvidence']);

        if ($intent->status !== YapePaymentIntent::STATUS_VALIDATED || ! $intent->payment_id) {
            return redirect()
                ->route('billing.yape.show', $intent->id)
                ->with('error', 'La constancia solo puede enviarse cuando el pago Yape está validado y registrado.');
        }

        $openWaData = $this->openWaService->openWaDataFromClientEvidence($intent->clientEvidence);
        $result = $this->sendPaymentReceiptWithAudit($openWaData, $intent, 'manual_receipt_retry', true);

        if ($result['success'] ?? false) {
            return redirect()
                ->route('billing.yape.show', $intent->id)
                ->with('success', 'Constancia reenviada correctamente por WhatsApp.');
        }

        return redirect()
            ->route('billing.yape.show', $intent->id)
            ->with('error', 'No se pudo reenviar la constancia: '.($result['error'] ?? 'error no identificado'));
    }

    public function cancel(YapePaymentIntent $intent)
    {
        try {
            $intent = $this->yapeReconciliationService->cancelIntentManually(
                $intent,
                auth()->id(),
                'Anulado manualmente desde panel de administración.'
            );

            return redirect()
                ->route('billing.yape.show', $intent->id)
                ->with('success', 'El recordatorio/intent Yape fue anulado correctamente.');
        } catch (\Throwable $exception) {
            return redirect()
                ->route('billing.yape.show', $intent->id)
                ->with('error', 'No se pudo anular la validación: ' . $exception->getMessage());
        }
    }

    public function clientImage(YapeClientEvidence $evidence)
    {
        return $this->privateImageResponse($evidence->image_path);
    }

    public function receiverImage(YapeReceiverEvent $event)
    {
        return $this->privateImageResponse($event->screenshot_path);
    }

    public function pendingReceiverIntents(Request $request)
    {
        $this->guardMonitorToken($request);

        $limit = min(max((int) $request->get('limit', 20), 1), 100);

        $intents = YapePaymentIntent::with(['clientEvidence', 'customer'])
            ->where('status', YapePaymentIntent::STATUS_WAITING_RECEIVER)
            ->whereNull('receiver_event_id')
            ->whereNotNull('client_evidence_id')
            ->where(function ($query) {
                $query->whereNotNull('security_code')
                    ->orWhereHas('clientEvidence', function ($evidenceQuery) {
                        $evidenceQuery->whereNotNull('security_code');
                    });
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->oldest()
            ->limit($limit)
            ->get()
            ->map(function (YapePaymentIntent $intent) {
                return [
                    'id' => $intent->id,
                    'intent_id' => $intent->id,
                    'intent_number' => $intent->intent_number,
                    'amount' => (float) $intent->expected_amount,
                    'expected_amount' => (float) $intent->expected_amount,
                    'payer_name' => $intent->customer_name ?: $intent->customer?->full_name,
                    'customer_name' => $intent->customer_name ?: $intent->customer?->full_name,
                    'whatsapp_phone' => $intent->whatsapp_phone,
                    'operation_code' => $intent->operation_code,
                    'security_code' => $intent->security_code ?: $intent->clientEvidence?->security_code,
                    'client_evidence_id' => $intent->client_evidence_id,
                    'client_occurred_at' => optional($intent->clientEvidence?->occurred_at)->toDateTimeString(),
                    'created_at' => optional($intent->created_at)->toDateTimeString(),
                    'expires_at' => optional($intent->expires_at)->toDateTimeString(),
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'count' => $intents->count(),
            'intents' => $intents,
        ]);
    }

    public function ingestClientEvidence(Request $request)
    {
        $this->guardMonitorToken($request);
        $this->ensureEvidenceImage($request);

        $validated = $request->validate([
            'whatsapp_phone' => 'nullable|string|max:40',
            'source_message_id' => 'nullable|string|max:255',
            'source_chat_name' => 'nullable|string|max:255',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'image_base64' => 'nullable|string',
            'image_path' => 'nullable|string|max:1024',
            'raw_text' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'operation_code' => 'nullable|string|max:120',
            'security_code' => 'nullable|string|max:20',
            'occurred_at' => 'nullable|date',
            'receiver_name' => 'nullable|string|max:255',
            'receiver_phone' => 'nullable|string|max:40',
            'confidence' => 'nullable|numeric|min:0|max:1',
        ]);

        $evidence = $this->yapeReconciliationService->ingestClientEvidence(
            $validated,
            $request->file('image')
        );

        return response()->json([
            'ok' => true,
            'type' => 'client_evidence',
            'evidence_id' => $evidence->id,
            'status' => $evidence->status,
            'intent_id' => $evidence->yape_payment_intent_id,
            'intent_status' => $evidence->intent?->status,
        ]);
    }

    public function ingestReceiverEvent(Request $request)
    {
        $this->guardMonitorToken($request);
        $source = $request->input('source');
        $isTextOnlyAndroidEvent = in_array($source, ['android_accessibility', 'android_notification'], true);

        if (! $isTextOnlyAndroidEvent) {
            $this->ensureEvidenceImage($request, ['image', 'screenshot']);
        }

        $validated = $request->validate([
            'source' => 'nullable|string|in:android_accessibility,android_notification',
            'device_serial' => 'nullable|string|max:255',
            'package_name' => 'nullable|string|max:255',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'screenshot' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:5120',
            'image_base64' => 'nullable|string',
            'image_path' => 'nullable|string|max:1024',
            'raw_text' => 'nullable|string',
            'amount' => 'nullable|numeric|min:0',
            'operation_code' => 'nullable|string|max:120',
            'security_code' => 'nullable|string|max:20',
            'occurred_at' => 'nullable|date',
            'sender_name' => 'nullable|string|max:255',
            'sender_phone' => 'nullable|string|max:40',
            'confidence' => 'nullable|numeric|min:0|max:1',
            'target_intent_id' => 'nullable|integer|exists:yape_payment_intents,id',
            'target_amount' => 'nullable|numeric|min:0',
            'target_customer_name' => 'nullable|string|max:255',
            'movement_list_context' => 'nullable|string',
            'movement_match_score' => 'nullable|numeric|min:0|max:100',
            'metadata' => 'nullable|string',
        ]);

        if ($isTextOnlyAndroidEvent && empty($validated['raw_text'])) {
            throw ValidationException::withMessages([
                'raw_text' => 'El evento receptor Android debe enviar raw_text.',
            ]);
        }

        $file = $request->file('image') ?: $request->file('screenshot');

        $event = $this->yapeReconciliationService->ingestReceiverEvent($validated, $file);
        $event->loadMissing('intent.clientEvidence');

        if ($event->intent?->status === YapePaymentIntent::STATUS_VALIDATED) {
            $this->openWaService->sendEvidenceTextMessageOnce(
                $event->intent->clientEvidence,
                $this->openWaService->openWaDataFromClientEvidence($event->intent->clientEvidence),
                $this->yapePaymentValidatedMessage($event->intent),
                'payment_validated'
            );
            $this->sendPaymentReceiptWithAudit(
                $this->openWaService->openWaDataFromClientEvidence($event->intent->clientEvidence),
                $event->intent,
                'receiver_event_ingest'
            );
        }

        return response()->json([
            'ok' => true,
            'type' => 'receiver_event',
            'event_id' => $event->id,
            'status' => $event->status,
            'intent_id' => $event->yape_payment_intent_id,
            'intent_status' => $event->intent?->status,
        ]);
    }

    public function ingestOpenWaWebhook(Request $request)
    {
        $this->writeYapeControllerMarker($request);

        $this->logOpenWaYape('info', 'yape_webhook_received_raw', [
            'sessionId' => $request->input('sessionId'),
            'deliveryId' => $request->input('deliveryId'),
            'data_id' => $request->input('data.id'),
            'chatId' => $request->input('data.chatId'),
            'from' => $request->input('data.from'),
            'to' => $request->input('data.to'),
            'type' => $request->input('data.type'),
            'fromMe' => $request->input('data.fromMe'),
            'isGroup' => $request->input('data.isGroup'),
            'has_media' => is_array($request->input('data.media')),
            'media_mimetype' => $request->input('data.media.mimetype'),
            'media_data_length' => is_string($request->input('data.media.data'))
                ? strlen((string) $request->input('data.media.data'))
                : null,
            'media_data_prefix' => is_string($request->input('data.media.data'))
                ? substr((string) $request->input('data.media.data'), 0, 30)
                : null,
        ]);

        $this->guardOpenWaWebhook($request);

        $normalized = $this->openWaWebhookPayloadService->normalize($request->all());
        $botEnabled = (bool) config('billing.yape.bot_enabled', true);

        $this->logOpenWaYape('info', 'yape_webhook_received', [
            'bot_enabled' => $botEnabled,
            'event' => $normalized['event'] ?? null,
            'message_id' => data_get($normalized, 'data.source_message_id'),
            'fromMe' => $normalized['from_me'] ?? data_get($normalized, 'data.openwa_from_me'),
            'chatId_original' => $normalized['chat_id_original'] ?? data_get($normalized, 'data.openwa_chat_id_original'),
            'chat_id_normalized' => $normalized['chat_id'] ?? data_get($normalized, 'data.openwa_chat_id'),
            'is_group' => $normalized['is_group'] ?? data_get($normalized, 'data.openwa_is_group'),
            'is_private' => $normalized['is_private'] ?? data_get($normalized, 'data.openwa_is_private'),
            'message_kind' => $normalized['message_kind'] ?? null,
            'has_image' => ($normalized['message_kind'] ?? null) === 'image',
            'ignored_reason' => $normalized['ignored_reason'] ?? null,
        ]);

        if (!$botEnabled) {
            $this->logOpenWaYape('info', 'yape_webhook_ignored', [
                'bot_enabled' => false,
                'ignored_reason' => 'yape_bot_disabled',
                'event' => $normalized['event'] ?? null,
                'message_id' => data_get($normalized, 'data.source_message_id'),
            ]);

            return response()->json([
                'ok' => true,
                'ignored' => true,
                'reason' => 'yape_bot_disabled',
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        if (!$normalized['should_process']) {
            $this->logOpenWaYape('info', 'yape_webhook_ignored', [
                'event' => $normalized['event'] ?? null,
                'reason' => $normalized['ignored_reason'] ?? null,
                'chat_id_original' => $normalized['chat_id_original'] ?? null,
                'chat_id_normalized' => $normalized['chat_id'] ?? null,
                'is_group' => $normalized['is_group'] ?? null,
                'message_kind' => $normalized['message_kind'] ?? null,
            ]);

            return response()->json([
                'ok' => true,
                'ignored' => true,
                'reason' => $normalized['ignored_reason'] ?? 'Evento ignorado.',
                'event' => $normalized['event'] ?? null,
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        $data = $normalized['data'];
        $messageId = (string) ($data['source_message_id'] ?? '');
        $rawTextForLog = (string) ($data['raw_text'] ?? '');
        $normalizedTextForLog = $this->normalizeYapeText($rawTextForLog);
        $messageKind = (string) ($normalized['message_kind'] ?? 'unknown');
        $isImageMessage = $messageKind === 'image';
        $isDniMessage = ($normalized['type'] ?? null) === 'dni_resolution';
        $isCancelCommand = $this->isYapeCancelCommand($rawTextForLog);

        $this->appendYapeDirectDebug('yape_user_message_received', [
            'sessionId' => $request->input('sessionId'),
            'deliveryId' => $request->input('deliveryId'),
            'data_id' => $request->input('data.id'),
            'chat_id' => $data['openwa_chat_id'] ?? null,
            'from' => $request->input('data.from'),
            'to' => $request->input('data.to'),
            'message_type' => $normalized['type'] ?? 'image',
            'message_kind' => $messageKind,
            'body' => $rawTextForLog,
            'normalized_text' => $normalizedTextForLog,
            'is_image' => $isImageMessage,
            'is_text' => !$isImageMessage,
            'is_dni' => $isDniMessage,
            'is_cancel_command' => $isCancelCommand,
        ]);

        $dedupKey = $messageId !== '' ? 'openwa_yape_message:' . sha1($messageId) : null;

        if ($dedupKey) {
            $dedupState = Cache::get($dedupKey);

            if (is_array($dedupState)
                && in_array(($dedupState['status'] ?? null), ['response_sent', 'completed'], true)) {
                $this->appendYapeDirectDebug('yape_duplicate_technical_retry', [
                    'original_evidence_id' => $dedupState['evidence_id'] ?? null,
                    'incoming_delivery_id' => $request->input('deliveryId'),
                    'incoming_data_id' => $request->input('data.id'),
                    'source_message_id' => $messageId,
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'dedup_state' => $dedupState,
                    'action_taken' => 'ignored_after_response_or_completion',
                ]);

                $this->logOpenWaYape('info', 'yape_webhook_ignored', [
                    'message_id' => $messageId,
                    'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
                    'ignored_reason' => 'duplicate_message_ignored',
                    'dedup_status' => $dedupState['status'] ?? 'hit',
                ]);

                return response()->json([
                    'ok' => true,
                    'ignored' => true,
                    'reason' => 'duplicate_message_ignored',
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            if (is_array($dedupState)) {
                $this->appendYapeDirectDebug('yape_duplicate_technical_retry', [
                    'original_evidence_id' => $dedupState['evidence_id'] ?? null,
                    'incoming_delivery_id' => $request->input('deliveryId'),
                    'incoming_data_id' => $request->input('data.id'),
                    'source_message_id' => $messageId,
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'dedup_state' => $dedupState,
                    'action_taken' => 'retry_allowed_after_incomplete_processing',
                ]);
            }

            Cache::put($dedupKey, [
                'status' => is_array($dedupState) ? 'retrying' : 'processing',
                'chat_id' => $data['openwa_chat_id'] ?? null,
                'message_type' => $normalized['type'] ?? 'image',
                'started_at' => now()->toDateTimeString(),
                'delivery_id' => $request->input('deliveryId'),
                'data_id' => $request->input('data.id'),
            ], now()->addDay());
        }

        $this->logOpenWaYape('info', 'yape_webhook_processable', [
            'event' => $normalized['event'] ?? null,
            'type' => $normalized['type'] ?? 'image',
            'chat_id_original' => $data['openwa_chat_id_original'] ?? null,
            'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
            'is_group' => $data['openwa_is_group'] ?? null,
            'whatsapp_phone_resolved' => ! empty($data['whatsapp_phone']),
            'whatsapp_phone_suffix' => ! empty($data['whatsapp_phone'])
                ? substr((string) $data['whatsapp_phone'], -4)
                : null,
            'source_message_id' => $data['source_message_id'] ?? null,
            'dedup_status' => $messageId !== '' ? 'reserved' : 'no_message_id',
        ]);

        $rawText = (string) ($data['raw_text'] ?? '');

        $chatState = $this->yapeReconciliationService->logChatStateLoaded($data);
        $pendingEvidence = $chatState['evidence'] ?? null;

        if (($normalized['type'] ?? null) === 'interactive_selection') {
            if ($pendingEvidence || in_array(($chatState['current_state'] ?? null), ['awaiting_dni', 'awaiting_service_selection'], true)) {
                return response()->json([
                    'ok' => true,
                    'ignored' => true,
                    'reason' => 'interactive_menu_ignored_during_payment_flow',
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            return $this->handleInteractiveMenuSelection($data, (string) ($data['interactive_selection_id'] ?? ''));
        }

        if ($this->isYapeCancelCommand($rawText)) {
            $cancelledEvidence = $this->yapeReconciliationService->cancelPendingClientEvidenceForOpenWaChat($data, 'user_cancel_command');
            $responseText = $cancelledEvidence
                ? self::YAPE_PENDING_CANCELLED_MESSAGE
                : self::YAPE_NO_PENDING_CANCEL_MESSAGE;
            $sendResult = $this->openWaService->sendTextMessage(
                $data,
                $responseText,
                [
                    'message_context' => $cancelledEvidence ? 'cancel_pending' : 'cancel_no_pending',
                    'evidence_id' => $cancelledEvidence?->id,
                ]
            );
            $responseSent = (bool) ($sendResult['success'] ?? false);

            $this->appendYapeDirectDebug('yape_pending_cancelled', [
                'chat_id' => $data['openwa_chat_id'] ?? null,
                'pending_evidence_id' => $cancelledEvidence?->id,
                'reason' => $cancelledEvidence ? 'user_cancel_command' : 'no_pending_to_cancel',
                'response_sent' => $responseSent,
            ]);
            $this->appendYapeDirectDebug('yape_cancel_command_received', [
                'chat_id' => $data['openwa_chat_id'] ?? null,
                'from' => data_get($data, 'payload.data.from'),
                'normalized_text' => $this->normalizeYapeText($rawText),
                'pending_found' => (bool) $cancelledEvidence,
                'pending_evidence_id' => $cancelledEvidence?->id,
                'state_before' => $chatState['current_state'] ?? null,
                'state_after' => $cancelledEvidence ? 'cancelled' : 'idle',
                'response_text' => $responseText,
                'response_sent' => $responseSent,
                'send_error' => $sendResult['error'] ?? ($sendResult['body'] ?? null),
            ]);

            return response()->json([
                'ok' => true,
                'type' => 'openwa_yape_cancel',
                'cancelled' => (bool) $cancelledEvidence,
                'evidence_id' => $cancelledEvidence?->id,
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        if (($chatState['current_state'] ?? null) === 'awaiting_service_selection' && !$isImageMessage) {
            if (!$this->isPotentialServiceSelectionText($rawText)) {
                $this->appendYapeDirectDebug('yape_unrelated_text_ignored', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'state' => 'awaiting_service_selection',
                    'message_kind' => $messageKind,
                    'reason' => 'text_not_service_selection',
                    'response_sent' => false,
                ]);

                if ($dedupKey) {
                    Cache::put($dedupKey, [
                        'status' => 'completed',
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'message_type' => $normalized['type'] ?? 'text',
                        'ignored_reason' => 'text_not_service_selection',
                        'completed_at' => now()->toDateTimeString(),
                    ], now()->addDay());
                }

                return response()->json([
                    'ok' => true,
                    'ignored' => true,
                    'reason' => 'unrelated_text_ignored_while_awaiting_service_selection',
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            $selectionResolution = $this->yapeReconciliationService->resolvePendingServiceSelectionForOpenWa(
                $data,
                $rawText
            );
            $selectionEvidence = $selectionResolution['evidence'] ?? null;
            $selectionEvidence?->loadMissing('intent');
            $selectionResolved = (bool) ($selectionResolution['resolved'] ?? false);
            $selectionReason = (string) ($selectionResolution['reason'] ?? YapeReconciliationService::DNI_RESOLUTION_SERVICE_SELECTION_INVALID);

            if ($selectionResolved) {
                if ($selectionEvidence?->intent?->status === YapePaymentIntent::STATUS_VALIDATED) {
                    $responseSent = $this->openWaService->sendEvidenceTextMessageOnce(
                        $selectionEvidence,
                        $data,
                        $this->yapePaymentValidatedMessage($selectionEvidence?->intent),
                        'payment_validated'
                    );
                } else {
                    $responseSent = $this->openWaService->sendEvidenceTextMessageOnce(
                        $selectionEvidence,
                        $data,
                        self::YAPE_VALIDATION_IN_PROGRESS_MESSAGE,
                        'payment_validation_in_progress'
                    );
                }

                try {
                    $this->sendPaymentReceiptWithAudit(
                        $data,
                        $selectionEvidence?->intent,
                        'service_selection_resolution'
                    );
                } catch (\Throwable $exception) {
                    $this->appendYapeDirectDebug('yape_payment_validation_exception', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'pending_evidence_id' => $selectionEvidence?->id,
                        'intent_id' => $selectionEvidence?->yape_payment_intent_id,
                        'stage' => 'send_payment_receipt_after_service_selection',
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);
                }

                $this->appendYapeDirectDebug('yape_payment_validation_started', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'evidence_id' => $selectionEvidence?->id,
                    'intent_id' => $selectionEvidence?->intent?->id,
                    'intent_number' => $selectionEvidence?->intent?->intent_number,
                    'customer_id' => $selectionEvidence?->customer_id,
                    'invoice_id' => $selectionEvidence?->intent?->invoice_id,
                    'amount' => $selectionEvidence?->parsed_amount,
                    'operation_code' => $selectionEvidence?->operation_code,
                    'decision' => 'manual_service_selection',
                    'response_sent' => $responseSent,
                ]);
            } else {
                $sendResult = $this->openWaService->sendTextMessage(
                    $data,
                    $this->yapeDniResolutionMessage($selectionReason),
                    [
                        'message_context' => 'service_selection_invalid',
                        'evidence_id' => $selectionEvidence?->id,
                    ]
                );

                $this->appendYapeDirectDebug('yape_invoice_selection_invalid', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'evidence_id' => $selectionEvidence?->id,
                    'selection_text' => $rawText,
                    'reason' => $selectionReason,
                    'response_sent' => (bool) ($sendResult['success'] ?? false),
                ]);
            }

            if ($dedupKey) {
                Cache::put($dedupKey, [
                    'status' => 'completed',
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'message_type' => $normalized['type'] ?? 'service_selection',
                    'evidence_id' => $selectionEvidence?->id,
                    'resolved' => $selectionResolved,
                    'completed_at' => now()->toDateTimeString(),
                    'delivery_id' => $request->input('deliveryId'),
                    'data_id' => $request->input('data.id'),
                ], now()->addDay());
            }

            return response()->json([
                'ok' => true,
                'type' => 'openwa_service_selection',
                'resolved' => $selectionResolved,
                'reason' => $selectionReason,
                'evidence_id' => $selectionEvidence?->id,
                'status' => $selectionEvidence?->status,
                'intent_id' => $selectionEvidence?->yape_payment_intent_id,
                'intent_status' => $selectionEvidence?->intent?->status,
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        if (($normalized['type'] ?? null) === 'dni_resolution_error') {
            if (!$pendingEvidence) {
                $recentEvidence = $this->recentYapeValidationEvidenceForOpenWaChat($data);
                $recentIntent = $recentEvidence?->intent;

                if ($recentIntent?->status === YapePaymentIntent::STATUS_WAITING_RECEIVER) {
                    $this->appendYapeDirectDebug('yape_text_ignored_during_payment_validation', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'text' => $rawText,
                        'evidence_id' => $recentEvidence?->id,
                        'intent_id' => $recentIntent?->id,
                        'intent_status' => $recentIntent?->status,
                        'reason' => 'payment_validation_in_progress',
                        'response_sent' => false,
                    ]);

                    return response()->json([
                        'ok' => true,
                        'ignored' => true,
                        'reason' => 'payment_validation_in_progress_text_ignored',
                        'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                    ]);
                }

                if ($recentIntent?->status === YapePaymentIntent::STATUS_VALIDATED && $this->isReceiptRequestText($rawText)) {
                    $this->sendPaymentReceiptWithAudit($data, $recentIntent, 'customer_receipt_request', true);

                    $this->appendYapeDirectDebug('yape_receipt_request_after_validation', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'text' => $rawText,
                        'evidence_id' => $recentEvidence?->id,
                        'intent_id' => $recentIntent?->id,
                        'intent_status' => $recentIntent?->status,
                        'receipt_requested' => true,
                    ]);

                    return response()->json([
                        'ok' => true,
                        'type' => 'openwa_yape_receipt_request',
                        'receipt_requested' => true,
                        'evidence_id' => $recentEvidence?->id,
                        'intent_id' => $recentIntent?->id,
                        'intent_status' => $recentIntent?->status,
                        'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                    ]);
                }

                if ($this->isCustomerMenuRequest($rawText)) {
                    $menuResult = $this->openWaService->sendCustomerMenu($data);

                    $this->appendYapeDirectDebug('openwa_customer_menu_sent', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'response_sent' => (bool) ($menuResult['success'] ?? false),
                        'http_status' => $menuResult['status'] ?? null,
                    ]);

                    return response()->json([
                        'ok' => true,
                        'type' => 'openwa_customer_menu',
                        'menu_sent' => (bool) ($menuResult['success'] ?? false),
                        'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                    ]);
                }

                $shouldRespondIdle = $this->isYapeRelatedText($rawText);
                $sendResult = ['success' => false];

                if ($shouldRespondIdle) {
                    $sendResult = $this->openWaService->sendTextMessage(
                        $data,
                        self::YAPE_DNI_WITHOUT_CAPTURE_MESSAGE,
                        ['message_context' => 'dni_without_capture']
                    );
                }

                $this->appendYapeDirectDebug('yape_text_ignored_or_prompted', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'text' => $rawText,
                    'response_sent' => (bool) ($sendResult['success'] ?? false),
                    'reason' => $shouldRespondIdle ? 'idle_yape_related_text_prompted_for_capture' : 'idle_unrelated_text_ignored',
                ]);

                return response()->json([
                    'ok' => true,
                    'ignored' => true,
                    'reason' => 'no_pending_dni_text_ignored',
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            if (($data['dni_error'] ?? null) === OpenWaWebhookPayloadService::DNI_ERROR_MISSING
                || !$this->isPotentialDniAttemptText($rawText)) {
                $this->appendYapeDirectDebug('yape_unrelated_text_ignored', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'state' => $chatState['current_state'] ?? 'awaiting_dni',
                    'message_kind' => $messageKind,
                    'reason' => 'text_without_dni_candidate',
                    'response_sent' => false,
                ]);

                if ($dedupKey) {
                    Cache::put($dedupKey, [
                        'status' => 'completed',
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'message_type' => $normalized['type'],
                        'evidence_id' => $pendingEvidence->id,
                        'ignored_reason' => 'text_without_dni_candidate',
                        'completed_at' => now()->toDateTimeString(),
                    ], now()->addDay());
                }

                return response()->json([
                    'ok' => true,
                    'ignored' => true,
                    'reason' => 'unrelated_text_ignored_while_awaiting_dni',
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            $responseText = $this->yapeDniErrorMessage((string) $data['dni_error']);
            $sendResult = $this->openWaService->sendTextMessage(
                $data,
                $responseText,
                [
                    'message_context' => 'awaiting_dni_text_prompt',
                    'evidence_id' => $pendingEvidence->id,
                ]
            );

            $this->appendYapeDirectDebug('yape_text_ignored_or_prompted', [
                'chat_id' => $data['openwa_chat_id'] ?? null,
                'text' => $rawText,
                'response_sent' => (bool) ($sendResult['success'] ?? false),
                'reason' => 'awaiting_dni_text_without_valid_dni',
            ]);
            $this->appendYapeDirectDebug('yape_text_prompted_for_dni', [
                'chat_id' => $data['openwa_chat_id'] ?? null,
                'pending_evidence_id' => $pendingEvidence->id,
                'received_text' => $rawText,
                'response_text' => $responseText,
                'response_sent' => (bool) ($sendResult['success'] ?? false),
            ]);

            return response()->json([
                'ok' => true,
                'type' => 'openwa_dni_resolution_error',
                'resolved' => false,
                'dni_error' => $data['dni_error'] ?? OpenWaWebhookPayloadService::DNI_ERROR_MISSING,
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        if (($normalized['type'] ?? null) === 'dni_resolution') {
            if (!$pendingEvidence) {
                $sendResult = $this->openWaService->sendTextMessage(
                    $data,
                    self::YAPE_DNI_WITHOUT_CAPTURE_MESSAGE,
                    ['message_context' => 'dni_without_capture']
                );

                $this->appendYapeDirectDebug('yape_user_message_decision', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'message_type' => 'dni_resolution',
                    'normalized_text' => $this->normalizeYapeText($rawText),
                    'pending_evidence_id' => null,
                    'state_before' => $chatState['current_state'] ?? 'idle',
                    'decision' => 'prompt_capture_first',
                    'reason' => 'dni_without_pending_capture',
                    'user_response_text' => self::YAPE_DNI_WITHOUT_CAPTURE_MESSAGE,
                    'should_send_response' => true,
                    'response_sent' => (bool) ($sendResult['success'] ?? false),
                ]);

                return response()->json([
                    'ok' => true,
                    'type' => 'openwa_dni_without_capture',
                    'resolved' => false,
                    'reason' => 'dni_without_pending_capture',
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            $earlyValidationSent = false;
            $earlyValidationSendResult = ['success' => false];
            $preview = $this->yapeReconciliationService->previewDniResolutionForOpenWa($data, (string) ($data['dni'] ?? ''));
            $previewEvidence = $preview['evidence'] ?? $pendingEvidence;

            $this->appendYapeDirectDebug('yape_payment_validation_prepare', [
                'chat_id' => $data['openwa_chat_id'] ?? null,
                'dni' => $data['dni'] ?? null,
                'pending_evidence_id' => $previewEvidence?->id,
                'found_client' => (bool) ($preview['found_client'] ?? false),
                'client_id' => $preview['client_id'] ?? null,
                'has_pending_debt' => (bool) ($preview['has_pending_debt'] ?? false),
                'should_send_early_response' => (bool) ($preview['should_send_validation_response'] ?? false),
            ]);

            if ($preview['should_send_validation_response'] ?? false) {
                $this->appendYapeDirectDebug('yape_payment_validation_response_attempt', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'evidence_id' => $previewEvidence?->id,
                    'message_text' => self::YAPE_VALIDATION_IN_PROGRESS_MESSAGE,
                    'phase' => 'before_reconciliation',
                ]);

                $earlyValidationSendResult = $this->openWaService->sendTextMessage(
                    $data,
                    self::YAPE_VALIDATION_IN_PROGRESS_MESSAGE,
                    [
                        'message_context' => 'payment_validation_in_progress',
                        'evidence_id' => $previewEvidence?->id,
                    ]
                );
                $earlyValidationSent = (bool) ($earlyValidationSendResult['success'] ?? false);

                if ($dedupKey && $earlyValidationSent) {
                    Cache::put($dedupKey, [
                        'status' => 'response_sent',
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'message_type' => $normalized['type'] ?? 'dni_resolution',
                        'evidence_id' => $previewEvidence?->id,
                        'response_context' => 'payment_validation_in_progress',
                        'response_sent_at' => now()->toDateTimeString(),
                        'delivery_id' => $request->input('deliveryId'),
                        'data_id' => $request->input('data.id'),
                    ], now()->addDay());
                }

                $this->appendYapeDirectDebug('yape_payment_validation_response_sent', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'evidence_id' => $previewEvidence?->id,
                    'response_sent' => $earlyValidationSent,
                    'send_error' => $earlyValidationSendResult['error'] ?? ($earlyValidationSendResult['body'] ?? null),
                    'phase' => 'before_reconciliation',
                ]);
            }

            try {
                $resolution = $this->yapeReconciliationService->resolveAmbiguousClientEvidenceByDniWithResult(
                    $data,
                    (string) ($data['dni'] ?? '')
                );
            } catch (\Throwable $exception) {
                $this->appendYapeDirectDebug('yape_payment_validation_exception', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'dni' => $data['dni'] ?? null,
                    'pending_evidence_id' => $previewEvidence?->id,
                    'response_already_sent' => $earlyValidationSent,
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);

                if (!$earlyValidationSent) {
                    $fallbackSendResult = $this->openWaService->sendTextMessage(
                        $data,
                        self::YAPE_VALIDATION_IN_PROGRESS_MESSAGE,
                        [
                            'message_context' => 'payment_validation_in_progress_after_exception',
                            'evidence_id' => $previewEvidence?->id,
                        ]
                    );
                    $earlyValidationSent = (bool) ($fallbackSendResult['success'] ?? false);

                    $this->appendYapeDirectDebug('yape_payment_validation_response_sent', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'evidence_id' => $previewEvidence?->id,
                        'response_sent' => $earlyValidationSent,
                        'send_error' => $fallbackSendResult['error'] ?? ($fallbackSendResult['body'] ?? null),
                        'phase' => 'exception_fallback',
                    ]);
                }

                if ($dedupKey && $earlyValidationSent) {
                    Cache::put($dedupKey, [
                        'status' => 'response_sent',
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'message_type' => $normalized['type'] ?? 'dni_resolution',
                        'evidence_id' => $previewEvidence?->id,
                        'response_context' => 'payment_validation_in_progress_exception',
                        'response_sent_at' => now()->toDateTimeString(),
                        'delivery_id' => $request->input('deliveryId'),
                        'data_id' => $request->input('data.id'),
                    ], now()->addDay());
                }

                return response()->json([
                    'ok' => true,
                    'type' => 'openwa_dni_resolution',
                    'resolved' => false,
                    'reason' => 'payment_validation_exception',
                    'evidence_id' => $previewEvidence?->id,
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            $evidence = $resolution['evidence'] ?? null;

            \Log::info('OpenWA Yape resolución DNI.', [
                'chat_id_original' => $data['openwa_chat_id_original'] ?? null,
                'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
                'dni' => $data['dni'] ?? null,
                'resolved' => (bool) ($resolution['resolved'] ?? false),
                'reason' => $resolution['reason'] ?? null,
                'evidence_id' => $evidence?->id,
                'intent_id' => $evidence?->yape_payment_intent_id,
            ]);

            if ($resolution['resolved'] ?? false) {
                $evidence?->loadMissing('intent');

                if ($evidence?->intent?->status === YapePaymentIntent::STATUS_VALIDATED) {
                    $validatedSent = $this->openWaService->sendEvidenceTextMessageOnce(
                        $evidence,
                        $data,
                        $this->yapePaymentValidatedMessage($evidence?->intent),
                        'payment_validated'
                    );
                    $this->appendYapeDirectDebug('yape_payment_validation_result', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'evidence_id' => $evidence?->id,
                        'intent_id' => $evidence?->intent?->id,
                        'intent_number' => $evidence?->intent?->intent_number,
                        'status' => $evidence?->intent?->status,
                        'customer_id' => $evidence?->customer_id,
                        'invoice_id' => $evidence?->intent?->invoice_id,
                        'receipt_id' => $evidence?->intent?->payment_id,
                        'decision' => 'payment_validated',
                        'response_sent' => $validatedSent,
                    ]);
                } else {
                    $validationSent = $earlyValidationSent ?: $this->openWaService->sendEvidenceTextMessageOnce(
                            $evidence,
                            $data,
                            self::YAPE_VALIDATION_IN_PROGRESS_MESSAGE,
                            'payment_validation_in_progress'
                        );
                    $this->appendYapeDirectDebug('yape_payment_validation_started', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'evidence_id' => $evidence?->id,
                        'intent_id' => $evidence?->intent?->id,
                        'intent_number' => $evidence?->intent?->intent_number,
                        'customer_id' => $evidence?->customer_id,
                        'invoice_id' => $evidence?->intent?->invoice_id,
                        'amount' => $evidence?->parsed_amount,
                        'operation_code' => $evidence?->operation_code,
                        'response_sent' => $validationSent,
                        'response_sent_before_reconciliation' => $earlyValidationSent,
                    ]);
                }

                try {
                    $this->sendPaymentReceiptWithAudit($data, $evidence?->intent, 'dni_resolution');
                } catch (\Throwable $exception) {
                    $this->appendYapeDirectDebug('yape_payment_validation_exception', [
                        'chat_id' => $data['openwa_chat_id'] ?? null,
                        'dni' => $data['dni'] ?? null,
                        'pending_evidence_id' => $evidence?->id,
                        'intent_id' => $evidence?->yape_payment_intent_id,
                        'stage' => 'send_payment_receipt',
                        'response_already_sent' => true,
                        'message' => $exception->getMessage(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]);
                }
            } else {
                $reason = (string) ($resolution['reason'] ?? YapeReconciliationService::DNI_RESOLUTION_NO_PENDING_PAYMENT);
                $sendResult = $this->openWaService->sendTextMessage(
                    $data,
                    (string) ($resolution['response_message'] ?? $this->yapeDniResolutionMessage($reason)),
                    [
                        'message_context' => 'dni_resolution_failed',
                        'evidence_id' => $evidence?->id,
                    ]
                );

                $this->appendYapeDirectDebug('yape_text_ignored_or_prompted', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'text' => $rawText,
                    'response_sent' => (bool) ($sendResult['success'] ?? false),
                    'reason' => 'dni_resolution_failed_keep_pending',
                    'resolution_reason' => $reason,
                    'pending_evidence_id' => $evidence?->id,
                ]);
                $this->appendYapeDirectDebug('yape_dni_failed_keep_pending', [
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'dni' => $data['dni'] ?? null,
                    'reason' => $reason,
                    'pending_evidence_id' => $evidence?->id,
                    'state_after' => $evidence ? 'awaiting_dni' : ($chatState['current_state'] ?? 'idle'),
                    'response_sent' => (bool) ($sendResult['success'] ?? false),
                ]);
            }

            if ($dedupKey) {
                Cache::put($dedupKey, [
                    'status' => 'completed',
                    'chat_id' => $data['openwa_chat_id'] ?? null,
                    'message_type' => $normalized['type'] ?? 'dni_resolution',
                    'evidence_id' => $evidence?->id,
                    'resolved' => (bool) ($resolution['resolved'] ?? false),
                    'completed_at' => now()->toDateTimeString(),
                    'delivery_id' => $request->input('deliveryId'),
                    'data_id' => $request->input('data.id'),
                ], now()->addDay());
            }

            return response()->json([
                'ok' => true,
                'type' => 'openwa_dni_resolution',
                'resolved' => (bool) ($resolution['resolved'] ?? false),
                'reason' => $resolution['reason'] ?? null,
                'attempts' => $resolution['attempts'] ?? 0,
                'max_attempts' => $resolution['max_attempts'] ?? YapeReconciliationService::DNI_RESOLUTION_MAX_ATTEMPTS,
                'manual_review_required' => (bool) ($resolution['manual_review_required'] ?? false),
                'evidence_id' => $evidence?->id,
                'status' => $evidence?->status,
                'intent_id' => $evidence?->yape_payment_intent_id,
                'intent_status' => $evidence?->intent?->status,
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        if ($pendingEvidence) {
            $this->logOpenWaYape('info', 'yape_webhook_ignored', [
                'message_id' => $data['source_message_id'] ?? null,
                'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
                'ignored_reason' => 'existing_pending_capture',
                'pending_evidence_id' => $pendingEvidence->id,
            ]);

            $sendResult = $this->openWaService->sendTextMessage(
                $data,
                self::YAPE_AWAITING_DNI_IMAGE_MESSAGE,
                [
                    'message_context' => 'image_ignored_awaiting_dni',
                    'evidence_id' => $pendingEvidence->id,
                ]
            );

            $this->appendYapeDirectDebug('yape_image_ignored_awaiting_dni', [
                'chat_id' => $data['openwa_chat_id'] ?? null,
                'pending_evidence_id' => $pendingEvidence->id,
                'response_text' => self::YAPE_AWAITING_DNI_IMAGE_MESSAGE,
                'response_sent' => (bool) ($sendResult['success'] ?? false),
                'send_error' => $sendResult['error'] ?? ($sendResult['body'] ?? null),
            ]);

            return response()->json([
                'ok' => true,
                'ignored' => true,
                'reason' => 'existing_pending_capture',
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        if (empty($data['image_base64']) && !empty($data['media_url'])) {
            $data['image_base64'] = $this->downloadOpenWaMediaAsBase64($data['media_url']);
        }

        if (empty($data['image_base64'])) {
            throw ValidationException::withMessages([
                'image' => 'OpenWA notificó una imagen, pero no envió media descargable.',
            ]);
        }

        // 1. Ingestar la evidencia de manera síncrona
        $evidence = $this->yapeReconciliationService->ingestClientEvidence([
            'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
            'source_message_id' => $data['source_message_id'] ?? null,
            'source_chat_name' => $data['source_chat_name'] ?? null,
            'image_base64' => $data['image_base64'],
            'media_mimetype' => $data['media_mimetype'] ?? null,
            'raw_text' => $data['raw_text'] ?? null,
            'amount' => $data['amount'] ?? null,
            'operation_code' => $data['operation_code'] ?? null,
            'security_code' => $data['security_code'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? null,
            'receiver_name' => $data['receiver_name'] ?? null,
            'receiver_phone' => $data['receiver_phone'] ?? null,
            'confidence' => $data['confidence'] ?? 0,
            'status' => 'pending_ocr',
            'payload' => [
                'source' => 'openwa',
                'event' => $normalized['event'] ?? null,
                'openwa_chat_id' => $data['openwa_chat_id'] ?? null,
                'openwa_chat_id_original' => $data['openwa_chat_id_original'] ?? null,
                'whatsapp_phone_resolution' => [
                    'source' => $data['whatsapp_phone_source'] ?? null,
                    'confidence' => $data['whatsapp_phone_confidence'] ?? null,
                    'session' => $request->input('sessionId'),
                    'confirmed_at' => ($data['whatsapp_phone'] ?? null) ? now()->toIso8601String() : null,
                ],
                'source_chat_display_name' => $data['source_chat_display_name'] ?? null,
                'openwa' => $data['payload'] ?? $request->all(),
            ],
        ]);

        $imageSizeBytes = $this->storedImageSize($evidence->image_path);

        $this->logOpenWaYape('info', 'yape_image_saved', [
            'evidence_id' => $evidence->id,
            'chat_id_original' => $data['openwa_chat_id_original'] ?? null,
            'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
            'image_path' => $evidence->image_path,
            'status' => $evidence->status,
            'image_saved_path' => $evidence->image_path,
            'image_size_bytes' => $imageSizeBytes,
            'image_save_failed' => !$evidence->image_path || $imageSizeBytes === null,
        ]);

        $this->appendYapeDirectDebug('yape_image_saved', [
            'evidence_id' => $evidence->id,
            'image_path' => $evidence->image_path,
            'image_size_bytes' => $imageSizeBytes,
            'exists' => $evidence->image_path && $imageSizeBytes !== null,
        ]);

        // 2. Ejecutar OCR síncronamente durante la petición HTTP
        $ocrSucceeded = $this->yapeReconciliationService->enrichEvidenceWithOcr($evidence);
        $evidence = $evidence->fresh(['intent']) ?: $evidence;

        if (!$ocrSucceeded || !$this->yapeReconciliationService->clientEvidenceIsStrictValidYapeCapture($evidence)) {
            $openWaData = $this->openWaService->openWaDataFromClientEvidence($evidence);
            $ocrFailureResponseSent = $this->openWaService->sendEvidenceTextMessageOnce(
                $evidence,
                $openWaData,
                self::YAPE_OCR_FAILED_MESSAGE,
                'capture_ocr_failed'
            );

            $this->appendYapeDirectDebug('yape_capture_invalid_response', [
                'chat_id' => $openWaData['source_chat_name'] ?? ($data['openwa_chat_id'] ?? null),
                'evidence_id' => $evidence->id,
                'reason' => $ocrSucceeded ? 'invalid_yape_capture' : 'ocr_failed_or_timeout',
                'response_text' => self::YAPE_OCR_FAILED_MESSAGE,
                'response_sent' => $ocrFailureResponseSent,
            ]);

            $this->appendYapeDirectDebug('yape_validation_decision', [
                'evidence_id' => $evidence->id,
                'capture_invalid' => true,
                'reason' => $ocrSucceeded ? 'invalid_yape_capture' : 'ocr_failed_or_not_yape_image',
                'threshold' => (float) config('billing.yape.confidence_threshold', 0.88),
                'confidence' => $evidence->confidence,
                'amount' => $evidence->parsed_amount,
                'operation_code' => $evidence->operation_code,
                'security_code' => $evidence->security_code,
                'should_create_pending' => false,
                'pending_id' => null,
                'intent_id' => null,
                'response_sent' => $ocrFailureResponseSent,
            ]);

            $this->logOpenWaYape('info', 'yape_capture_ignored', [
                'message_id' => $data['source_message_id'] ?? null,
                'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
                'image_saved_path' => $evidence->image_path,
                'ocr_mode' => config('billing.yape.ocr_mode', 'local'),
                'ocr_remote_url' => config('billing.yape.ocr_remote_url'),
                'ocr_response' => $this->summarizeOcrResponse(data_get($evidence->payload, 'ocr.response')),
                'is_valid_yape_capture' => false,
                'pending_created_id' => null,
                'ignored_reason' => $ocrSucceeded ? 'invalid_yape_capture' : 'ocr_failed_or_not_yape_image',
                'response_sent' => $ocrFailureResponseSent,
            ]);

            return response()->json([
                'ok' => true,
                'type' => 'openwa_client_evidence',
                'evidence_id' => $evidence->id,
                'status' => $evidence->status,
                'dni_requested' => false,
                'capture_invalid' => true,
                'reason' => $ocrSucceeded ? 'invalid_yape_capture' : 'ocr_failed_or_not_yape_image',
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        // 3. Ejecutar conciliación síncronamente durante la petición HTTP
        $evidenceBeforeReconciliation = $evidence->fresh(['intent']) ?: $evidence;

        $this->appendYapeDirectDebug('yape_reconciliation_controller_boundary', [
            'step' => 'before_processEvidenceReconciliation',
            'incoming_evidence_id' => $evidenceBeforeReconciliation->id,
            'incoming_status' => $evidenceBeforeReconciliation->status,
            'incoming_source_message_id' => $evidenceBeforeReconciliation->source_message_id,
            'incoming_chat_id' => $evidenceBeforeReconciliation->source_chat_name,
            'incoming_intent_id' => $evidenceBeforeReconciliation->yape_payment_intent_id,
        ]);

        $evidence = $this->yapeReconciliationService->processEvidenceReconciliation($evidenceBeforeReconciliation);

        $this->appendYapeDirectDebug('yape_reconciliation_controller_boundary', [
            'step' => 'after_processEvidenceReconciliation',
            'incoming_evidence_id' => $evidenceBeforeReconciliation->id,
            'selected_evidence_id' => $evidence->id,
            'selected_status' => $evidence->status,
            'selected_source_message_id' => $evidence->source_message_id,
            'selected_chat_id' => $evidence->source_chat_name,
            'selected_intent_id' => $evidence->yape_payment_intent_id,
            'selection_changed' => (int) $evidenceBeforeReconciliation->id !== (int) $evidence->id,
            'selection_reason' => (int) $evidenceBeforeReconciliation->id === (int) $evidence->id
                ? 'same_current_evidence'
                : 'service_returned_different_evidence',
        ]);

        $evidence->loadMissing('intent');
        $intent = $evidence->intent;
        $openWaData = $this->openWaService->openWaDataFromClientEvidence($evidence);

        $this->appendYapeDirectDebug('yape_validation_decision', [
            'evidence_id' => $evidence->id,
            'capture_invalid' => false,
            'reason' => 'valid_yape_capture',
            'threshold' => (float) config('billing.yape.confidence_threshold', 0.88),
            'confidence' => $evidence->confidence,
            'amount' => $evidence->parsed_amount,
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
            'should_create_pending' => true,
            'pending_id' => $intent?->id,
            'intent_id' => $intent?->id,
            'intent_status' => $intent?->status,
        ]);

        $this->logOpenWaYape('info', 'yape_capture_processed', [
            'evidence_id' => $evidence->id,
            'chat_id_original' => $data['openwa_chat_id_original'] ?? null,
            'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
            'image_path' => $evidence->image_path,
            'status' => $evidence->status,
            'ocr_mode' => config('billing.yape.ocr_mode', 'local'),
            'ocr_remote_url' => config('billing.yape.ocr_remote_url'),
            'parsed_amount' => $evidence->parsed_amount,
            'operation_code' => $evidence->operation_code,
            'security_code' => $evidence->security_code,
            'confidence' => $evidence->confidence,
            'ocr_response' => $this->summarizeOcrResponse(data_get($evidence->payload, 'ocr.response')),
            'is_valid_yape_capture' => true,
            'pending_created_id' => $intent?->id,
            'intent_id' => $intent?->id,
            'intent_status' => $intent?->status,
        ]);

        if ($intent && $intent->status === YapePaymentIntent::STATUS_VALIDATED) {
            $this->openWaService->sendEvidenceTextMessageOnce(
                $evidence,
                $openWaData,
                $this->yapePaymentValidatedMessage($intent),
                'payment_validated'
            );
            $this->sendPaymentReceiptWithAudit($openWaData, $intent, 'client_evidence_reconciliation');

            return response()->json([
                'ok' => true,
                'type' => 'openwa_client_evidence',
                'evidence_id' => $evidence->id,
                'status' => $evidence->status,
                'dni_requested' => false,
                'receipt_requested' => true,
                'intent_id' => $evidence->yape_payment_intent_id,
                'intent_status' => $evidence->intent?->status,
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        if (!$this->yapeReconciliationService->clientEvidenceHasDniResolutionFingerprint($evidence)) {
            $this->logOpenWaYape('info', 'yape_capture_ignored', [
                'evidence_id' => $evidence->id,
                'message_id' => $data['source_message_id'] ?? null,
                'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
                'image_saved_path' => $evidence->image_path,
                'is_valid_yape_capture' => true,
                'ignored_reason' => 'valid_yape_capture_without_dni_fingerprint',
                'response_sent' => false,
            ]);

            return response()->json([
                'ok' => true,
                'type' => 'openwa_client_evidence',
                'evidence_id' => $evidence->id,
                'status' => $evidence->status,
                'dni_requested' => false,
                'capture_incomplete' => true,
                'intent_id' => $evidence->yape_payment_intent_id,
                'intent_status' => $evidence->intent?->status,
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        // Si no se validó (porque desactivamos el match automático por teléfono), le enviamos DNI prompt
        $evidence = $this->yapeReconciliationService->markEvidenceAwaitingDni($evidence, [
            'reason' => 'valid_yape_capture',
        ]);
        $openWaData = $this->openWaService->openWaDataFromClientEvidence($evidence);

        $dniPromptSent = $this->openWaService->sendEvidenceTextMessageOnce(
            $evidence,
            $openWaData,
            self::YAPE_DNI_PROMPT_MESSAGE,
            'dni_prompt'
        );

        $this->appendYapeDirectDebug('yape_dni_prompt_attempt', [
            'evidence_id' => $evidence->id,
            'intent_id' => $evidence->yape_payment_intent_id,
            'chat_id' => $openWaData['source_chat_name'] ?? null,
            'message_text' => self::YAPE_DNI_PROMPT_MESSAGE,
            'api_url' => config('billing.yape.openwa.api_url'),
            'session' => config('billing.yape.openwa.session'),
            'send_result' => $dniPromptSent,
            'send_error' => $dniPromptSent ? null : 'sendEvidenceTextMessageOnce_returned_false',
        ]);

        $this->logOpenWaYape('info', 'yape_dni_prompt_result', [
            'evidence_id' => $evidence->id,
            'intent_id' => $evidence->yape_payment_intent_id,
            'message_id' => $data['source_message_id'] ?? null,
            'chat_id_normalized' => $data['openwa_chat_id'] ?? null,
            'response_sent' => $dniPromptSent,
        ]);

        return response()->json([
            'ok' => true,
            'type' => 'openwa_client_evidence',
            'evidence_id' => $evidence->id,
            'status' => $evidence->status,
            'dni_requested' => true,
            'intent_id' => $evidence->yape_payment_intent_id,
            'intent_status' => $evidence->intent?->status,
            'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
        ]);
    }

    public function health(Request $request)
    {
        $this->guardMonitorToken($request);

        $whatsappPath = config('billing.yape.whatsapp_inbox_path');

        return response()->json([
            'ok' => true,
            'monitor_token_configured' => filled(config('billing.yape.monitor_token')),
            'receiver_name_configured' => filled(config('billing.yape.receiver_name')),
            'receiver_phone_configured' => filled(config('billing.yape.receiver_phone')),
            'storage_disk' => config('billing.yape.storage_disk', 'local'),
            'client_evidence_dir' => config('billing.yape.client_evidence_dir'),
            'receiver_event_dir' => config('billing.yape.receiver_event_dir'),
            'whatsapp_inbox_path_configured' => filled($whatsappPath),
            'whatsapp_inbox_path_accessible' => $whatsappPath ? is_dir($whatsappPath) : false,
            'openwa_api_url_configured' => filled(config('billing.yape.openwa.api_url')),
            'openwa_api_key_configured' => filled(config('billing.yape.openwa.api_key')),
            'openwa_session_configured' => filled(config('billing.yape.openwa.session')),
            'openwa_webhook_secret_configured' => filled(config('billing.yape.openwa.webhook_secret')),
            'yape_bot_enabled' => (bool) config('billing.yape.bot_enabled', true),
            'openwa_ocr_enabled' => (bool) config('billing.yape.openwa.ocr_enabled', true),
            'openwa_ocr_parser_configured' => is_file((string) config('billing.yape.openwa.ocr_parser_path')),
            'ocr_mode' => config('billing.yape.ocr_mode', 'local'),
            'ocr_remote_url_configured' => filled(config('billing.yape.ocr_remote_url')),
            'ocr_remote_secret_configured' => filled(config('billing.yape.ocr_remote_secret')),
            'openwa_send_receipt_enabled' => (bool) config('billing.yape.openwa.send_receipt_on_validation', true),
            'adb_path_configured' => filled(config('billing.yape.adb_path')),
            'device_serial_configured' => filled(config('billing.yape.device_serial')),
            'time_tolerance_minutes' => (int) config('billing.yape.time_tolerance_minutes', 10),
            'confidence_threshold' => (float) config('billing.yape.confidence_threshold', 0.88),
        ]);
    }

    protected function guardMonitorToken(Request $request): void
    {
        $configuredToken = (string) config('billing.yape.monitor_token');
        $providedToken = (string) ($request->bearerToken() ?: $request->header('X-Yape-Monitor-Token'));

        if (!$configuredToken || !$providedToken || !hash_equals($configuredToken, $providedToken)) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'message' => 'Token interno Yape inválido o no configurado.',
            ], 401));
        }
    }

    protected function guardOpenWaWebhook(Request $request): void
    {
        $configuredSecret = (string) config('billing.yape.openwa.webhook_secret');
        $providedSecret = (string) (
            $request->header('X-OpenWA-Webhook-Secret')
            ?: $request->header('X-Yape-OpenWA-Secret')
        );

        if (!$configuredSecret) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'message' => 'Secreto webhook OpenWA no configurado.',
            ], 401));
        }

        if ($providedSecret && hash_equals($configuredSecret, $providedSecret)) {
            return;
        }

        $signature = (string) (
            $request->header('X-OpenWA-Signature')
            ?: $request->header('X-Hub-Signature-256')
            ?: $request->header('X-Signature')
        );

        $expected = hash_hmac('sha256', $request->getContent(), $configuredSecret);
        $signature = preg_replace('/^sha256=/i', '', $signature);

        if (!$signature || !hash_equals($expected, $signature)) {
            throw new HttpResponseException(response()->json([
                'ok' => false,
                'message' => 'Firma o secreto OpenWA inválido.',
            ], 401));
        }
    }

    protected function yapeDniErrorMessage(string $reason): string
    {
        return self::YAPE_DNI_ERROR_MESSAGES[$reason]
            ?? self::YAPE_DNI_ERROR_MESSAGES[OpenWaWebhookPayloadService::DNI_ERROR_MISSING];
    }

    protected function yapeDniResolutionMessage(string $reason): string
    {
        if ($reason === YapeReconciliationService::DNI_RESOLUTION_CUSTOMER_NOT_FOUND) {
            return self::YAPE_DNI_RETRY_MESSAGE;
        }

        if ($reason === YapeReconciliationService::DNI_RESOLUTION_NO_PENDING_PAYMENT) {
            return self::YAPE_NO_PENDING_DEBT_MESSAGE;
        }

        if ($reason === YapeReconciliationService::DNI_RESOLUTION_EVIDENCE_NOT_FOUND) {
            return self::YAPE_DNI_WITHOUT_CAPTURE_MESSAGE;
        }

        return self::YAPE_DNI_RESOLUTION_MESSAGES[$reason]
            ?? self::YAPE_DNI_RESOLUTION_MESSAGES[YapeReconciliationService::DNI_RESOLUTION_NO_PENDING_PAYMENT];
    }

    protected function yapePaymentValidatedMessage(?YapePaymentIntent $intent): string
    {
        return (($intent?->payment_mode ?? 'single') === 'multi_month')
            ? self::YAPE_MULTI_PAYMENT_VALIDATED_MESSAGE
            : self::YAPE_PAYMENT_VALIDATED_MESSAGE;
    }

    protected function isYapeCancelCommand(string $text): bool
    {
        $text = $this->normalizeYapeText($text);

        return in_array($text, [
            'cancelar',
            'cancela',
            'anular',
            'anula',
            'reiniciar',
            'reinicia',
            'nuevo',
            'empezar',
            'comenzar',
            'reset',
        ], true);
    }

    protected function normalizeYapeText(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return preg_replace('/[^\pL\pN]+/u', '', $text) ?: '';
    }

    protected function isPotentialServiceSelectionText(string $text): bool
    {
        return preg_match('/^\s*\d{1,2}\s*$/', $text) === 1;
    }

    protected function isPotentialDniAttemptText(string $text): bool
    {
        $text = mb_strtolower(trim($text));
        $digits = preg_replace('/\D+/', '', $text) ?: '';

        if (preg_match('/^[\d\s-]+$/u', $text) === 1) {
            return strlen($digits) >= 6;
        }

        foreach (['dni', 'documento', 'titular', 'cliente'] as $keyword) {
            if (str_contains($text, $keyword) && $digits !== '') {
                return true;
            }
        }

        return false;
    }

    protected function isCustomerMenuRequest(string $text): bool
    {
        $text = $this->normalizeYapeText($text);

        if ($text === '') {
            return false;
        }

        foreach (['hola', 'buenosdias', 'buenastardes', 'buenasnoches', 'menu', 'menú', 'opciones', 'ayuda', 'internet', 'averia', 'lento', 'problema', 'soporte', 'pago', 'recibo', 'boleta', 'constancia'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function handleInteractiveMenuSelection(array $data, string $selectionId)
    {
        if ($selectionId === 'support_outage') {
            $message = 'Recibimos tu reporte de avería. Un asesor revisará tu servicio y te atenderá por este medio.';
        } elseif ($selectionId === 'billing_payment') {
            $message = self::YAPE_DNI_WITHOUT_CAPTURE_MESSAGE;
        } elseif ($selectionId === 'billing_receipt') {
            $recentIntent = $this->recentYapeValidationEvidenceForOpenWaChat($data)?->intent;

            if ($recentIntent?->status === YapePaymentIntent::STATUS_VALIDATED) {
                $this->sendPaymentReceiptWithAudit($data, $recentIntent, 'interactive_menu_receipt', true);

                return response()->json([
                    'ok' => true,
                    'type' => 'openwa_menu_receipt_sent',
                    'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
                ]);
            }

            $message = 'No encontramos una constancia de pago reciente. Si realizaste un pago, envíanos tu captura Yape para validarlo.';
        } else {
            return response()->json([
                'ok' => true,
                'ignored' => true,
                'reason' => 'unknown_interactive_menu_selection',
                'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
            ]);
        }

        $sendResult = $this->openWaService->sendTextMessage($data, $message, [
            'message_context' => 'interactive_menu_' . $selectionId,
        ]);

        return response()->json([
            'ok' => true,
            'type' => 'openwa_menu_selection',
            'selection' => $selectionId,
            'response_sent' => (bool) ($sendResult['success'] ?? false),
            'controller_marker' => self::OPENWA_YAPE_CONTROLLER_MARKER,
        ]);
    }

    protected function isYapeRelatedText(string $text): bool
    {
        $normalized = $this->normalizeYapeText($text);

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/\d{4,}/', $normalized)) {
            return true;
        }

        foreach (['yape', 'pago', 'pagado', 'dni', 'recibo', 'deuda', 'validar'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function isReceiptRequestText(string $text): bool
    {
        $normalized = $this->normalizeYapeText($text);

        foreach (['recibo', 'constancia', 'comprobante', 'boleta'] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function recentYapeValidationEvidenceForOpenWaChat(array $data): ?YapeClientEvidence
    {
        $chatKeys = collect([
            $data['openwa_chat_id'] ?? null,
            $data['source_chat_name'] ?? null,
            $data['whatsapp_phone'] ?? null,
        ])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        if (empty($chatKeys)) {
            return null;
        }

        return YapeClientEvidence::with('intent')
            ->where('created_at', '>=', now()->subHours(2))
            ->where(function ($query) use ($chatKeys) {
                foreach ($chatKeys as $chatKey) {
                    $query->orWhere('whatsapp_phone', $chatKey)
                        ->orWhere('source_chat_name', $chatKey);
                }
            })
            ->whereHas('intent', function ($query) {
                $query->whereIn('status', [
                    YapePaymentIntent::STATUS_WAITING_RECEIVER,
                    YapePaymentIntent::STATUS_VALIDATED,
                ]);
            })
            ->latest()
            ->first();
    }

    protected function downloadOpenWaMediaAsBase64(string $mediaUrl): string
    {
        $url = $this->absoluteOpenWaUrl($mediaUrl);
        $apiKey = config('billing.yape.openwa.api_key');

        $response = Http::withHeaders(array_filter([
            'X-API-Key' => $apiKey,
        ]))
            ->timeout((int) config('billing.yape.openwa.http_timeout', 20))
            ->get($url);

        if (!$response->successful()) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo descargar la imagen desde OpenWA.',
            ]);
        }

        $contentType = $response->header('Content-Type') ?: 'image/png';

        return 'data:' . $contentType . ';base64,' . base64_encode($response->body());
    }

    protected function storedImageSize(?string $path): ?int
    {
        $disk = config('billing.yape.storage_disk', 'local');

        if (!$path || !Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->size($path);
    }

    protected function summarizeOcrResponse(mixed $response): ?array
    {
        if (!is_array($response)) {
            return null;
        }

        return [
            'ok' => $response['ok'] ?? null,
            'confidence' => $response['confidence'] ?? null,
            'amount' => $response['amount'] ?? null,
            'operation_code' => $response['operation_code'] ?? null,
            'security_code' => $response['security_code'] ?? null,
            'raw_text_length' => isset($response['raw_text']) ? mb_strlen((string) $response['raw_text']) : null,
        ];
    }

    protected function logOpenWaYape(string $level, string $message, array $context = []): void
    {
        try {
            \Log::channel('openwa_yape')->{$level}($message, $context);
        } catch (\Throwable) {
            \Log::{$level}($message, $context);
        }
    }

    protected function writeYapeControllerMarker(Request $request): void
    {
        $context = [
            'method' => __METHOD__,
            'file' => __FILE__,
            'line' => __LINE__,
            'headers' => $request->headers->all(),
            'payload_keys' => array_keys($request->all()),
            'raw_len' => strlen((string) $request->getContent()),
        ];

        try {
            \Log::channel('openwa_yape')->debug('YAPE_CONTROLLER_MARKER_2026_06_12', $context);
        } catch (\Throwable $exception) {
            try {
                \Log::debug('YAPE_CONTROLLER_MARKER_2026_06_12', array_merge($context, [
                    'openwa_yape_channel_error' => $exception->getMessage(),
                ]));
            } catch (\Throwable) {
                // El marcador directo de archivo de abajo es el respaldo final.
            }
        }

        $directLine = json_encode([
            'marker' => 'YAPE_CONTROLLER_MARKER_2026_06_12',
            'date' => now()->toDateTimeString(),
            'sessionId' => $request->input('sessionId'),
            'deliveryId' => $request->input('deliveryId'),
            'data_type' => $request->input('data.type'),
            'data_id' => $request->input('data.id'),
            'chatId' => $request->input('data.chatId'),
            'from' => $request->input('data.from'),
            'to' => $request->input('data.to'),
            'raw_len' => strlen((string) $request->getContent()),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            file_put_contents(
                storage_path('logs/openwa-yape-direct-debug.log'),
                $directLine . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $exception) {
            try {
                \Log::warning('YAPE_CONTROLLER_DIRECT_MARKER_FAILED_2026_06_12', [
                    'error' => $exception->getMessage(),
                    'target' => storage_path('logs/openwa-yape-direct-debug.log'),
                ]);
            } catch (\Throwable) {
                // No podemos hacer más sin romper el webhook.
            }
        }
    }

    protected function sendPaymentReceiptWithAudit(
        array $data,
        ?YapePaymentIntent $intent,
        string $stage,
        bool $force = false
    ): array {
        try {
            $result = $this->openWaService->sendPaymentReceipt($data, $intent, $force);
        } catch (\Throwable $exception) {
            $result = [
                'success' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $this->appendYapeDirectDebug('yape_payment_receipt_delivery_result', [
            'stage' => $stage,
            'intent_id' => $intent?->id,
            'intent_number' => $intent?->intent_number,
            'payment_id' => $intent?->payment_id,
            'invoice_id' => $intent?->invoice_id,
            'force' => $force,
            'success' => (bool) ($result['success'] ?? false),
            'result' => $result,
        ]);

        return $result;
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
            // Debug directo no debe romper el webhook.
        }
    }

    protected function absoluteOpenWaUrl(string $mediaUrl): string
    {
        if (preg_match('#^https?://#i', $mediaUrl)) {
            return $mediaUrl;
        }

        $baseUrl = rtrim((string) config('billing.yape.openwa.api_url'), '/');

        return $baseUrl . '/' . ltrim($mediaUrl, '/');
    }

    protected function ensureEvidenceImage(Request $request, array $fileFields = ['image']): void
    {
        $hasFile = collect($fileFields)->contains(fn($field) => $request->hasFile($field));

        if (!$hasFile && !$request->filled('image_base64') && !$request->filled('image_path')) {
            throw ValidationException::withMessages([
                'image' => 'Debe enviar una imagen, image_base64 o image_path.',
            ]);
        }
    }

    protected function privateImageResponse(?string $path)
    {
        $disk = config('billing.yape.storage_disk', 'local');

        if (!$path || !Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return response()->file(
            Storage::disk($disk)->path($path),
            ['Content-Type' => Storage::disk($disk)->mimeType($path) ?: 'image/png']
        );
    }

    public function previewReceipt(YapePaymentIntent $intent)
    {
        $intent->loadMissing([
            'customer',
            'contract.customer.addresses',
            'contract.plan',
            'invoice.items',
            'invoice.contract.customer.addresses',
            'payment.invoice.items',
            'clientEvidence',
            'receiverEvent',
        ]);

        if ($intent->payment_id && $intent->payment) {
            $receipt = app(\Modules\Billing\Services\PaymentReceiptService::class)->build($intent->payment, $intent);
            return view('billing::payments.print', [
                'receipt' => $receipt,
                'payment' => $intent->payment,
                'invoice' => $receipt['invoice'] ?? $intent->invoice,
            ]);
        }

        $invoice = $intent->invoice ?: $intent->contract?->invoices()?->latest()?->first();
        $dummyPayment = new \Modules\Billing\Entities\Payment([
            'amount' => (float) $intent->expected_amount,
            'payment_date' => $intent->created_at ?: now(),
            'payment_method' => 'Yape',
            'reference' => $intent->operation_code,
            'status' => 'completed',
            'payment_details' => [
                'yape_operation_code' => $intent->operation_code,
                'yape_security_code' => $intent->security_code,
                'preview_mode' => true,
            ],
        ]);
        $dummyPayment->id = 0;
        $dummyPayment->created_at = $intent->clientEvidence?->occurred_at 
            ?: ($intent->receiverEvent?->occurred_at 
                ?: ($intent->created_at ?: now()));

        if ($invoice) {
            $dummyPayment->setRelation('invoice', $invoice);
        }

        $receipt = app(\Modules\Billing\Services\PaymentReceiptService::class)->build($dummyPayment, $intent);

        return view('billing::payments.print', [
            'receipt' => $receipt,
            'payment' => $dummyPayment,
            'invoice' => $invoice,
        ]);
    }

    protected function statuses(): array
    {
        return [
            YapePaymentIntent::STATUS_WAITING_CLIENT => 'Esperando WhatsApp',
            YapePaymentIntent::STATUS_WAITING_RECEIVER => 'Esperando receptor',
            YapePaymentIntent::STATUS_VALIDATED => 'Validado',
            YapePaymentIntent::STATUS_OBSERVED => 'Observado',
            YapePaymentIntent::STATUS_CANCELLED => 'Cancelado',
        ];
    }
}
