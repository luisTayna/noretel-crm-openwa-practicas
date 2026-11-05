<?php

namespace Modules\Customer\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\InvoiceItem;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Billing\Services\InvoicePeriodGuardService;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\Installation;
use Modules\Core\Entities\AuditLog;
use Modules\Customer\Entities\Address;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;

class HistoricalCustomerRegistrationService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly InvoicePeriodGuardService $invoicePeriodGuard
    ) {
    }

    public function register(array $data, ?int $userId, ?string $ip): array
    {
        return DB::transaction(function () use ($data, $userId, $ip) {
            $installedAt = Carbon::parse($data['installed_at'])->startOfDay();
            $plan = Plan::findOrFail($data['plan_id']);
            $monthlyPrice = round((float) $data['monthly_price'], 2);

            $customer = $this->resolveCustomer($data, $installedAt);

            $contract = Contract::create([
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'start_date' => $installedAt,
                'status' => 'active',
                'final_price' => $monthlyPrice,
                'notes' => trim(($data['notes'] ?? '') . "\nRegistro histórico: cliente ya instalado."),
            ]);

            $installation = Installation::create([
                'contract_id' => $contract->id,
                'scheduled_date' => $installedAt,
                'completed_date' => $installedAt,
                'status' => 'completed',
                'notes' => 'Instalación histórica registrada durante migración de clientes reales.',
            ]);

            $invoices = $this->createHistoricalDebtInvoices($customer, $contract, $plan, $monthlyPrice, $data);

            $this->refreshCustomerMetrics($customer, $installedAt);

            AuditLog::register(
                $userId,
                'historical_customer_registered',
                'customers',
                "Cliente histórico registrado: {$customer->full_name}",
                $ip,
                null,
                [
                    'customer_id' => $customer->id,
                    'contract_id' => $contract->id,
                    'installation_id' => $installation->id,
                    'generated_invoices' => count($invoices),
                ]
            );

            return [
                'customer' => $customer->fresh(['contracts', 'addresses']),
                'contract' => $contract,
                'installation' => $installation,
                'invoices' => $invoices,
            ];
        });
    }

    private function resolveCustomer(array $data, Carbon $installedAt): Customer
    {
        if (!empty($data['customer_id'])) {
            $customer = Customer::findOrFail($data['customer_id']);
            $customer->update([
                'active' => true,
                'customer_status' => 'active',
                'first_purchase_at' => $customer->first_purchase_at ?: $installedAt,
            ]);

            return $customer;
        }

        $customer = Customer::create([
            'customer_type' => $data['customer_type'] ?? 'individual',
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'identity_document' => $data['identity_document'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'segment' => $data['segment'] ?? 'residential',
            'registration_date' => $installedAt,
            'active' => true,
            'customer_status' => 'active',
            'first_purchase_at' => $installedAt,
            'months_as_customer' => $installedAt->diffInMonths(now()),
            'acquisition_channel' => 'historical_migration',
        ]);

        Address::create([
            'customer_id' => $customer->id,
            'address_type' => 'installation',
            'street' => $data['street'],
            'city' => $data['city'],
            'state' => $data['state'],
            'coordinates' => $data['coordinates'] ?? null,
            'is_primary' => true,
        ]);

        return $customer;
    }

    private function createHistoricalDebtInvoices(
        Customer $customer,
        Contract $contract,
        Plan $plan,
        float $monthlyPrice,
        array $data
    ): array {
        if (empty($data['debt_start_date'])) {
            return [];
        }

        $startDate = Carbon::parse($data['debt_start_date'])->startOfDay();
        $endMonth = !empty($data['debt_end_month'])
            ? Carbon::createFromFormat('Y-m', $data['debt_end_month'])->startOfMonth()
            : now()->startOfMonth();
        $finalDueDate = $this->monthlyDueDate($endMonth);

        if ($finalDueDate->lt($startDate)) {
            throw new \RuntimeException('El rango de deuda no genera ningún recibo. Revisa la fecha inicial y el mes final.');
        }

        $invoices = [];
        $currentDueDate = $this->firstDueDateOnOrAfter($startDate);
        $previousDueDate = null;
        $isFirstPeriod = true;

        while ($currentDueDate->lte($finalDueDate)) {
            $periodStart = $isFirstPeriod
                ? $startDate->copy()
                : $previousDueDate->copy();
            $periodEnd = $currentDueDate->copy();
            $billingPeriod = $this->billingPeriodLabel($periodStart, $periodEnd, $isFirstPeriod);
            $amount = $this->historicalPeriodAmount($monthlyPrice, $startDate, $periodEnd, $isFirstPeriod);

            $issueDate = $periodStart->copy();
            $dueDate = $periodEnd->copy();
            $periodCheck = $this->invoicePeriodGuard->checkInsideTransaction(
                $contract->id,
                $billingPeriod,
                $issueDate,
                true
            );

            if ($periodCheck['skipped']) {
                $previousDueDate = $currentDueDate->copy();
                $currentDueDate = $this->monthlyDueDate($currentDueDate->copy()->addMonthNoOverflow()->startOfMonth());
                $isFirstPeriod = false;
                continue;
            }

            $status = $dueDate->lt(now()->startOfDay()) ? 'overdue' : 'pending';
            $description = $isFirstPeriod && $amount < $monthlyPrice
                ? "Prorrateo histórico {$periodStart->format('d/m/Y')} al {$periodEnd->format('d/m/Y')} - {$plan->name}"
                : "Mensualidad histórica {$periodStart->format('d/m/Y')} al {$periodEnd->format('d/m/Y')} - {$plan->name}";

            $invoice = Invoice::create([
                'contract_id' => $contract->id,
                'invoice_number' => $this->invoiceRepository->generateInvoiceNumber(),
                'amount' => $amount,
                'taxes' => 0,
                'total_amount' => $amount,
                'issue_date' => $issueDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => $status,
                'document_type' => 'invoice',
                'notes' => 'Recibo histórico generado por migración de deuda anterior.',
                'sent' => false,
                'billing_name' => $customer->full_name,
                'billing_address' => $this->primaryAddress($customer),
                'billing_document' => $customer->identity_document,
                'billing_email' => $customer->email,
                'generation_type' => 'manual',
                'billing_period' => $billingPeriod,
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'discount' => 0,
                'amount' => $amount,
                'order' => 1,
                'item_type' => 'plan',
                'service_id' => $plan->id,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
            ]);

            $invoices[] = $invoice;
            $previousDueDate = $currentDueDate->copy();
            $currentDueDate = $this->monthlyDueDate($currentDueDate->copy()->addMonthNoOverflow()->startOfMonth());
            $isFirstPeriod = false;
        }

        return $invoices;
    }

    private function monthlyDueDate(Carbon $periodDate): Carbon
    {
        $dueDay = (int) config('billing.invoice.fixed_due_day', 30);
        $lastDay = (int) $periodDate->copy()->endOfMonth()->format('d');

        return $periodDate->copy()
            ->day(min(max($dueDay, 1), $lastDay))
            ->startOfDay();
    }

    private function firstDueDateOnOrAfter(Carbon $date): Carbon
    {
        $dueDate = $this->monthlyDueDate($date->copy()->startOfMonth());

        if ($dueDate->lt($date)) {
            return $this->monthlyDueDate($date->copy()->addMonthNoOverflow()->startOfMonth());
        }

        return $dueDate;
    }

    private function billingPeriodLabel(Carbon $periodStart, Carbon $periodEnd, bool $isFirstPeriod): string
    {
        $label = $periodStart->format('d/m/Y') . ' - ' . $periodEnd->format('d/m/Y');

        return $isFirstPeriod ? "{$label} (prorrateado)" : $label;
    }

    private function historicalPeriodAmount(float $monthlyPrice, Carbon $startDate, Carbon $periodEnd, bool $isFirstPeriod): float
    {
        if (!$isFirstPeriod) {
            return round($monthlyPrice, 2);
        }

        $monthStart = $periodEnd->copy()->startOfMonth();

        if ($startDate->lte($monthStart)) {
            return round($monthlyPrice, 2);
        }

        $billingDays = max(1, $monthStart->diffInDays($periodEnd) + 1);
        $chargedDays = max(1, $startDate->diffInDays($periodEnd) + 1);

        return round(($monthlyPrice / $billingDays) * $chargedDays, 2);
    }

    private function primaryAddress(Customer $customer): ?string
    {
        $address = $customer->addresses()->where('is_primary', true)->first()
            ?: $customer->addresses()->first();

        return $address?->full_address;
    }

    private function refreshCustomerMetrics(Customer $customer, Carbon $installedAt): void
    {
        $customer->update([
            'active' => true,
            'customer_status' => 'active',
            'first_purchase_at' => $customer->first_purchase_at ?: $installedAt,
            'contract_count' => $customer->contracts()->count(),
            'months_as_customer' => $installedAt->diffInMonths(now()),
        ]);
    }
}
