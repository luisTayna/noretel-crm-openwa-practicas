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
 * Regression test for the N+1 in OperationsController::onboarding(), driven
 * by CustomerOperationFlowService::billingSummary().
 *
 * That method sums `pending_amount` over every pending invoice of the
 * customer's current contract. The invoices were eager loaded, but
 * getPaidAmountAttribute()/getPendingAmountAttribute() ignore the loaded
 * relation and re-query payments/credit notes per invoice unless the query
 * also applies Invoice::withAmountTotals().
 */
class OperationsOnboardingQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private User $admin;

    private int $sequence = 970000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'onboarding-query-admin',
            'email' => 'onboarding-query-admin@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');

        $service = Service::create([
            'name' => 'Internet onboarding query',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $this->plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan onboarding query',
            'price' => 100,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_onboarding_index_query_count_does_not_grow_with_customer_count(): void
    {
        $this->seedCustomersWithPendingInvoices(5);
        $responseFive = null;
        $queriesForFive = $this->countQueriesFor(function () use (&$responseFive) {
            $responseFive = $this->actingAs($this->admin)->get(route('operations.onboarding'));

            return $responseFive;
        });

        $this->seedCustomersWithPendingInvoices(15); // total now 20
        $queriesForTwenty = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(
            route('operations.onboarding')
        ));

        $this->assertLessThanOrEqual(
            $queriesForFive + 3,
            $queriesForTwenty,
            "Query count grew with customer count: {$queriesForFive} queries for 5 customers vs {$queriesForTwenty} for 20."
        );

        // Behavior must stay unchanged: the pending amount label for a known
        // customer (100 - 30 paid - 10 credit note = 60) still renders.
        $responseFive->assertSee('S/ 60.00', false);
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

    private function seedCustomersWithPendingInvoices(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->sequence++;

            $customer = Customer::create([
                'customer_type' => 'individual',
                'first_name' => 'Cliente Onboarding',
                'last_name' => (string) $this->sequence,
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
                'invoice_number' => 'INV-O'.$this->sequence,
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
                'credit_note_number' => 'CN-O'.$this->sequence,
                'amount' => 10,
                'issue_date' => '2026-01-15',
                'status' => 'applied',
                'reason' => 'Test credit note',
                'user_id' => $this->admin->id,
            ]);
        }
    }
}
