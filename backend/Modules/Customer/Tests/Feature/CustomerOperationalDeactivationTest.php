<?php

namespace Modules\Customer\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Services\CustomerService;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

class CustomerOperationalDeactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-05 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deactivation_suspends_active_contracts_and_voids_only_the_cutoff_month_and_later(): void
    {
        [$customer, $contract] = $this->customerWithActiveContract();
        $june = $this->invoice($contract, '2026-06-01', 'pending', 'June 2026');
        $july = $this->invoice($contract, '2026-07-01', 'pending', 'July 2026');

        $result = app(CustomerService::class)->changeStatus($customer->id, false, $this->operator()->id, '127.0.0.1');

        $this->assertTrue($result['success'], $result['message'] ?? 'Unknown failure');
        $this->assertFalse($customer->fresh()->active);
        $this->assertNotNull($customer->fresh()->deactivated_at);
        $this->assertSame('suspended', $contract->fresh()->status);
        $this->assertSame('customer_deactivated', $contract->fresh()->suspension_reason);
        $this->assertSame('pending', $june->fresh()->status);
        $this->assertSame('void', $july->fresh()->status);
    }

    public function test_reactivation_restores_only_contracts_suspended_by_customer_deactivation(): void
    {
        [$customer, $deactivatedContract] = $this->customerWithActiveContract();
        $otherContract = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $deactivatedContract->plan_id,
            'start_date' => now(),
            'status' => 'suspended',
            'suspension_reason' => 'non_payment',
            'final_price' => 50.00,
        ]);

        $service = app(CustomerService::class);
        $operator = $this->operator();
        $service->changeStatus($customer->id, false, $operator->id, '127.0.0.1');
        $result = $service->changeStatus($customer->id, true, $operator->id, '127.0.0.1');

        $this->assertTrue($result['success'], $result['message'] ?? 'Unknown failure');
        $this->assertTrue($customer->fresh()->active);
        $this->assertNull($customer->fresh()->deactivated_at);
        $this->assertSame('active', $deactivatedContract->fresh()->status);
        $this->assertNull($deactivatedContract->fresh()->suspension_reason);
        $this->assertSame('suspended', $otherContract->fresh()->status);
        $this->assertSame('non_payment', $otherContract->fresh()->suspension_reason);
    }

    private function customerWithActiveContract(): array
    {
        $service = Service::create([
            'name' => 'Internet',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);
        $plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan 50',
            'price' => 50.00,
            'upload_speed' => 50,
            'download_speed' => 50,
            'active' => true,
        ]);
        $customer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Ana',
            'last_name' => 'Prueba',
            'phone' => '999999999',
        ]);
        $contract = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'start_date' => now(),
            'status' => 'active',
            'final_price' => 50.00,
        ]);

        return [$customer, $contract];
    }

    private function invoice(Contract $contract, string $issueDate, string $status, string $period): Invoice
    {
        return Invoice::create([
            'contract_id' => $contract->id,
            'invoice_number' => 'TEST-' . str_replace('-', '', $issueDate) . '-' . uniqid(),
            'amount' => 50,
            'taxes' => 0,
            'total_amount' => 50,
            'issue_date' => $issueDate,
            'due_date' => Carbon::parse($issueDate)->endOfMonth()->toDateString(),
            'status' => $status,
            'billing_period' => $period,
        ]);
    }

    private function operator(): User
    {
        return User::firstOrCreate(
            ['username' => 'operator'],
            ['email' => 'operator@test.com', 'password' => bcrypt('password')]
        );
    }
}
