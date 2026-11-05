<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Services\BillingPeriodRepairService;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Tests\TestCase;

class BillingPeriodRepairServiceTest extends TestCase
{
    public function test_paid_advance_invoice_and_unpaid_automatic_duplicate_is_safe(): void
    {
        $contract = new Contract(['id' => 275]);
        $contract->id = 275;
        $contract->setRelation('customer', new Customer(['first_name' => 'Carlos']));

        $paidInvoice = $this->invoice(1001, 'July 2026', 'paid', 'advance_payment', $contract);
        $paidInvoice->setRelation('payments', new Collection([
            new Payment(['amount' => 60, 'status' => 'completed']),
        ]));

        $duplicate = $this->invoice(1209, 'julio 2026', 'pending', 'automatic', $contract);

        $group = app(BillingPeriodRepairService::class)->classifyGroup(collect([$paidInvoice, $duplicate]));

        $this->assertTrue($group['safe']);
        $this->assertSame([1209], $group['duplicate_ids']);
        $this->assertSame('2026-07', $group['period_key']);
    }

    public function test_duplicate_with_a_payment_is_not_automatically_repaired(): void
    {
        $contract = new Contract(['id' => 275]);
        $contract->id = 275;
        $contract->setRelation('customer', new Customer(['first_name' => 'Carlos']));

        $paidInvoice = $this->invoice(1001, 'julio 2026', 'paid', 'advance_payment', $contract);
        $paidInvoice->setRelation('payments', new Collection([
            new Payment(['amount' => 60, 'status' => 'completed']),
        ]));

        $ambiguous = $this->invoice(1209, 'July 2026', 'partial', 'automatic', $contract);
        $ambiguous->setRelation('payments', new Collection([
            new Payment(['amount' => 10, 'status' => 'completed']),
        ]));

        $group = app(BillingPeriodRepairService::class)->classifyGroup(collect([$paidInvoice, $ambiguous]));

        $this->assertFalse($group['safe']);
        $this->assertSame([], $group['duplicate_ids']);
    }

    private function invoice(
        int $id,
        string $period,
        string $status,
        string $generationType,
        Contract $contract
    ): Invoice {
        $invoice = new Invoice([
            'contract_id' => 275,
            'invoice_number' => 'INV-'.$id,
            'billing_period' => $period,
            'issue_date' => '2026-07-15',
            'total_amount' => 60,
            'status' => $status,
            'generation_type' => $generationType,
        ]);
        $invoice->id = $id;
        $invoice->setRelation('contract', $contract);
        $invoice->setRelation('payments', new Collection());
        $invoice->setRelation('creditNotes', new Collection());

        return $invoice;
    }
}
