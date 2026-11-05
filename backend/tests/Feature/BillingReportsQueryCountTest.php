<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\CreditNote;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression test for the N+1 in BillingController::reports().
 *
 * All three report types render `contract.customer` (directly or through
 * `invoice.contract.customer`) for every row, and the "aging" report also
 * renders `pending_amount` for every overdue invoice. None of these were
 * eager loaded, so each row triggered its own queries.
 */
class BillingReportsQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private User $admin;

    private int $sequence = 900000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'reports-query-admin',
            'email' => 'reports-query-admin@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');

        $service = Service::create([
            'name' => 'Internet reports query',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $this->plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan reports query',
            'price' => 100,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_payments_report_query_count_does_not_grow_with_row_count(): void
    {
        $this->seedOverdueInvoicesWithPayments(5);
        $queriesForFive = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(
            route('billing.reports', ['report_type' => 'payments', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])
        ));

        $this->seedOverdueInvoicesWithPayments(15); // total now 20
        $queriesForTwenty = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(
            route('billing.reports', ['report_type' => 'payments', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])
        ));

        $this->assertLessThanOrEqual(
            $queriesForFive + 2,
            $queriesForTwenty,
            "Query count grew with row count: {$queriesForFive} queries for 5 rows vs {$queriesForTwenty} for 20."
        );
    }

    public function test_invoices_report_query_count_does_not_grow_with_row_count(): void
    {
        $this->seedOverdueInvoicesWithPayments(5);
        $queriesForFive = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(
            route('billing.reports', ['report_type' => 'invoices', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])
        ));

        $this->seedOverdueInvoicesWithPayments(15); // total now 20
        $queriesForTwenty = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(
            route('billing.reports', ['report_type' => 'invoices', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])
        ));

        $this->assertLessThanOrEqual(
            $queriesForFive + 2,
            $queriesForTwenty,
            "Query count grew with row count: {$queriesForFive} queries for 5 rows vs {$queriesForTwenty} for 20."
        );
    }

    public function test_aging_report_query_count_does_not_grow_with_invoice_count(): void
    {
        $this->seedOverdueInvoicesWithPayments(5);
        $queriesForFive = $this->countAgingReportQueries(fn () => $this->actingAs($this->admin)->get(
            route('billing.reports', ['report_type' => 'aging'])
        ));

        $this->seedOverdueInvoicesWithPayments(15); // total now 20
        $queriesForTwenty = $this->countAgingReportQueries(fn () => $this->actingAs($this->admin)->get(
            route('billing.reports', ['report_type' => 'aging'])
        ));

        $this->assertLessThanOrEqual(
            $queriesForFive + 2,
            $queriesForTwenty,
            "Query count grew with invoice count: {$queriesForFive} queries for 5 invoices vs {$queriesForTwenty} for 20."
        );

        // Behavior must stay unchanged: pending_amount for a known invoice
        // (100 - 30 paid - 10 credit note = 60) must compute the same value
        // whether or not the accessor reads preloaded aggregates.
        $invoice = Invoice::withAmountTotals()->latest('id')->first();
        $this->assertEqualsWithDelta(60.0, (float) $invoice->pending_amount, 0.001);
    }

    private function countAgingReportQueries(\Closure $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $callback();
        $response->assertStatus(200);
        $response->assertSee('const agingData', false);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function countQueriesFor(\Closure $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $callback();
        $response->assertStatus(200);

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function seedOverdueInvoicesWithPayments(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $customer = Customer::create([
                'customer_type' => 'individual',
                'first_name' => 'Cliente',
                'last_name' => 'Reports '.$this->sequence++,
                'identity_document' => 'DOC'.$this->sequence,
                'active' => true,
            ]);

            $contract = Contract::create([
                'customer_id' => $customer->id,
                'plan_id' => $this->plan->id,
                'start_date' => '2026-01-01',
                'status' => 'active',
                'final_price' => 100,
            ]);

            $invoice = Invoice::create([
                'contract_id' => $contract->id,
                'invoice_number' => 'INV-R'.$this->sequence++,
                'amount' => 100,
                'taxes' => 0,
                'total_amount' => 100,
                'issue_date' => '2026-01-01',
                'due_date' => '2026-01-05',
                'status' => 'pending',
                'document_type' => 'invoice',
                'generation_type' => 'manual',
                'billing_period' => 'January 2026',
                'sent' => false,
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => 30,
                'payment_date' => '2026-01-10',
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);

            CreditNote::create([
                'invoice_id' => $invoice->id,
                'credit_note_number' => 'CN-R'.$this->sequence++,
                'amount' => 10,
                'issue_date' => '2026-01-15',
                'status' => 'applied',
                'reason' => 'Test credit note',
                'user_id' => $this->admin->id,
            ]);
        }
    }
}
