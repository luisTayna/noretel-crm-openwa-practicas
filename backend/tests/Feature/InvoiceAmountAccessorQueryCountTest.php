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
 * Regression test for the N+1 caused by Invoice::getPaidAmountAttribute()
 * and getPendingAmountAttribute() running a fresh SQL query per invoice.
 *
 * Both hot list screens below render paid_amount/pending_amount for every
 * invoice in the list:
 *  - billing.collections (InvoiceController@collections)
 *  - billing.customer   (BillingController@customerBillingSummary)
 *
 * The query count for rendering the list must not grow proportionally with
 * the number of invoices once the accessors read preloaded aggregates.
 */
class InvoiceAmountAccessorQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private User $admin;

    private int $sequence = 800000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'amount-query-admin',
            'email' => 'amount-query-admin@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');

        $service = Service::create([
            'name' => 'Internet amount query',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $this->plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan amount query',
            'price' => 100,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_collections_endpoint_query_count_does_not_grow_with_invoice_count(): void
    {
        $customer = $this->customer();

        $this->seedPendingInvoices($customer, 5);
        $queriesForFive = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(route('billing.collections')));

        $this->seedPendingInvoices($customer, 15); // total now 20
        $queriesForTwenty = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(route('billing.collections')));

        $this->assertLessThanOrEqual(
            $queriesForFive + 2,
            $queriesForTwenty,
            "Query count grew with invoice count: {$queriesForFive} queries for 5 invoices vs {$queriesForTwenty} for 20."
        );
    }

    public function test_customer_summary_endpoint_query_count_does_not_grow_with_invoice_count(): void
    {
        $customer = $this->customer();

        $this->seedPendingInvoices($customer, 5);
        $queriesForFive = $this->countQueriesFor(
            fn () => $this->actingAs($this->admin)->get(route('billing.customer', $customer->id))
        );

        $this->seedPendingInvoices($customer, 15); // total now 20
        $queriesForTwenty = $this->countQueriesFor(
            fn () => $this->actingAs($this->admin)->get(route('billing.customer', $customer->id))
        );

        $this->assertLessThanOrEqual(
            $queriesForFive + 2,
            $queriesForTwenty,
            "Query count grew with invoice count: {$queriesForFive} queries for 5 invoices vs {$queriesForTwenty} for 20."
        );
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

    private function seedPendingInvoices(Customer $customer, int $count): void
    {
        $contract = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $this->plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'final_price' => 100,
        ]);

        for ($i = 0; $i < $count; $i++) {
            $invoice = Invoice::create([
                'contract_id' => $contract->id,
                'invoice_number' => 'INV-Q'.$this->sequence++,
                'amount' => 100,
                'taxes' => 0,
                'total_amount' => 100,
                'issue_date' => '2026-01-01',
                'due_date' => '2026-01-28',
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
                'credit_note_number' => 'CN-Q'.$this->sequence++,
                'amount' => 10,
                'issue_date' => '2026-01-15',
                'status' => 'applied',
                'reason' => 'Test credit note',
                'user_id' => $this->admin->id,
            ]);
        }
    }

    private function customer(): Customer
    {
        return Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Cliente',
            'last_name' => 'Query '.$this->sequence++,
            'identity_document' => 'DOC'.$this->sequence,
            'active' => true,
        ]);
    }
}
