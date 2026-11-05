<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
 * Regression test for the N+1 in PaymentController::index().
 *
 * The payments list view renders `$payment->invoice->invoice_number` and
 * `$payment->invoice->contract->customer->full_name` for every row, but the
 * query built in the controller did not eager load `invoice.contract.customer`.
 */
class PaymentsIndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private User $admin;

    private int $sequence = 950000;

    private ?string $firstCustomerFullName = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'username' => 'payments-index-admin',
            'email' => 'payments-index-admin@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole('admin');

        $service = Service::create([
            'name' => 'Internet payments index query',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $this->plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan payments index query',
            'price' => 100,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_payments_index_query_count_does_not_grow_with_payment_count(): void
    {
        $this->seedCompletedPayments(5);
        $responseFive = null;
        $queriesForFive = $this->countQueriesFor(function () use (&$responseFive) {
            $responseFive = $this->actingAs($this->admin)->get(route('billing.payments.index', ['per_page' => 100]));

            return $responseFive;
        });

        $this->seedCompletedPayments(15); // total now 20
        $queriesForTwenty = $this->countQueriesFor(fn () => $this->actingAs($this->admin)->get(
            route('billing.payments.index', ['per_page' => 100])
        ));

        $this->assertLessThanOrEqual(
            $queriesForFive + 2,
            $queriesForTwenty,
            "Query count grew with payment count: {$queriesForFive} queries for 5 payments vs {$queriesForTwenty} for 20."
        );

        // Behavior must stay unchanged: the customer name for the first seeded
        // payment must still render on the list.
        $responseFive->assertSee($this->firstCustomerFullName, false);
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

    private function seedCompletedPayments(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->sequence++;

            $customer = Customer::create([
                'customer_type' => 'individual',
                'first_name' => 'Cliente Pay',
                'last_name' => (string) $this->sequence,
                'identity_document' => 'DOC'.$this->sequence,
                'active' => true,
            ]);

            if ($this->firstCustomerFullName === null) {
                $this->firstCustomerFullName = $customer->full_name;
            }

            $contract = Contract::create([
                'customer_id' => $customer->id,
                'plan_id' => $this->plan->id,
                'start_date' => '2026-01-01',
                'status' => 'active',
                'final_price' => 100,
            ]);

            $invoice = Invoice::create([
                'contract_id' => $contract->id,
                'invoice_number' => 'INV-P'.$this->sequence,
                'amount' => 100,
                'taxes' => 0,
                'total_amount' => 100,
                'issue_date' => '2026-01-01',
                'due_date' => '2026-01-28',
                'status' => 'paid',
                'document_type' => 'invoice',
                'generation_type' => 'manual',
                'billing_period' => 'January 2026',
                'sent' => false,
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => 100,
                'payment_date' => '2026-01-10',
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
        }
    }
}
