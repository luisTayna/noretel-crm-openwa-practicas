<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Entities\CreditNote;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

/**
 * Verifies that Invoice::getPaidAmountAttribute() / getPendingAmountAttribute()
 * return the exact same values whether the invoice was loaded with the
 * withAmountTotals() scope (preloaded aggregates) or without it (fresh
 * per-invoice queries). The scope only changes how many queries run,
 * never the resulting amounts.
 */
class InvoiceAmountAccessorEquivalenceTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private int $sequence = 700000;

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'username' => 'amount-equivalence-admin',
            'email' => 'amount-equivalence@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $service = Service::create([
            'name' => 'Internet amount equivalence',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $this->plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan amount equivalence',
            'price' => 100,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_paid_and_pending_amount_match_with_and_without_preloaded_totals_for_mixed_payments(): void
    {
        $invoice = $this->invoiceWithTotal(200);

        $this->payment($invoice, 50, 'completed');
        $this->payment($invoice, 30, 'completed');
        $this->payment($invoice, 999, 'pending');
        $this->payment($invoice, 999, 'failed');
        $this->creditNote($invoice, 20, 'applied');
        $this->creditNote($invoice, 999, 'draft');

        $plain = Invoice::find($invoice->id);
        $preloaded = Invoice::withAmountTotals()->find($invoice->id);

        $this->assertSame(80.0, (float) $plain->paid_amount);
        $this->assertSame((float) $plain->paid_amount, (float) $preloaded->paid_amount);

        $this->assertSame(100.0, (float) $plain->pending_amount);
        $this->assertSame((float) $plain->pending_amount, (float) $preloaded->pending_amount);
    }

    public function test_paid_and_pending_amount_match_when_invoice_has_no_payments_or_credit_notes(): void
    {
        $invoice = $this->invoiceWithTotal(150);

        $plain = Invoice::find($invoice->id);
        $preloaded = Invoice::withAmountTotals()->find($invoice->id);

        $this->assertSame(0.0, (float) $plain->paid_amount);
        $this->assertSame((float) $plain->paid_amount, (float) $preloaded->paid_amount);

        $this->assertSame(150.0, (float) $plain->pending_amount);
        $this->assertSame((float) $plain->pending_amount, (float) $preloaded->pending_amount);
    }

    public function test_paid_and_pending_amount_match_when_credit_notes_fully_cover_balance(): void
    {
        $invoice = $this->invoiceWithTotal(90);

        $this->payment($invoice, 40, 'completed');
        $this->creditNote($invoice, 50, 'applied');

        $plain = Invoice::find($invoice->id);
        $preloaded = Invoice::withAmountTotals()->find($invoice->id);

        $this->assertSame(40.0, (float) $plain->paid_amount);
        $this->assertSame((float) $plain->paid_amount, (float) $preloaded->paid_amount);

        $this->assertSame(0.0, (float) $plain->pending_amount);
        $this->assertSame((float) $plain->pending_amount, (float) $preloaded->pending_amount);
    }

    private function invoiceWithTotal(float $total): Invoice
    {
        $customer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Cliente',
            'last_name' => 'Equivalence '.$this->sequence++,
            'identity_document' => 'DOC'.$this->sequence,
            'active' => true,
        ]);

        $contract = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $this->plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'final_price' => $total,
        ]);

        return Invoice::create([
            'contract_id' => $contract->id,
            'invoice_number' => 'INV-EQ'.$this->sequence++,
            'amount' => $total,
            'taxes' => 0,
            'total_amount' => $total,
            'issue_date' => '2026-01-01',
            'due_date' => '2026-01-28',
            'status' => 'pending',
            'document_type' => 'invoice',
            'generation_type' => 'manual',
            'billing_period' => 'January 2026',
            'sent' => false,
        ]);
    }

    private function payment(Invoice $invoice, float $amount, string $status): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'payment_date' => '2026-01-10',
            'payment_method' => 'cash',
            'status' => $status,
        ]);
    }

    private function creditNote(Invoice $invoice, float $amount, string $status): CreditNote
    {
        $user = User::first();

        return CreditNote::create([
            'invoice_id' => $invoice->id,
            'credit_note_number' => 'CN'.$this->sequence++,
            'amount' => $amount,
            'issue_date' => '2026-01-15',
            'status' => $status,
            'reason' => 'Test credit note',
            'user_id' => $user->id,
        ]);
    }
}
