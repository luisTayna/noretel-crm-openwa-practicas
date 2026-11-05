<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Collection;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Entities\YapePaymentIntent;

class PaymentReceiptService
{
    public function build(Payment $payment, ?YapePaymentIntent $intent = null): array
    {
        $payment->loadMissing([
            'invoice.items',
            'invoice.payments',
            'invoice.creditNotes',
            'invoice.contract.customer.addresses',
            'invoice.contract.plan',
            'invoice.contract.contractedServices.additionalService',
            'yapePaymentIntent.clientEvidence',
            'yapePaymentIntent.receiverEvent',
            'user',
        ]);

        $intent = $intent ?: $payment->yapePaymentIntent;
        $clientEvidence = $intent?->clientEvidence;
        $receiverEvent = $intent?->receiverEvent;

        $appliedInvoicePayload = $this->appliedInvoicesPayload($payment, $intent);
        $payments = $this->receiptPayments($payment);
        $firstPayment = $payments->first() ?: $payment;
        $invoice = $firstPayment->invoice ?: $payment->invoice;
        $contract = $invoice?->contract;
        $customer = $contract?->customer;
        $contracts = $payments
            ->map(fn (Payment $appliedPayment) => $appliedPayment->invoice?->contract)
            ->filter()
            ->unique(fn ($appliedContract) => $appliedContract->id)
            ->values();
        $lines = $payments
            ->flatMap(fn (Payment $appliedPayment) => $this->linesForPayment($appliedPayment))
            ->values();

        if ($payments->count() === 1 && $appliedInvoicePayload->count() > 1) {
            $lines = $this->linesFromAppliedInvoicePayload($appliedInvoicePayload);
        }

        $appliedToDebt = round((float) $payments->sum(fn (Payment $appliedPayment) => (float) $appliedPayment->amount), 2);
        $creditAmount = round((float) data_get($firstPayment->payment_details, 'credit_amount', 0), 2);
        $totalReceived = round((float) data_get($firstPayment->payment_details, 'original_yape_amount', $appliedToDebt + $creditAmount), 2);
        $total = $totalReceived;
        $linesTotal = round((float) $lines->sum(fn (array $line) => (float) ($line['amount'] ?? 0)), 2);
        $address = $this->receiptAddress($invoice);

        if (! $this->amountsMatch($appliedToDebt, $linesTotal)) {
            \Log::warning('yape_receipt_total_mismatch', [
                'payment_id' => $firstPayment->id,
                'payment_group_id' => data_get($firstPayment->payment_details, 'payment_group_id'),
                'payments_total' => $appliedToDebt,
                'receipt_items_total' => $linesTotal,
                'receipt_items' => $lines->all(),
            ]);
        }

        $receivedAmount = data_get($firstPayment->payment_details, 'received_amount');
        $changeAmount = data_get($firstPayment->payment_details, 'change_amount');
        $balanceSummary = $this->balanceSummary($payments);

        $paidAt = $clientEvidence?->occurred_at
            ?: ($receiverEvent?->occurred_at
                ?: ($intent?->created_at
                    ?: ($firstPayment->payment_date ? \Carbon\Carbon::parse($firstPayment->payment_date) : ($firstPayment->created_at ?: now()))));

        return [
            'payment' => $firstPayment,
            'payments' => $payments,
            'invoice' => $invoice,
            'intent' => $intent,
            'customer' => $customer,
            'contract' => $contract,
            'lines' => $lines,
            'receiptItems' => $lines,
            'total' => $total,
            'totalPaid' => $total,
            'totalReceived' => $totalReceived,
            'appliedToDebt' => $appliedToDebt,
            'creditAmount' => $creditAmount,
            'creditMovementId' => data_get($firstPayment->payment_details, 'credit_movement_id'),
            'creditBalanceAfter' => data_get($firstPayment->payment_details, 'credit_balance_after'),
            'applied_invoices_payload' => $appliedInvoicePayload,
            'receipt_number' => $this->receiptNumber($firstPayment, $intent),
            'paid_at' => $paidAt,
            'issued_at' => now(),
            'payment_method' => $this->paymentMethodLabel($firstPayment),
            'operation_code' => $firstPayment->reference ?: $intent?->operation_code ?: data_get($firstPayment->payment_details, 'yape_operation_code'),
            'security_code' => $intent?->security_code ?: data_get($firstPayment->payment_details, 'yape_security_code'),
            'customer_name' => $invoice?->billing_name ?: $customer?->full_name ?: '-',
            'customer_document' => $invoice?->billing_document ?: $customer?->identity_document ?: '-',
            'contract_label' => $contracts->isNotEmpty()
                ? $contracts->map(fn ($appliedContract) => '#'.$appliedContract->id)->implode(', ')
                : '-',
            'service_label' => $contracts->isNotEmpty()
                ? $contracts->map(fn ($appliedContract) => optional($appliedContract->plan)->name ?: 'Servicio de internet')->unique()->implode(' + ')
                : 'Servicio de internet',
            'address' => $address ?: '-',
            'payment_group_id' => data_get($firstPayment->payment_details, 'payment_group_id'),
            'received_amount' => $receivedAmount ? (float) $receivedAmount : null,
            'change_amount' => $changeAmount ? (float) $changeAmount : null,
            'invoiceTotal' => $balanceSummary['invoice_total'],
            'remainingBalance' => $balanceSummary['remaining_balance'],
            'isPartialPayment' => $balanceSummary['is_partial_payment'],
            'paymentStatusLabel' => $balanceSummary['payment_status_label'],
            'notes' => $firstPayment->notes ?: data_get($firstPayment->payment_details, 'notes'),
            'custom_note' => $this->filterCustomerFacingNote($firstPayment),
        ];
    }

    protected function filterCustomerFacingNote(Payment $payment): ?string
    {
        $explicitCustomNote = data_get($payment->payment_details, 'custom_note');
        if (! empty($explicitCustomNote) && is_string($explicitCustomNote)) {
            return trim($explicitCustomNote);
        }

        $note = trim((string) ($payment->notes ?: data_get($payment->payment_details, 'notes')));
        if ($note === '') {
            return null;
        }

        $systemKeywords = [
            'Intent Yape',
            'captura por DNI',
            'Decisión:',
            'oldest_invoices',
            'Pago adelantado generado',
            'Aplicación de saldo',
            'Recibo anulado',
            'validación Yape',
            'Procesado automáticamente',
        ];

        foreach ($systemKeywords as $keyword) {
            if (mb_stripos($note, $keyword) !== false) {
                return null;
            }
        }

        return $note;
    }

    protected function receiptPayments(Payment $payment): Collection
    {
        $groupId = data_get($payment->payment_details, 'payment_group_id');

        if ($groupId) {
            return Payment::query()
                ->with([
                    'invoice.items',
                    'invoice.payments',
                    'invoice.creditNotes',
                    'invoice.contract.customer.addresses',
                    'invoice.contract.plan',
                    'invoice.contract.contractedServices.additionalService',
                    'user',
                ])
                ->where('status', 'completed')
                ->where('payment_details->payment_group_id', $groupId)
                ->get()
                ->sortBy(fn (Payment $appliedPayment) => $this->invoiceSortKey($appliedPayment->invoice))
                ->values();
        }

        return collect([$payment]);
    }

    protected function balanceSummary(Collection $payments): array
    {
        $invoices = $payments
            ->map(fn (Payment $payment) => $payment->invoice)
            ->filter()
            ->unique(fn (Invoice $invoice) => $invoice->getKey() ?: spl_object_id($invoice))
            ->values();

        $invoiceTotal = round((float) $invoices->sum(fn (Invoice $invoice) => (float) $invoice->total_amount), 2);
        $remainingBalance = round((float) $invoices->sum(function (Invoice $invoice) {
            $paidAmount = $invoice->relationLoaded('payments')
                ? $invoice->payments->where('status', 'completed')->sum('amount')
                : $invoice->payments()->where('status', 'completed')->sum('amount');

            $creditNoteAmount = $invoice->relationLoaded('creditNotes')
                ? $invoice->creditNotes->where('status', 'applied')->sum('amount')
                : $invoice->creditNotes()->where('status', 'applied')->sum('amount');

            return max((float) $invoice->total_amount - (float) $paidAmount - (float) $creditNoteAmount, 0);
        }), 2);

        $appliedAmount = round((float) $payments->sum(fn (Payment $payment) => (float) $payment->amount), 2);
        $isPartialPayment = $appliedAmount > 0 && $remainingBalance > 0.009;

        return [
            'invoice_total' => $invoiceTotal,
            'remaining_balance' => $remainingBalance,
            'is_partial_payment' => $isPartialPayment,
            'payment_status_label' => $isPartialPayment ? 'PAGO PARCIAL' : 'PAGADO',
        ];
    }

    protected function appliedInvoicesPayload(Payment $payment, ?YapePaymentIntent $intent = null): Collection
    {
        $fromPayment = data_get($payment->payment_details, 'applied_invoices');

        if (is_array($fromPayment) && ! empty($fromPayment)) {
            return collect($fromPayment)->values();
        }

        $fromIntent = data_get($intent?->source_payload, 'applied_invoices');

        if (is_array($fromIntent) && ! empty($fromIntent)) {
            return collect($fromIntent)->values();
        }

        return collect();
    }

    protected function linesFromAppliedInvoicePayload(Collection $appliedInvoices): Collection
    {
        $invoiceIds = $appliedInvoices
            ->map(fn ($item) => (int) data_get($item, 'invoice_id'))
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $invoices = Invoice::query()
            ->with(['items', 'contract.plan'])
            ->whereIn('id', $invoiceIds)
            ->get()
            ->keyBy('id');

        return $appliedInvoices
            ->map(function ($item) use ($invoices) {
                $invoiceId = (int) data_get($item, 'invoice_id');
                $invoice = $invoices->get($invoiceId);
                $period = data_get($item, 'billing_period') ?: $invoice?->billing_period;

                $cleanPeriod = $this->cleanBillingPeriod($period);

                return [
                    'description' => $invoice
                        ? $this->invoiceDescription($invoice, $cleanPeriod)
                        : 'Pago de recibo '.(data_get($item, 'invoice_number') ?: $invoiceId),
                    'invoice_id' => $invoiceId ?: null,
                    'invoice_number' => data_get($item, 'invoice_number') ?: $invoice?->invoice_number,
                    'period' => $cleanPeriod,
                    'plan' => optional($invoice?->contract?->plan)->name,
                    'quantity' => 1,
                    'amount' => round((float) data_get($item, 'amount', data_get($item, 'pending_amount', 0)), 2),
                ];
            })
            ->values();
    }

    protected function linesForPayment(Payment $payment): Collection
    {
        $invoice = $payment->invoice;
        $appliedAmount = round((float) $payment->amount, 2);

        if (! $invoice) {
            return collect([[
                'description' => 'Pago aplicado',
                'invoice_number' => null,
                'period' => null,
                'quantity' => 1,
                'amount' => $appliedAmount,
            ]]);
        }

        $items = $invoice->items
            ->sortBy('order')
            ->values();
        $itemsTotal = round((float) $items->sum(fn ($item) => (float) $item->amount), 2);
        $invoiceTotal = round((float) $invoice->total_amount, 2);

        $cleanPeriod = $this->cleanBillingPeriod($invoice->billing_period);

        if ($items->isNotEmpty() && $this->amountsMatch($itemsTotal, $invoiceTotal) && $this->amountsMatch($appliedAmount, $invoiceTotal)) {
            return $items->map(function ($item) use ($invoice, $cleanPeriod) {
                $isPlan = ($item->item_type === 'plan');

                return [
                    'description' => $this->lineDescription((string) $item->description, $invoice, $cleanPeriod, (string) $item->item_type),
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'period' => $cleanPeriod,
                    'plan' => $isPlan ? optional($invoice->contract?->plan)->name : null,
                    'quantity' => (int) ($item->quantity ?: 1),
                    'amount' => round((float) $item->amount, 2),
                ];
            })->values();
        }

        return collect([[
            'description' => $this->invoiceDescription($invoice, $cleanPeriod),
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'period' => $cleanPeriod,
            'plan' => optional($invoice->contract?->plan)->name,
            'quantity' => 1,
            'amount' => $appliedAmount,
        ]]);
    }

    protected function lineDescription(string $description, Invoice $invoice, string $cleanPeriod, string $itemType = 'plan'): string
    {
        $description = trim($description);
        $planName = optional($invoice->contract?->plan)->name;

        $isPlan = ($itemType === 'plan');
        $containsPlan = $planName && str_contains(mb_strtolower($description), mb_strtolower($planName));
        $isHistoric = str_contains(mb_strtolower($description), 'histórica') || preg_match('/\d{2}\/\d{2}\/\d{4}/', $description);

        if ($isPlan || $containsPlan || $isHistoric) {
            return 'Mensualidad';
        }

        return $description ?: 'Servicio de Internet';
    }

    protected function invoiceDescription(Invoice $invoice, string $cleanPeriod): string
    {
        return 'Mensualidad'.($cleanPeriod ? ' - '.$cleanPeriod : '');
    }

    public function cleanBillingPeriod(?string $billingPeriod): string
    {
        $billingPeriod = trim((string) $billingPeriod);
        if ($billingPeriod === '') {
            return '';
        }

        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $billingPeriod, $matches, PREG_SET_ORDER)) {
            try {
                $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
                $day = (int) $targetMatch[1];
                $month = (int) $targetMatch[2];
                $year = (int) $targetMatch[3];
                $date = \Carbon\Carbon::create($year, $month, $day);
                $monthName = ucfirst($date->locale('es')->translatedFormat('F'));

                return "{$monthName} {$year}";
            } catch (\Throwable) {
                return $billingPeriod;
            }
        }

        // Traducción de meses en inglés para datos históricos guardados erróneamente
        $monthsTranslations = [
            'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril',
            'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto',
            'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre',
        ];

        foreach ($monthsTranslations as $eng => $esp) {
            if (stripos($billingPeriod, $eng) !== false) {
                $billingPeriod = str_ireplace($eng, $esp, $billingPeriod);
            }
        }

        return ucfirst($billingPeriod);
    }

    protected function receiptAddress(?Invoice $invoice): ?string
    {
        if (! $invoice) {
            return null;
        }

        if (trim((string) $invoice->billing_address) !== '') {
            return $invoice->billing_address;
        }

        $addresses = $invoice->contract?->customer?->addresses;

        return $addresses?->sortByDesc('is_primary')->first()?->full_address;
    }

    protected function receiptNumber(Payment $payment, ?YapePaymentIntent $intent = null): string
    {
        return $intent?->intent_number ?: 'PAY-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
    }

    protected function paymentMethodLabel(Payment $payment): string
    {
        if ($payment->payment_method === 'yape') {
            return 'Yape';
        }

        return $payment->payment_method_name;
    }

    protected function invoiceSortKey(?Invoice $invoice): string
    {
        return sprintf(
            '%s|%s|%010d',
            optional($invoice?->due_date)->format('Y-m-d') ?: '9999-12-31',
            optional($invoice?->issue_date)->format('Y-m-d') ?: '9999-12-31',
            (int) ($invoice?->id ?? 0)
        );
    }

    protected function amountsMatch(float $expected, float $actual): bool
    {
        return abs(round($expected, 2) - round($actual, 2)) < 0.01;
    }
}
