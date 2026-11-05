<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

/**
 * Covers Modules\Customer\Http\Controllers\CustomerAPIController::dashboard()
 * and ::invoices(), routes "customer.portal.dashboard" and
 * "customer.portal.invoices" (auth:sanctum).
 *
 * Customer::invoices() is a hasManyThrough(Invoice, Contract) relation, and
 * both "contracts" and "invoices" tables have their own "status"/"id"
 * columns, so any unqualified reference to those columns in a whereIn/orderBy
 * on that relation is ambiguous.
 */
class CustomerPortalDashboardAmbiguousColumnTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 950000;

    public function test_dashboard_counts_pending_invoices_without_an_ambiguous_column_error(): void
    {
        [$customerUser] = $this->makeCustomerWithPendingInvoice();

        $response = $this->actingAs($customerUser, 'sanctum')
            ->getJson(route('customer.portal.dashboard'));

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'pending_invoices' => 1]);
    }

    public function test_invoices_listing_does_not_error_on_ambiguous_id(): void
    {
        [$customerUser] = $this->makeCustomerWithPendingInvoice();

        $response = $this->actingAs($customerUser, 'sanctum')
            ->getJson(route('customer.portal.invoices'));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * @return array{0: User, 1: Customer}
     */
    private function makeCustomerWithPendingInvoice(): array
    {
        $customerUser = User::create([
            'username' => 'dashcustomer_' . $this->sequence++,
            'email' => 'dashcustomer_' . $this->sequence . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_type' => 'individual',
            'first_name' => 'Ana',
            'last_name' => 'Torres ' . $this->sequence,
            'identity_document' => 'DOC' . $this->sequence++,
            'active' => true,
        ]);

        $service = Service::create([
            'name' => 'Internet dashboard test',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan dashboard test',
            'price' => 100,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);

        $contract = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'final_price' => 100,
        ]);

        Invoice::create([
            'contract_id' => $contract->id,
            'invoice_number' => 'INV-DASH' . $this->sequence++,
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

        return [$customerUser, $customer];
    }
}
