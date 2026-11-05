<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Collection;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;

class InvoiceGlobalAuditService
{
    public function __construct(private readonly InvoicePeriodGuardService $periodGuard)
    {
    }

    public function audit(): array
    {
        $invoices = Invoice::query()
            ->with(['contract.customer'])
            ->orderBy('contract_id')
            ->orderBy('id')
            ->get();

        $activeInvoices = $invoices->whereIn('status', InvoicePeriodGuardService::BLOCKING_STATUSES);

        $unrecognized = $invoices
            ->filter(fn (Invoice $invoice) => $this->periodGuard->canonicalKey($invoice->billing_period) === null)
            ->values();

        $duplicateContractMonths = $this->duplicateGroups(
            $activeInvoices,
            fn (Invoice $invoice) => $invoice->contract_id.'|'.$this->periodKey($invoice)
        );

        $crossContractCustomerMonths = $this->duplicateGroups(
            $activeInvoices->filter(fn (Invoice $invoice) => $invoice->contract?->customer_id),
            fn (Invoice $invoice) => $invoice->contract->customer_id.'|'.$this->periodKey($invoice)
        )->filter(fn (Collection $group) => $group->pluck('contract_id')->unique()->count() > 1)->values();

        $automaticOnCancelled = $invoices
            ->filter(fn (Invoice $invoice) => $invoice->generation_type === 'automatic'
                && $invoice->contract?->status === 'cancelled')
            ->values();

        $duplicateActiveContracts = Contract::query()
            ->with(['customer', 'plan'])
            ->where('status', 'active')
            ->get()
            ->groupBy(fn (Contract $contract) => (string) $contract->customer_id)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();

        return compact(
            'duplicateContractMonths',
            'duplicateActiveContracts',
            'automaticOnCancelled',
            'crossContractCustomerMonths',
            'unrecognized'
        );
    }

    private function duplicateGroups(Collection $invoices, callable $key): Collection
    {
        return $invoices
            ->filter(fn (Invoice $invoice) => $this->periodKey($invoice) !== null)
            ->groupBy($key)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->values();
    }

    private function periodKey(Invoice $invoice): ?string
    {
        return $this->periodGuard->canonicalKeyForInvoice($invoice);
    }
}
