<?php

namespace Modules\Billing\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Billing\Repositories\PaymentRepository;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Services\MultiMonthPaymentSurgicalRepairService;
use Modules\Billing\Services\OpenWaService;
use Modules\Billing\Services\PaymentReceiptService;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Services\YapeReconciliationService;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Services\DuplicateContractRepairService;
use Modules\Customer\Entities\Customer;
use Modules\Core\Entities\AuditLog;
use Modules\Operations\Entities\OnboardingOrder;

class PaymentController extends Controller
{
    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * @var YapeReconciliationService
     */
    protected $yapeReconciliationService;

    /**
     * PaymentController constructor.
     */
    public function __construct(
        PaymentRepository $paymentRepository,
        InvoiceRepository $invoiceRepository,
        PaymentService $paymentService,
        YapeReconciliationService $yapeReconciliationService
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->paymentService = $paymentService;
        $this->yapeReconciliationService = $yapeReconciliationService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $paymentMethod = $request->get('payment_method');
        $from = $request->get('from');
        $to = $request->get('to');
        $perPage = $request->get('per_page', 10);

        $query = $this->paymentRepository->query()
            ->with(['invoice.contract.customer']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('invoice', function ($q) use ($search) {
                        $q->where('invoice_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        if ($from) {
            $query->where('payment_date', '>=', $from);
        }

        if ($to) {
            $query->where('payment_date', '<=', $to);
        }

        $payments = $query->orderBy('payment_date', 'desc')->paginate($perPage);

        $statuses = [
            'pending' => 'Pendiente',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'refunded' => 'Reembolsado',
        ];

        $paymentMethods = config('billing.payment.methods');

        return view('billing::payments.index', compact(
            'payments',
            'search',
            'status',
            'paymentMethod',
            'from',
            'to',
            'statuses',
            'paymentMethods'
        ));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Renderable
     */
    public function create(Request $request)
    {
        $invoiceId = $request->get('invoice_id');
        $contractId = $request->get('contract_id');
        $paymentMode = $request->get('mode', 'single');
        $invoice = null;
        $contract = null;

        if ($invoiceId) {
            $invoice = $this->invoiceRepository->find($invoiceId);
            $invoice?->loadMissing(['contract.customer', 'contract.plan', 'contract.invoices.payments', 'contract.invoices.creditNotes']);
            $contract = $invoice?->contract;
        }

        if (! $contract && $contractId) {
            $contract = Contract::with(['customer', 'plan', 'invoices.payments', 'invoices.creditNotes'])->find($contractId);
        }

        // Obtener lista de facturas pendientes para el dropdown
        $pendingInvoices = $this->invoiceRepository->getPendingInvoices();
        $contractPendingInvoices = collect();
        $monthlyAmount = 0;
        $advancePaymentBasePeriod = null;

        if ($contract) {
            $contract->loadMissing(['contractedServices', 'invoices.payments', 'invoices.creditNotes']);
            $contractPendingInvoices = $contract->invoices
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->sortBy('due_date')
                ->values();
            $advancePaymentBasePeriod = $this->advancePaymentBasePeriod($contract);

            $monthlyAmount = $contract->final_price !== null
                ? (float) $contract->final_price
                : (float) (optional($contract->plan)->price ?: 0);
            if ($contract->final_price === null) {
                foreach ($contract->contractedServices as $service) {
                    $monthlyAmount += (float) $service->price;
                }
            }
        }

        // Obtener métodos de pago
        $paymentMethods = config('billing.payment.methods');

        // Fecha por defecto (hoy)
        $paymentDate = Carbon::now()->format('Y-m-d');

        return view('billing::payments.create', compact(
            'invoice',
            'contract',
            'contractPendingInvoices',
            'advancePaymentBasePeriod',
            'monthlyAmount',
            'paymentMode',
            'pendingInvoices',
            'paymentMethods',
            'paymentDate'
        ));
    }

    public function createForCustomer(int $customerId)
    {
        $customer = Customer::with([
            'contracts.plan',
            'contracts.invoices.payments',
            'contracts.invoices.creditNotes',
        ])->findOrFail($customerId);
        $pendingInvoices = $customer->contracts
            ->flatMap(fn (Contract $contract) => $contract->invoices
                ->filter(fn (Invoice $invoice) => in_array($invoice->status, ['pending', 'partial', 'overdue'], true)
                    && (float) $invoice->pending_amount > 0.009)
                ->map(function (Invoice $invoice) use ($contract) {
                    $invoice->setRelation('contract', $contract);

                    return $invoice;
                }))
            ->sortBy(fn (Invoice $invoice) => sprintf('%s|%010d', optional($invoice->due_date)->format('Y-m-d') ?: '9999-12-31', $invoice->id))
            ->values();
        $invoice = $pendingInvoices->first();

        if (! $invoice) {
            return redirect()->back()->with('warning', 'El cliente no tiene recibos pendientes para pagar.');
        }

        return redirect()->route('billing.payments.create', [
            'invoice_id' => (int) $invoice->id,
        ]);
    }

    public function storeForCustomer(Request $request, int $customerId)
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'required|integer|distinct|exists:invoices,id',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|not_in:yape',
            'reference' => 'nullable|string|max:255',
            'received_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ], [
            'invoice_ids.required' => 'Seleccione al menos un recibo.',
            'payment_method.not_in' => 'Los pagos Yape deben validarse mediante el flujo automático de Yape.',
        ]);

        $invoices = Invoice::query()
            ->with(['contract.customer', 'payments', 'creditNotes'])
            ->whereIn('id', $validated['invoice_ids'])
            ->whereHas('contract', fn ($query) => $query->where('customer_id', $customerId))
            ->get();

        if ($invoices->count() !== count(array_unique($validated['invoice_ids']))) {
            return redirect()->back()->withInput()->with('error', 'Uno o más recibos no pertenecen al cliente seleccionado.');
        }

        $amount = round((float) $invoices->sum(fn (Invoice $invoice) => (float) $invoice->pending_amount), 2);
        $isCash = $validated['payment_method'] === 'cash';
        $receivedAmount = $isCash ? round((float) ($validated['received_amount'] ?? 0), 2) : null;

        if ($isCash && $receivedAmount + 0.009 < $amount) {
            return redirect()->back()->withInput()->withErrors([
                'received_amount' => 'El monto recibido no puede ser menor al total seleccionado de S/ '.number_format($amount, 2).'.',
            ]);
        }
        if (! $isCash && blank($validated['reference'] ?? null)) {
            return redirect()->back()->withInput()->withErrors([
                'reference' => 'Ingrese la referencia o número de operación.',
            ]);
        }

        $result = $this->paymentService->registerMultiInvoicePayment([
            'amount' => $amount,
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'received_amount' => $receivedAmount,
            'change_amount' => $isCash ? round(max($receivedAmount - $amount, 0), 2) : 0,
            'allow_overpayment_credit' => false,
            'payment_details' => [
                'source' => 'manual_multi_service_payment',
                'customer_id' => $customerId,
            ],
            'user_id' => auth()->id(),
        ], $invoices->pluck('id')->all());

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        $this->syncOnboardingOrdersAfterPayments($result['payments']);

        return redirect()->route('billing.payments.show', $result['payment']->id)
            ->with('success', 'Pago aplicado correctamente a los servicios seleccionados.');
    }

    protected function advancePaymentBasePeriod(Contract $contract): ?string
    {
        $latestPeriod = $contract->invoices
            ->whereNotIn('status', ['cancelled', 'void'])
            ->map(fn ($invoice) => $this->invoicePeriodStart($invoice))
            ->filter()
            ->sortByDesc(fn (Carbon $period) => $period->timestamp)
            ->first();

        return $latestPeriod?->format('m/Y');
    }

    protected function invoicePeriodStart($invoice): ?Carbon
    {
        $periodDate = $this->parseBillingPeriod($invoice->billing_period);

        if ($periodDate) {
            return $periodDate;
        }

        return ! empty($invoice->issue_date)
            ? Carbon::parse($invoice->issue_date)->startOfMonth()
            : null;
    }

    protected function parseBillingPeriod(?string $billingPeriod): ?Carbon
    {
        $billingPeriod = trim((string) $billingPeriod);

        if ($billingPeriod === '') {
            return null;
        }

        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $billingPeriod, $matches, PREG_SET_ORDER)) {
            $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];

            return Carbon::create((int) $targetMatch[3], (int) $targetMatch[2], 1)->startOfMonth();
        }

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $billingPeriod, $matches)) {
            return Carbon::create((int) $matches[2], (int) $matches[1], 1)->startOfMonth();
        }

        $normalized = strtr(strtolower($billingPeriod), [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
        ]);

        if (! preg_match('/(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\s+(\d{4})/', $normalized, $matches)) {
            return null;
        }

        $months = [
            'enero' => 1,
            'febrero' => 2,
            'marzo' => 3,
            'abril' => 4,
            'mayo' => 5,
            'junio' => 6,
            'julio' => 7,
            'agosto' => 8,
            'septiembre' => 9,
            'setiembre' => 9,
            'octubre' => 10,
            'noviembre' => 11,
            'diciembre' => 12,
        ];

        return Carbon::create((int) $matches[2], $months[$matches[1]], 1)->startOfMonth();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($request->filled('duplicate_contract_repair_case')) {
            $validated = $request->validate([
                'duplicate_contract_repair_case' => 'required|in:'.implode(',', [
                    DuplicateContractRepairService::GREGORIO_122,
                    DuplicateContractRepairService::GREGORIO_253_254_PHYSICAL,
                    DuplicateContractRepairService::LEONIDAS_37_38,
                    DuplicateContractRepairService::DELIA_159_160,
                ]),
            ]);

            try {
                $service = app(DuplicateContractRepairService::class);
                $result = $validated['duplicate_contract_repair_case'] === DuplicateContractRepairService::GREGORIO_122
                    ? $service->repairGregorio(auth()->id(), $request->ip())
                    : $service->physicallyDeleteConfirmedDuplicate(
                        $validated['duplicate_contract_repair_case'],
                        auth()->id(),
                        $request->ip()
                    );

                return redirect()->route('billing.notebook', ['mode' => 'contract-duplicates'])
                    ->with($result['skipped'] ? 'warning' : 'success', $result['message']);
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->route('billing.notebook', ['mode' => 'contract-duplicates'])
                    ->with('error', 'No se aplicó ningún cambio: '.$exception->getMessage());
            }
        }

        if ($request->filled('surgical_repair_case')) {
            $validated = $request->validate([
                'surgical_repair_case' => 'required|in:'.implode(',', [
                    MultiMonthPaymentSurgicalRepairService::JOSE_379,
                    MultiMonthPaymentSurgicalRepairService::VICTORIA_400,
                ]),
            ]);

            try {
                $result = app(MultiMonthPaymentSurgicalRepairService::class)->repair(
                    $validated['surgical_repair_case'],
                    auth()->id(),
                    $request->ip()
                );

                return redirect()->route('billing.notebook', ['mode' => 'multimonth-surgical'])
                    ->with($result['skipped'] ? 'warning' : 'success', $result['message']);
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->route('billing.notebook', ['mode' => 'multimonth-surgical'])
                    ->with('error', 'No se aplicó ningún cambio: '.$exception->getMessage());
            }
        }

        $paymentMode = $request->input('payment_mode', 'single');

        $rules = [
            'payment_mode' => 'nullable|in:single,multi_month',
            'invoice_id' => $paymentMode === 'multi_month' ? 'nullable|exists:invoices,id' : 'required|exists:invoices,id',
            'contract_id' => $paymentMode === 'multi_month' ? 'required_without:invoice_id|exists:contracts,id' : 'nullable|exists:contracts,id',
            'amount' => 'nullable|numeric|min:0',
            'months_to_pay' => $paymentMode === 'multi_month' ? 'required|integer|min:1|max:24' : 'nullable|integer|min:1|max:24',
            'payment_date' => 'required|date',
            'payment_time' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'received_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'invoice_discounts' => 'nullable|array',
            'invoice_discounts.*' => 'nullable|numeric|min:0',
            'discount_reason' => 'nullable|string|max:1000',
        ];

        $validated = $request->validate($rules, [
            'discount_amount.min' => 'El descuento no puede ser negativo.',
        ]);

        $fullPaymentDate = $validated['payment_date'];
        if ($request->filled('payment_time')) {
            $fullPaymentDate .= ' ' . $request->input('payment_time');
        }

        $discountAmount = round((float) ($validated['discount_amount'] ?? 0), 2);
        $multiInvoiceDiscounts = collect($validated['invoice_discounts'] ?? [])
            ->map(fn ($amount) => round((float) $amount, 2))
            ->filter(fn (float $amount) => $amount > 0);
        $totalMultiDiscount = round((float) $multiInvoiceDiscounts->sum(), 2);

        if (($validated['payment_mode'] ?? 'single') === 'multi_month') {
            $isCash = $validated['payment_method'] === 'cash';
            $amount = round((float) $validated['amount'], 2);
            $receivedAmount = $isCash ? round((float) ($validated['received_amount'] ?? 0), 2) : null;

            if ($amount > 0 && blank($validated['payment_method'] ?? null)) {
                return redirect()->back()->withInput()->withErrors([
                    'payment_method' => 'Seleccione un método de pago.',
                ]);
            }

            if ($amount <= 0 && $totalMultiDiscount <= 0) {
                return redirect()->back()->withInput()->withErrors([
                    'amount' => 'Ingrese un monto de pago mayor a S/ 0.00.',
                ]);
            }

            if ($isCash && $receivedAmount + 0.009 < $amount) {
                return redirect()->back()->withInput()->withErrors([
                    'received_amount' => 'El monto recibido no puede ser menor al monto aplicado.',
                ]);
            }

            if ($amount > 0 && ! $isCash && blank($validated['reference'] ?? null)) {
                return redirect()->back()->withInput()->withErrors([
                    'reference' => 'Ingrese la referencia o número de operación para este método de pago.',
                ]);
            }

            $saveCredit = $request->boolean('save_overpayment_as_credit');
            $effectiveAmount = ($saveCredit && $isCash && $receivedAmount > $amount) ? $receivedAmount : $amount;
            $changeAmount = ($saveCredit || ! $isCash) ? 0 : round(max($receivedAmount - $amount, 0), 2);

            $data = [
                'invoice_id' => $validated['invoice_id'] ?? null,
                'contract_id' => $validated['contract_id'] ?? null,
                'amount' => $effectiveAmount,
                'months_to_pay' => (int) $validated['months_to_pay'],
                'payment_date' => $fullPaymentDate,
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount,
                'save_overpayment_as_credit' => $saveCredit,
                'payment_details' => [],
                'invoice_discounts' => $multiInvoiceDiscounts->all(),
                'discount_reason' => $validated['discount_reason'] ?? null,
                'user_id' => auth()->id(),
            ];

            $result = $this->paymentService->registerMultiMonthPayment($data);

            if (! $result['success']) {
                return redirect()->back()->withInput()->with('error', $result['message']);
            }

            $this->syncOnboardingOrdersAfterPayments($result['payments'] ?? collect([$result['payment']]));

            if ($request->has('redirect_to')) {
                return redirect($request->redirect_to)
                    ->with('success', 'Pago multimes registrado correctamente.');
            }

            if (! empty($result['payment'])) {
                return redirect()->route('billing.payments.show', $result['payment']->id)
                    ->with('success', $totalMultiDiscount > 0 ? 'Descuentos y pago multimes registrados correctamente.' : 'Pago multimes registrado correctamente.');
            }

            return redirect()->route('billing.invoices.show', $result['invoice']->id)
                ->with('success', 'Descuentos aplicados correctamente.');
        }

        $invoice = $this->invoiceRepository->find($validated['invoice_id']);

        if (! $invoice) {
            return redirect()->back()->withInput()->with('error', 'Recibo no encontrado.');
        }

        $pendingAmount = round((float) $invoice->pending_amount, 2);

        if ($discountAmount > $pendingAmount) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['discount_amount' => 'El descuento no puede superar el importe pendiente de S/ '.number_format($pendingAmount, 2).'.']);
        }

        $amount = $discountAmount > 0
            ? round($pendingAmount - $discountAmount, 2)
            : round((float) ($validated['amount'] ?? 0), 2);

        if ($amount <= 0 && $discountAmount <= 0) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['amount' => 'Ingrese un monto de pago mayor a S/ 0.00.']);
        }

        if ($amount > $pendingAmount) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['amount' => 'El monto aplicado no puede superar el importe pendiente de S/ '.number_format($pendingAmount, 2).'.']);
        }

        $paymentMethod = $validated['payment_method'] ?? null;

        if ($amount > 0 && blank($paymentMethod)) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method' => 'Seleccione un método de pago.',
            ]);
        }

        $isCash = $paymentMethod === 'cash';
        $receivedAmount = $isCash ? round((float) ($validated['received_amount'] ?? 0), 2) : null;

        if ($isCash && $receivedAmount + 0.009 < $amount) {
            return redirect()->back()->withInput()->withErrors([
                'received_amount' => 'El monto recibido no puede ser menor al monto aplicado.',
            ]);
        }

        if ($amount > 0 && ! $isCash && blank($validated['reference'] ?? null)) {
            return redirect()->back()->withInput()->withErrors([
                'reference' => 'Ingrese la referencia o número de operación para este método de pago.',
            ]);
        }

        $changeAmount = $isCash ? round(max($receivedAmount - $amount, 0), 2) : 0;

        $data = [
            'invoice_id' => $validated['invoice_id'],
            'amount' => $amount,
            'payment_date' => $fullPaymentDate,
            'payment_method' => $paymentMethod,
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'payment_details' => [
                'received_amount' => $receivedAmount,
                'change_amount' => $changeAmount,
                'cashier_context' => 'manual_payment_form',
                'registered_by_user_id' => auth()->id(),
            ],
        ];
        $data['user_id'] = auth()->id();
        $data['status'] = 'completed';

        $result = $discountAmount > 0
            ? $this->paymentService->registerPaymentWithFixedDiscount(
                $data,
                $discountAmount,
                $validated['discount_reason'] ?? null
            )
            : $this->paymentService->registerPayment($data);

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        if (! empty($result['payment'])) {
            $this->syncOnboardingOrdersAfterPayments(collect([$result['payment']]));
        }

        if ($request->has('redirect_to')) {
            return redirect($request->redirect_to)
                ->with('success', $discountAmount > 0 ? 'Descuento aplicado correctamente.' : 'Pago registrado correctamente.');
        }

        if (! empty($result['payment'])) {
            return redirect()->route('billing.payments.show', $result['payment']->id)
                ->with('success', $discountAmount > 0 ? 'Descuento aplicado y pago registrado correctamente.' : 'Pago registrado correctamente.');
        }

        return redirect()->route('billing.invoices.show', $invoice->id)
            ->with('success', 'Descuento aplicado correctamente. El recibo quedó cancelado.');
    }

    protected function createYapeValidationIntent(array $validated)
    {
        $paymentMode = $validated['payment_mode'] ?? 'single';
        $invoice = null;
        $contract = null;

        if (! empty($validated['invoice_id'])) {
            $invoice = $this->invoiceRepository->find($validated['invoice_id']);
            $invoice?->loadMissing(['contract.customer', 'contract.invoices.payments', 'contract.invoices.creditNotes']);
            $contract = $invoice?->contract;
        }

        if (! $contract && ! empty($validated['contract_id'])) {
            $contract = Contract::with(['customer', 'invoices.payments', 'invoices.creditNotes'])->find($validated['contract_id']);
        }

        if ($paymentMode !== 'multi_month' && ! $invoice) {
            return redirect()->back()->withInput()->with('error', 'Recibo no encontrado.');
        }

        if ($paymentMode === 'multi_month' && ! $contract) {
            return redirect()->back()->withInput()->with('error', 'Contrato no encontrado para crear la validación Yape.');
        }

        $amount = round((float) $validated['amount'], 2);

        if ($paymentMode !== 'multi_month') {
            $pendingAmount = round((float) $invoice->pending_amount, 2);

            if ($amount > $pendingAmount) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['amount' => 'El monto aplicado no puede superar el importe pendiente de S/ '.number_format($pendingAmount, 2).'.']);
            }
        }

        $intent = $this->yapeReconciliationService->createIntent([
            'payment_mode' => $paymentMode,
            'invoice_id' => $validated['invoice_id'] ?? null,
            'contract_id' => $validated['contract_id'] ?? $contract?->id,
            'amount' => $amount,
            'months_to_pay' => $validated['months_to_pay'] ?? null,
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
            'user_id' => auth()->id(),
        ], $invoice, $contract);

        return redirect()->route('billing.yape.show', $intent->id)
            ->with('success', 'Validación Yape creada. El pago NO se completará hasta cruzar WhatsApp y la notificación del Yape receptor.');
    }

    protected function syncOnboardingOrdersAfterPayments($payments): void
    {
        foreach (collect($payments) as $payment) {
            $payment->loadMissing('invoice.contract.invoices.payments', 'invoice.contract.invoices.creditNotes');
            $invoice = $payment->invoice?->fresh(['contract.invoices.payments', 'contract.invoices.creditNotes']);

            if (! $invoice || ! $invoice->contract) {
                continue;
            }

            $order = OnboardingOrder::query()
                ->where('invoice_id', $invoice->id)
                ->orWhere('contract_id', $invoice->contract_id)
                ->latest()
                ->first();

            if (! $order) {
                continue;
            }

            if ($invoice->status === 'paid') {
                $hasPendingInvoices = $invoice->contract->invoices
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->isNotEmpty();

                $order->update([
                    'status' => $hasPendingInvoices ? OnboardingOrder::STATUS_INVOICED : OnboardingOrder::STATUS_PAID,
                    'paid_at' => $hasPendingInvoices ? $order->paid_at : now(),
                ]);
            }
        }
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Renderable
     */
    public function show($id)
    {
        $payment = $this->paymentRepository->find($id);

        // Cargar relaciones
        $payment->load(['invoice.contract.customer', 'user']);

        // Obtener logs de auditoría relacionados con este pago
        $logs = AuditLog::where('module', 'payments')
            ->where('action_detail', 'like', "%{$payment->invoice->invoice_number}%")
            ->orderBy('action_date', 'desc')
            ->limit(5)
            ->get();

        return view('billing::payments.show', compact('payment', 'logs'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Renderable
     */
    public function edit($id)
    {
        $payment = $this->paymentRepository->find($id);

        // Solo se pueden editar pagos pendientes
        if ($payment->status !== 'pending') {
            return redirect()->route('billing.payments.show', $id)
                ->with('error', 'Solo se pueden editar pagos en estado pendiente.');
        }

        // Cargar relaciones
        $payment->load(['invoice']);

        // Obtener métodos de pago
        $paymentMethods = config('billing.payment.methods');

        return view('billing::payments.edit', compact('payment', 'paymentMethods'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,completed,failed',
        ]);

        $payment = $this->paymentRepository->find($id);

        // Solo se pueden editar pagos pendientes
        if ($payment->status !== 'pending') {
            return redirect()->route('billing.payments.show', $id)
                ->with('error', 'Solo se pueden editar pagos en estado pendiente.');
        }

        $data = $request->all();

        // Guardar datos anteriores para auditoría
        $oldData = $payment->toArray();

        // Actualizar pago
        $this->paymentRepository->update($id, $data);

        // Si el estado cambia a completado, actualizar la factura
        if ($data['status'] === 'completed' && $payment->status !== 'completed') {
            $invoice = $payment->invoice;
            $totalPaid = $invoice->payments()->where('status', 'completed')->sum('amount');
            $creditNoteAmount = $invoice->creditNotes()->where('status', 'applied')->sum('amount');

            if ($totalPaid + $creditNoteAmount >= $invoice->total_amount) {
                $invoice->update(['status' => 'paid']);
            } elseif ($totalPaid + $creditNoteAmount > 0) {
                $invoice->update(['status' => 'partial']);
            }
        }

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'payment_updated',
            'payments',
            "Pago actualizado para recibo: {$payment->invoice->invoice_number}",
            request()->ip(),
            $oldData,
            $payment->refresh()->toArray()
        );

        return redirect()->route('billing.payments.show', $id)
            ->with('success', 'Pago actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $payment = $this->paymentRepository->find($id);

        // Solo se pueden eliminar pagos pendientes
        if ($payment->status !== 'pending') {
            return redirect()->route('billing.payments.index')
                ->with('error', 'Solo se pueden eliminar pagos en estado pendiente.');
        }

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'payment_deleted',
            'payments',
            "Pago eliminado para recibo: {$payment->invoice->invoice_number}",
            request()->ip(),
            $payment->toArray(),
            null
        );

        // Eliminar pago
        $this->paymentRepository->delete($id);

        return redirect()->route('billing.payments.index')
            ->with('success', 'Pago eliminado correctamente.');
    }

    /**
     * Void the specified payment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function void(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $result = $this->paymentService->voidPayment($id, $request->reason);

        if (! $result['success']) {
            return redirect()->route('billing.payments.show', $id)
                ->with('error', $result['message']);
        }

        return redirect()->route('billing.payments.show', $id)
            ->with('success', 'Pago anulado correctamente.');
    }

    /**
     * Print payment receipt.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function printReceipt($id)
    {
        $payment = $this->paymentRepository->find($id);

        // Cargar relaciones
        $payment->load(['invoice.items', 'invoice.contract.customer.addresses', 'invoice.contract.plan', 'user', 'yapePaymentIntent']);
        $receipt = app(PaymentReceiptService::class)->build($payment, $payment->yapePaymentIntent);
        $invoice = $receipt['invoice'];

        // Aquí implementarías la generación del PDF con alguna librería como DOMPDF
        // Por ahora, solo mostraremos una vista simplificada

        return view('billing::payments.print', [
            'receipt' => $receipt,
            'payment' => $receipt['payment'],
            'invoice' => $invoice,
        ]);
    }

    /**
     * Display payment report.
     *
     * @return Renderable
     */
    public function report(Request $request)
    {
        $from = $request->get('from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to = $request->get('to', Carbon::now()->format('Y-m-d'));
        $paymentMethod = $request->get('payment_method');
        $customerId = $request->get('customer_id');

        $filters = [
            'payment_method' => $paymentMethod,
            'customer_id' => $customerId,
        ];

        $report = $this->paymentService->getPaymentReport($from, $to, $filters);

        $paymentMethods = config('billing.payment.methods');

        return view('billing::payments.report', compact('report', 'from', 'to', 'paymentMethod', 'customerId', 'paymentMethods'));
    }

    /**
     * Registrar abono manual de saldo a favor para el cliente.
     */
    public function addCredit(Request $request, $customerId)
    {
        $request->validate([
            'action' => 'required|in:add,consume',
            'amount' => 'required|numeric|min:0.01',
            'contract_id' => 'nullable|integer|exists:contracts,id',
            'description' => 'nullable|string|max:255',
        ]);

        $customer = \Modules\Customer\Entities\Customer::findOrFail($customerId);
        $action = $request->input('action');
        $amount = (float) $request->input('amount');
        $contractId = $request->input('contract_id') ? (int) $request->input('contract_id') : null;

        try {
            $creditService = app(\Modules\Billing\Services\CustomerCreditService::class);

            if ($action === 'consume') {
                $description = $request->input('description') ?: 'Retiro / Ajuste manual de saldo a favor';
                $creditService->consumeCredit([
                    'customer_id' => $customer->id,
                    'contract_id' => $contractId,
                    'amount' => $amount,
                    'description' => $description,
                    'source_type' => 'manual_adjustment',
                    'created_by' => auth()->id(),
                ]);

                $message = 'Retiro/Ajuste manual de S/ '.number_format($amount, 2).' registrado con éxito.';
            } else {
                $description = $request->input('description') ?: 'Abono manual de saldo a favor';
                $creditService->addCredit([
                    'customer_id' => $customer->id,
                    'contract_id' => $contractId,
                    'amount' => $amount,
                    'description' => $description,
                    'created_by' => auth()->id(),
                ]);

                $applied = $creditService->applyAvailableCreditToPendingInvoices($customer, $contractId, true);

                $message = 'Abono manual de S/ '.number_format($amount, 2).' registrado con éxito.';
                if (! empty($applied)) {
                    $message .= ' Se aplicó automáticamente a las facturas pendientes.';
                }
            }

            return redirect()->route('customer.customers.show', $customer->id)
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Error registrando/ajustando saldo a favor manual: '.$e->getMessage(), [
                'customer_id' => $customerId,
                'action' => $action,
                'exception' => $e,
            ]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'No se pudo realizar la transacción. Inténtelo nuevamente o contacte al administrador.']);
        }
    }

    /**
     * Enviar constancia de pago PDF a WhatsApp.
     */
    public function sendWhatsApp(Request $request, $id)
    {
        $request->validate([
            'phone' => 'required|string|min:9|max:20',
        ]);

        $payment = $this->paymentRepository->find($id);

        if (! $payment) {
            return redirect()->back()->with('error', 'Pago no encontrado.');
        }

        if ($payment->status !== 'completed') {
            return redirect()->back()->with('error', 'Solo se pueden enviar recibos de pagos completados.');
        }

        $phone = $request->input('phone');

        try {
            $openWaService = app(OpenWaService::class);
            $result = $openWaService->sendPaymentReceiptDirect($payment, $phone);

            if ($result['success'] ?? false) {
                return redirect()->back()->with('success', 'Recibo de pago enviado a WhatsApp correctamente.');
            }

            return redirect()->back()->with('error', 'No se pudo enviar el WhatsApp: '.($result['error'] ?? 'Error desconocido'));
        } catch (\Throwable $e) {
            \Log::error('Error enviando recibo a WhatsApp: '.$e->getMessage(), [
                'payment_id' => $id,
                'phone' => $phone,
                'exception' => $e,
            ]);

            return redirect()->back()->with('error', 'No se pudo enviar el recibo por WhatsApp. Inténtelo nuevamente o contacte al administrador.');
        }
    }
}
