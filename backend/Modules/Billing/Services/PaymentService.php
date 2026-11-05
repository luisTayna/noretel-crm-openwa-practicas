<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\InvoiceItem;
use Modules\Billing\Repositories\CreditNoteRepository;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Billing\Repositories\PaymentRepository;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\AuditLog;

class PaymentService
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
     * @var CreditNoteRepository
     */
    protected $creditNoteRepository;

    /**
     * PaymentService constructor.
     */
    public function __construct(
        PaymentRepository $paymentRepository,
        InvoiceRepository $invoiceRepository,
        CreditNoteRepository $creditNoteRepository,
        protected InvoicePeriodGuardService $invoicePeriodGuard
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->creditNoteRepository = $creditNoteRepository;
    }

    /**
     * Registrar un pago
     *
     * @return array
     */
    public function registerPayment(array $data)
    {
        try {
            DB::beginTransaction();

            // Verificar que la factura exista
            $invoice = $this->invoiceRepository->find($data['invoice_id']);
            if (! $invoice) {
                return [
                    'success' => false,
                    'message' => 'Recibo no encontrado',
                ];
            }

            // Verificar que la factura no esté anulada o completamente pagada
            if (in_array($invoice->status, ['void', 'paid'])) {
                return [
                    'success' => false,
                    'message' => 'No se puede registrar pago para un recibo anulado o completamente pagado',
                ];
            }

            // Establecer usuario que registra el pago
            if (! isset($data['user_id'])) {
                $data['user_id'] = auth()->id();
            }

            // Establecer estado del pago
            if (! isset($data['status'])) {
                $data['status'] = 'completed';
            }

            // Crear el pago y actualizar estado de la factura
            $payment = $this->paymentRepository->createPaymentAndUpdateInvoice($data);

            // Registrar en log de auditoría
            AuditLog::register(
                auth()->id(),
                'payment_registered',
                'payments',
                "Pago registrado para recibo: {$invoice->invoice_number}",
                request()->ip(),
                null,
                $payment->toArray()
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pago registrado correctamente',
                'payment' => $payment,
            ];
        } catch (\Exception $e) {
            DB::rollback();

            return [
                'success' => false,
                'message' => 'Error al registrar el pago: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Apply a fixed discount as an applied credit note and register the
     * remaining payment in the same transaction.
     *
     * @return array
     */
    public function registerPaymentWithFixedDiscount(array $data, float $discountAmount, ?string $discountReason = null): array
    {
        try {
            DB::beginTransaction();

            $invoice = Invoice::query()->lockForUpdate()->find($data['invoice_id']);

            if (! $invoice || in_array($invoice->status, ['void', 'paid'], true)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'No se puede aplicar un descuento a este recibo.',
                ];
            }

            $pendingAmount = round((float) $invoice->pending_amount, 2);
            $discountAmount = round($discountAmount, 2);

            if ($discountAmount <= 0 || $discountAmount > $pendingAmount) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'El descuento debe ser mayor a S/ 0.00 y no puede superar el saldo pendiente.',
                ];
            }

            $creditNote = $this->creditNoteRepository->create([
                'invoice_id' => $invoice->id,
                'credit_note_number' => $this->creditNoteRepository->generateCreditNoteNumber(),
                'amount' => $discountAmount,
                'issue_date' => $data['payment_date'],
                'status' => 'active',
                'reason' => $discountReason ?: 'Descuento fijo aplicado al registrar pago',
                'notes' => $data['notes'] ?? null,
                'user_id' => $data['user_id'] ?? auth()->id(),
            ]);
            $this->creditNoteRepository->applyCreditNote($creditNote->id);

            AuditLog::register(
                auth()->id(),
                'payment_discount_applied',
                'credit_notes',
                "Descuento fijo de S/ {$discountAmount} aplicado al recibo: {$invoice->invoice_number}",
                request()->ip(),
                null,
                $creditNote->fresh()->toArray()
            );

            $remainingAmount = round($pendingAmount - $discountAmount, 2);
            $payment = null;

            if ($remainingAmount > 0) {
                $data['amount'] = $remainingAmount;
                $data['user_id'] ??= auth()->id();
                $data['status'] ??= 'completed';
                $payment = $this->paymentRepository->createPaymentAndUpdateInvoice($data);

                AuditLog::register(
                    auth()->id(),
                    'payment_registered',
                    'payments',
                    "Pago con descuento registrado para recibo: {$invoice->invoice_number}",
                    request()->ip(),
                    null,
                    $payment->toArray()
                );
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Descuento aplicado y pago registrado correctamente.',
                'payment' => $payment,
                'credit_note' => $creditNote->fresh(),
                'invoice' => $invoice->fresh(),
            ];
        } catch (\Throwable $exception) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Error al aplicar el descuento: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Registrar un pago que puede cubrir varios meses.
     *
     * Crea pagos separados por recibo para mantener correcto el cálculo
     * de paid_amount/pending_amount sin crear todavía payment_allocations.
     *
     * @return array
     */
    public function registerMultiMonthPayment(array $data)
    {
        try {
            DB::beginTransaction();

            $contract = $this->resolveContractForMultiMonthPayment($data);

            if (! $contract) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'No se encontró un contrato válido para registrar el pago multimes.',
                ];
            }

            if ($contract->status !== 'active') {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Solo se pueden registrar pagos multimes para contratos activos.',
                ];
            }

            $contract->loadMissing(['customer', 'plan', 'contractedServices.additionalService', 'invoices.payments', 'invoices.creditNotes']);

            $rawAmount = round((float) $data['amount'], 2);
            $rawReceived = isset($data['received_amount']) ? round((float) $data['received_amount'], 2) : 0;
            $saveCredit = ! empty($data['save_overpayment_as_credit']);
            $remainingAmount = ($saveCredit && $rawReceived > $rawAmount) ? $rawReceived : $rawAmount;
            $monthsRequested = max((int) ($data['months_to_pay'] ?? 1), 1);
            $monthlyAmount = $this->monthlyAmountForContract($contract);

            if ($monthlyAmount <= 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'El contrato no tiene un precio mensual válido.',
                ];
            }

            $paymentGroupId = (string) Str::uuid();
            $appliedInvoices = [];
            $payments = collect();
            $coveredMonths = 0;
            $totalDiscount = 0.0;
            $invoiceDiscounts = collect($data['invoice_discounts'] ?? [])
                ->mapWithKeys(fn ($amount, $invoiceId) => [(int) $invoiceId => round((float) $amount, 2)])
                ->filter(fn (float $amount) => $amount > 0);

            $pendingInvoices = $contract->invoices()
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->whereNotIn('status', ['cancelled', 'void'])
                ->with(['payments', 'creditNotes'])
                ->orderBy('due_date')
                ->orderBy('issue_date')
                ->orderBy('billing_period')
                ->orderBy('id')
                ->get();

            foreach ($pendingInvoices as $invoice) {
                $discountAmount = $invoiceDiscounts->get((int) $invoice->id, 0.0);

                if (($remainingAmount <= 0 && $discountAmount <= 0) || $coveredMonths >= $monthsRequested) {
                    break;
                }

                $pendingAmount = round((float) $invoice->pending_amount, 2);

                if ($discountAmount > $pendingAmount) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => "El descuento del recibo {$invoice->invoice_number} no puede superar su saldo pendiente.",
                    ];
                }

                if ($discountAmount > 0) {
                    $creditNote = $this->creditNoteRepository->create([
                        'invoice_id' => $invoice->id,
                        'credit_note_number' => $this->creditNoteRepository->generateCreditNoteNumber(),
                        'amount' => $discountAmount,
                        'issue_date' => $data['payment_date'],
                        'status' => 'active',
                        'reason' => $data['discount_reason'] ?: 'Descuento aplicado al registrar pago multimes',
                        'notes' => $data['notes'] ?? null,
                        'user_id' => $data['user_id'] ?? auth()->id(),
                    ]);
                    $this->creditNoteRepository->applyCreditNote($creditNote->id);
                    $totalDiscount = round($totalDiscount + $discountAmount, 2);
                    $invoice->refresh();
                }

                $amountToApply = min($remainingAmount, round((float) $invoice->pending_amount, 2));

                if ($amountToApply <= 0 && $discountAmount <= 0) {
                    continue;
                }

                if ($amountToApply > 0) {
                    $payment = $this->createAllocatedPayment($invoice, $amountToApply, $data, $paymentGroupId, $monthsRequested);
                    $payments->push($payment);
                }

                $this->refreshInvoiceStatus($invoice->refresh());

                $appliedInvoices[] = [
                    'invoice_id' => $invoice->id,
                    'contract_id' => $invoice->contract_id,
                    'invoice_number' => $invoice->invoice_number,
                    'billing_period' => $invoice->billing_period,
                    'amount' => $amountToApply,
                    'discount_amount' => $discountAmount,
                ];

                $remainingAmount = round($remainingAmount - $amountToApply, 2);
                $coveredMonths++;
            }

            while ($remainingAmount > 0 && $coveredMonths < $monthsRequested) {
                $futureInvoice = $this->createNextFutureInvoice($contract);
                $amountToApply = min($remainingAmount, round((float) $futureInvoice->pending_amount, 2));

                if ($amountToApply <= 0) {
                    break;
                }

                $payment = $this->createAllocatedPayment($futureInvoice, $amountToApply, $data, $paymentGroupId, $monthsRequested);
                $payments->push($payment);

                $this->refreshInvoiceStatus($futureInvoice->refresh());

                $appliedInvoices[] = [
                    'invoice_id' => $futureInvoice->id,
                    'invoice_number' => $futureInvoice->invoice_number,
                    'billing_period' => $futureInvoice->billing_period,
                    'amount' => $amountToApply,
                ];

                $remainingAmount = round($remainingAmount - $amountToApply, 2);
                $coveredMonths++;
            }

            if ($remainingAmount > 0) {
                if (! empty($data['save_overpayment_as_credit'])) {
                    $customer = $contract->customer;
                    if (! $customer) {
                        DB::rollBack();

                        return [
                            'success' => false,
                            'message' => 'No se pudo identificar al cliente para registrar el saldo a favor.',
                        ];
                    }

                    app(CustomerCreditService::class)->addCredit([
                        'customer_id' => $customer->id,
                        'contract_id' => $contract->id,
                        'source_type' => 'manual_overpayment',
                        'amount' => $remainingAmount,
                        'description' => 'Saldo a favor generado al registrar pago multimes.',
                        'created_by' => $data['user_id'] ?? auth()->id(),
                    ]);
                    $remainingAmount = 0;
                } else {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => 'El monto a aplicar excede los meses seleccionados. Aumente la cantidad de meses, reduzca el monto o marque la opción de registrar excedente como saldo a favor.',
                    ];
                }
            }

            if ($payments->isEmpty() && empty($appliedInvoices)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'No se pudo aplicar el pago a ningún recibo.',
                ];
            }

            $paymentDetails = [
                'payment_mode' => 'multi_month',
                'payment_group_id' => $paymentGroupId,
                'months_requested' => $monthsRequested,
                'applied_invoices' => $appliedInvoices,
                'discount_amount' => $totalDiscount,
                'discount_reason' => $data['discount_reason'] ?? null,
                'unapplied_amount' => $remainingAmount,
            ];

            foreach ($payments as $payment) {
                $details = array_merge($payment->payment_details ?? [], $paymentDetails);
                $payment->update(['payment_details' => $details]);
            }

            AuditLog::register(
                auth()->id(),
                'multi_month_payment_registered',
                'payments',
                "Pago multimes registrado para contrato #{$contract->id}. Meses aplicados: {$coveredMonths}",
                request()->ip(),
                null,
                $paymentDetails
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pago multimes registrado correctamente',
                'payment' => $payments->first(),
                'payments' => $payments,
                'invoice' => $contract->invoices()->find(data_get($appliedInvoices, '0.invoice_id')),
                'applied_invoices' => $appliedInvoices,
                'unapplied_amount' => $remainingAmount,
            ];
        } catch (\Exception $e) {
            if (DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }

            return [
                'success' => false,
                'message' => 'Error al registrar el pago multimes: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Registrar un pago Yape automático aplicado únicamente a facturas ya seleccionadas.
     *
     * A diferencia de registerMultiMonthPayment(), este método NO genera facturas futuras
     * ni decide por cantidad de meses. Solo paga los invoice_id explícitos que la
     * validación Yape ya marcó como seguros.
     *
     * @param  array<int>  $invoiceIds
     */
    public function registerYapeMultiInvoicePayment(array $data, array $invoiceIds): array
    {
        $data['payment_details'] = array_merge([
            'source' => 'automatic_yape_validation',
        ], $data['payment_details'] ?? []);

        return $this->registerMultiInvoicePayment($data, $invoiceIds);
    }

    /**
     * Apply one received payment to explicit pending invoices of one customer,
     * even when those invoices belong to different contracts/services.
     *
     * @param  array<int>  $invoiceIds
     */
    public function registerMultiInvoicePayment(array $data, array $invoiceIds): array
    {
        try {
            DB::beginTransaction();

            $invoiceIds = collect($invoiceIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if ($invoiceIds->isEmpty()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'No se recibieron recibos válidos para aplicar el pago.',
                ];
            }

            $invoices = Invoice::query()
                ->with(['contract.customer', 'payments', 'creditNotes'])
                ->whereIn('id', $invoiceIds)
                ->lockForUpdate()
                ->get()
                ->sortBy(fn (Invoice $invoice) => sprintf(
                    '%s|%s|%s|%010d',
                    optional($invoice->due_date)->format('Y-m-d') ?: '9999-12-31',
                    optional($invoice->issue_date)->format('Y-m-d') ?: '9999-12-31',
                    (string) ($invoice->billing_period ?? ''),
                    (int) $invoice->id
                ))
                ->values();

            if ($invoices->count() !== $invoiceIds->count()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'Uno o más recibos seleccionados no existen.',
                ];
            }

            $contractIds = $invoices->pluck('contract_id')->unique()->values();
            $customerIds = $invoices
                ->map(fn (Invoice $invoice) => $invoice->contract?->customer_id)
                ->filter()
                ->unique()
                ->values();

            if ($customerIds->count() !== 1) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'El pago solo puede aplicarse a recibos pertenecientes al mismo cliente.',
                ];
            }

            foreach ($invoices as $invoice) {
                if (! in_array($invoice->status, ['pending', 'partial', 'overdue'], true)) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => "El recibo {$invoice->invoice_number} ya no tiene deuda pendiente.",
                    ];
                }
            }

            $expectedAmount = round((float) ($data['amount'] ?? 0), 2);
            $selectedTotal = round((float) $invoices->sum(fn (Invoice $invoice) => round((float) $invoice->pending_amount, 2)), 2);
            $allowOverpaymentCredit = (bool) ($data['allow_overpayment_credit'] ?? false);

            if (! $allowOverpaymentCredit && abs($selectedTotal - $expectedAmount) >= 0.01) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'La suma de los recibos seleccionados no coincide exactamente con el monto recibido.',
                ];
            }

            if ($allowOverpaymentCredit && $expectedAmount + 0.009 < $selectedTotal) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'El monto recibido no cubre la deuda seleccionada desde el recibo más antiguo.',
                ];
            }

            $paymentGroupId = (string) Str::uuid();
            $monthsRequested = $invoices->count();
            $payments = collect();
            $appliedInvoices = [];
            $appliedAmount = 0.0;

            foreach ($invoices as $invoice) {
                $amountToApply = round((float) $invoice->pending_amount, 2);

                if ($amountToApply <= 0) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => "El recibo {$invoice->invoice_number} no tiene saldo pendiente aplicable.",
                    ];
                }

                $payment = $this->createAllocatedPayment($invoice, $amountToApply, $data, $paymentGroupId, $monthsRequested);
                $payments->push($payment);
                $appliedAmount = round($appliedAmount + $amountToApply, 2);

                $this->refreshInvoiceStatus($invoice->refresh());

                $appliedInvoices[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'billing_period' => $invoice->billing_period,
                    'due_date' => optional($invoice->due_date)->toDateString(),
                    'amount' => $amountToApply,
                    'pending_amount' => $amountToApply,
                ];
            }

            $creditAmount = $allowOverpaymentCredit
                ? round(max($expectedAmount - $appliedAmount, 0), 2)
                : 0.0;
            $paymentMode = $creditAmount > 0 ? 'overpayment_with_credit' : 'multi_month';

            $paymentDetails = [
                'payment_mode' => $paymentMode,
                'payment_group_id' => $paymentGroupId,
                'months_requested' => $monthsRequested,
                'applied_invoice_ids' => $invoices->pluck('id')->values()->all(),
                'applied_contract_ids' => $contractIds->all(),
                'customer_id' => (int) $customerIds->first(),
                'applied_invoices' => $appliedInvoices,
                'unapplied_amount' => $creditAmount,
                'original_yape_amount' => $expectedAmount,
                'applied_amount' => $appliedAmount,
                'credit_amount' => $creditAmount,
                'source' => data_get($data, 'payment_details.source', 'manual_multi_service_payment'),
            ];
            $creditMovement = null;

            if ($creditAmount > 0.009) {
                $firstInvoice = $invoices->first();
                $customer = $firstInvoice->contract?->customer;

                if (! $customer) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => 'No se pudo identificar el cliente para registrar el saldo a favor.',
                    ];
                }

                \Log::info('customer_credit_overpayment_detected', [
                    'customer_id' => $customer->id,
                    'contract_ids' => $contractIds->all(),
                    'yape_intent_id' => data_get($data, 'payment_details.yape_intent_id'),
                    'operation_code' => data_get($data, 'payment_details.operation_code', $data['reference'] ?? null),
                    'original_yape_amount' => $expectedAmount,
                    'applied_amount' => $appliedAmount,
                    'credit_amount' => $creditAmount,
                    'payment_group_id' => $paymentGroupId,
                    'applied_invoice_ids' => $invoices->pluck('id')->values()->all(),
                ]);

                $creditMovement = app(CustomerCreditService::class)->addCredit([
                    'customer_id' => $customer->id,
                    'contract_id' => $contractIds->count() === 1 ? (int) $contractIds->first() : null,
                    'source_type' => 'yape_payment_intent',
                    'source_id' => data_get($data, 'payment_details.yape_intent_id'),
                    'amount' => $creditAmount,
                    'description' => 'Saldo a favor generado por sobrepago Yape.',
                    'created_by' => $data['user_id'] ?? auth()->id(),
                    'metadata' => [
                        'yape_intent_id' => data_get($data, 'payment_details.yape_intent_id'),
                        'operation_code' => data_get($data, 'payment_details.operation_code', $data['reference'] ?? null),
                        'security_code' => data_get($data, 'payment_details.security_code'),
                        'evidence_id' => data_get($data, 'payment_details.client_evidence_id'),
                        'payment_group_id' => $paymentGroupId,
                        'applied_invoice_ids' => $invoices->pluck('id')->values()->all(),
                        'original_yape_amount' => $expectedAmount,
                        'applied_amount' => $appliedAmount,
                        'credit_amount' => $creditAmount,
                    ],
                ]);

                $paymentDetails['credit_movement_id'] = $creditMovement->id;
                $paymentDetails['credit_balance_after'] = (float) $creditMovement->balance_after;

                \Log::info('customer_credit_overpayment_created', [
                    'customer_id' => $customer->id,
                    'contract_ids' => $contractIds->all(),
                    'yape_intent_id' => data_get($data, 'payment_details.yape_intent_id'),
                    'operation_code' => data_get($data, 'payment_details.operation_code', $data['reference'] ?? null),
                    'original_yape_amount' => $expectedAmount,
                    'applied_amount' => $appliedAmount,
                    'credit_amount' => $creditAmount,
                    'credit_movement_id' => $creditMovement->id,
                    'balance_after' => (float) $creditMovement->balance_after,
                    'applied_invoice_ids' => $invoices->pluck('id')->values()->all(),
                ]);
            }

            foreach ($payments as $payment) {
                $details = array_merge($payment->payment_details ?? [], $paymentDetails);
                $payment->update(['payment_details' => $details]);
            }

            $isYape = ($data['payment_method'] ?? null) === 'yape';
            AuditLog::register(
                auth()->id(),
                $isYape ? 'yape_multi_invoice_payment_registered' : 'multi_service_payment_registered',
                'payments',
                ($isYape ? 'Pago Yape' : 'Pago').' registrado para cliente #'.$customerIds->first().'. Contratos: '.$contractIds->implode(', ').". Recibos aplicados: {$monthsRequested}",
                request()->ip(),
                null,
                $paymentDetails
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pago aplicado a varios recibos correctamente',
                'payment' => $payments->first(),
                'payments' => $payments,
                'applied_invoices' => $appliedInvoices,
                'unapplied_amount' => $creditAmount,
                'payment_group_id' => $paymentGroupId,
                'original_yape_amount' => $expectedAmount,
                'applied_amount' => $appliedAmount,
                'credit_amount' => $creditAmount,
                'credit_movement' => $creditMovement,
                'credit_movement_id' => $creditMovement?->id,
            ];
        } catch (\Exception $e) {
            if (DB::connection()->transactionLevel() > 0) {
                DB::rollBack();
            }

            return [
                'success' => false,
                'message' => 'Error al registrar el pago en varios recibos: '.$e->getMessage(),
            ];
        }
    }

    protected function resolveContractForMultiMonthPayment(array $data): ?Contract
    {
        if (! empty($data['contract_id'])) {
            return Contract::find($data['contract_id']);
        }

        if (! empty($data['invoice_id'])) {
            $invoice = $this->invoiceRepository->find($data['invoice_id']);

            return $invoice ? $invoice->contract : null;
        }

        return null;
    }

    protected function createAllocatedPayment(Invoice $invoice, float $amount, array $data, string $paymentGroupId, int $monthsRequested)
    {
        $extraDetails = $data['payment_details'] ?? [];

        return $this->paymentRepository->create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'payment_date' => $data['payment_date'],
            'payment_method' => $data['payment_method'],
            'status' => 'completed',
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'user_id' => $data['user_id'] ?? auth()->id(),
            'payment_details' => array_merge([
                'payment_mode' => 'multi_month',
                'payment_group_id' => $paymentGroupId,
                'months_requested' => $monthsRequested,
                'received_amount' => $data['received_amount'] ?? null,
                'change_amount' => $data['change_amount'] ?? 0,
                'registered_by_user_id' => $data['user_id'] ?? auth()->id(),
            ], $extraDetails),
        ]);
    }

    public function refreshInvoiceStatus(Invoice $invoice): void
    {
        $totalPaid = $invoice->payments()->where('status', 'completed')->sum('amount');
        $creditNoteAmount = $invoice->creditNotes()->where('status', 'applied')->sum('amount');

        if ($totalPaid + $creditNoteAmount >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid']);
        } elseif ($totalPaid + $creditNoteAmount > 0) {
            $invoice->update(['status' => 'partial']);
        } else {
            $invoice->update(['status' => 'pending']);
        }
    }

    public function createNextFutureInvoice(Contract $contract): Invoice
    {
        return $this->invoicePeriodGuard->createNextFuture($contract->id, function (Contract $contract, Carbon $issueDate) {
        $contract->loadMissing(['customer', 'plan', 'contractedServices.additionalService']);
        $billingPeriod = $issueDate->locale('es')->translatedFormat('F Y');

        $dueDays = config('billing.invoice.due_days', 15);
        $customer = $contract->customer;
        $subtotal = 0;
        $taxTotal = 0;

        $invoice = $this->invoiceRepository->create([
            'contract_id' => $contract->id,
            'invoice_number' => $this->invoiceRepository->generateInvoiceNumber(),
            'issue_date' => $issueDate->format('Y-m-d'),
            'due_date' => $issueDate->copy()->addDays($dueDays)->format('Y-m-d'),
            'status' => 'pending',
            'document_type' => 'invoice',
            'sent' => false,
            'billing_name' => $customer->full_name,
            'billing_address' => $customer->primary_address,
            'billing_document' => $customer->identity_document,
            'billing_email' => $customer->email,
            'generation_type' => 'advance_payment',
            'billing_period' => $billingPeriod,
            'amount' => 0,
            'taxes' => 0,
            'total_amount' => 0,
        ]);

        $hasCustomPrice = $contract->final_price !== null;

        if ($contract->plan) {
            $planAmount = $hasCustomPrice ? (float) $contract->final_price : (float) $contract->plan->price;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => "Plan {$contract->plan->name}",
                'quantity' => 1,
                'unit_price' => $planAmount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'discount' => 0,
                'amount' => $planAmount,
                'order' => 1,
                'item_type' => 'plan',
                'service_id' => $contract->plan->id,
                'period_start' => $issueDate->copy()->startOfMonth()->format('Y-m-d'),
                'period_end' => $issueDate->copy()->endOfMonth()->format('Y-m-d'),
            ]);

            $subtotal += $planAmount;
        }

        $order = 2;
        foreach ($contract->contractedServices as $service) {
            $serviceAmount = $hasCustomPrice ? 0.00 : (float) $service->price;
            $description = optional($service->additionalService)->name ?: 'Servicio adicional';
            if ($hasCustomPrice) {
                $description .= ' (Incluido)';
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $serviceAmount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'discount' => 0,
                'amount' => $serviceAmount,
                'order' => $order++,
                'item_type' => 'additional_service',
                'service_id' => $service->additional_service_id,
                'period_start' => $issueDate->copy()->startOfMonth()->format('Y-m-d'),
                'period_end' => $issueDate->copy()->endOfMonth()->format('Y-m-d'),
            ]);

            $subtotal += $serviceAmount;
        }

        $invoice->update([
            'amount' => $subtotal,
            'taxes' => $taxTotal,
            'total_amount' => $subtotal + $taxTotal,
        ]);

        return $invoice->refresh();
        });
    }

    protected function invoicePeriodStart(Invoice $invoice): ?Carbon
    {
        $periodDate = $this->parseBillingPeriod($invoice->billing_period);

        if ($periodDate) {
            return $periodDate;
        }

        return ! empty($invoice->issue_date)
            ? Carbon::parse($invoice->issue_date)->startOfMonth()
            : null;
    }

    public function canonicalInvoicePeriodKey(Invoice $invoice): ?string
    {
        return $this->invoicePeriodGuard->canonicalKeyForInvoice($invoice);
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

    protected function monthlyAmountForContract(Contract $contract): float
    {
        $contract->loadMissing(['plan', 'contractedServices']);

        if ($contract->final_price !== null) {
            return round((float) $contract->final_price, 2);
        }

        $amount = (float) (optional($contract->plan)->price ?: 0);

        foreach ($contract->contractedServices as $service) {
            $amount += (float) $service->price;
        }

        return round($amount, 2);
    }

    public function exactAdvancePaymentMonths(Contract $contract, float $amount, int $maximumMonths = 24): ?int
    {
        if ($contract->status !== 'active' || $amount <= 0) {
            return null;
        }

        $monthlyAmount = $this->monthlyAmountForContract($contract);

        if ($monthlyAmount <= 0) {
            return null;
        }

        $months = (int) round($amount / $monthlyAmount);

        if ($months < 1 || $months > $maximumMonths) {
            return null;
        }

        return abs($amount - ($monthlyAmount * $months)) < 0.01
            ? $months
            : null;
    }

    /**
     * Anular un pago
     *
     * @param  int  $id
     * @param  string  $reason
     * @return array
     */
    public function voidPayment($id, $reason)
    {
        try {
            DB::beginTransaction();

            $payment = $this->paymentRepository->find($id);

            // Verificar que el pago no esté ya anulado
            if ($payment->status === 'refunded') {
                return [
                    'success' => false,
                    'message' => 'El pago ya está anulado',
                ];
            }

            // Guardar datos anteriores para auditoría
            $oldData = $payment->toArray();

            // Anular el pago
            $payment->update([
                'status' => 'refunded',
                'notes' => ($payment->notes ? $payment->notes."\n" : '').
                           '['.now().'] Pago anulado. Motivo: '.$reason,
            ]);

            // Actualizar el estado de la factura
            $invoice = $payment->invoice;
            $totalPaid = $invoice->payments()->where('status', 'completed')->sum('amount');
            $creditNoteAmount = $invoice->creditNotes()->where('status', 'applied')->sum('amount');

            if ($totalPaid + $creditNoteAmount <= 0) {
                $invoice->update(['status' => 'pending']);
            } elseif ($totalPaid + $creditNoteAmount < $invoice->total_amount) {
                $invoice->update(['status' => 'partial']);
            }

            // Registrar en log de auditoría
            AuditLog::register(
                auth()->id(),
                'payment_voided',
                'payments',
                "Pago anulado para recibo: {$invoice->invoice_number}. Motivo: {$reason}",
                request()->ip(),
                $oldData,
                $payment->toArray()
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Pago anulado correctamente',
                'payment' => $payment,
            ];
        } catch (\Exception $e) {
            DB::rollback();

            return [
                'success' => false,
                'message' => 'Error al anular el pago: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Generar reporte de pagos por período
     *
     * @param  string  $startDate
     * @param  string  $endDate
     * @param  array  $filters
     * @return array
     */
    public function getPaymentReport($startDate, $endDate, $filters = [])
    {
        try {
            $query = $this->paymentRepository->query();

            // Aplicar filtro de fechas
            $query->whereBetween('payment_date', [$startDate, $endDate]);

            // Filtro por método de pago
            if (isset($filters['payment_method']) && $filters['payment_method']) {
                $query->where('payment_method', $filters['payment_method']);
            }

            // Filtro por estado
            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }

            // Filtro por cliente
            if (isset($filters['customer_id']) && $filters['customer_id']) {
                $query->whereHas('invoice.contract', function ($q) use ($filters) {
                    $q->where('customer_id', $filters['customer_id']);
                });
            }

            $payments = $query->get();

            $totalAmount = $payments->where('status', 'completed')->sum('amount');

            // Agrupar por método de pago
            $byMethod = $payments->where('status', 'completed')
                ->groupBy('payment_method')
                ->map(function ($items, $key) {
                    return [
                        'method' => $key,
                        'count' => $items->count(),
                        'amount' => $items->sum('amount'),
                    ];
                })->values();

            // Agrupar por día
            $byDate = $payments->where('status', 'completed')
                ->groupBy(function ($item) {
                    return Carbon::parse($item->payment_date)->format('Y-m-d');
                })
                ->map(function ($items, $key) {
                    return [
                        'date' => $key,
                        'count' => $items->count(),
                        'amount' => $items->sum('amount'),
                    ];
                })->values();

            return [
                'success' => true,
                'total_payments' => $payments->where('status', 'completed')->count(),
                'total_amount' => $totalAmount,
                'by_method' => $byMethod,
                'by_date' => $byDate,
                'payments' => $payments,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al generar el reporte: '.$e->getMessage(),
            ];
        }
    }
}
