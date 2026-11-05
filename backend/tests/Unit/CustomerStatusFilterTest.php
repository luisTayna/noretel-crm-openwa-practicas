<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Entities\Customer;
use Modules\Contract\Entities\Contract;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Modules\Customer\Repositories\CustomerRepository;

class CustomerStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $repository;
    protected $service;
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(CustomerRepository::class);

        $this->service = Service::create([
            'name' => 'Internet Fiber',
            'description' => 'Fiber internet service',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $this->plan = Plan::create([
            'service_id' => $this->service->id,
            'name' => '100 Mbps Plan',
            'price' => 50.00,
            'upload_speed' => 50,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_filter_by_customer_active(): void
    {
        $activeCustomer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Active',
            'last_name' => 'User',
            'identity_document' => '12345678',
            'active' => true,
        ]);

        $inactiveCustomer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Inactive',
            'last_name' => 'User',
            'identity_document' => '87654321',
            'active' => false,
        ]);

        $resultsActive = $this->repository->getWithFilters(['customer_active' => '1']);
        $this->assertContains($activeCustomer->id, $resultsActive->pluck('id')->toArray());
        $this->assertNotContains($inactiveCustomer->id, $resultsActive->pluck('id')->toArray());

        $resultsInactive = $this->repository->getWithFilters(['customer_active' => '0']);
        $this->assertNotContains($activeCustomer->id, $resultsInactive->pluck('id')->toArray());
        $this->assertContains($inactiveCustomer->id, $resultsInactive->pluck('id')->toArray());
    }

    public function test_filter_by_contract_status_active(): void
    {
        $customerA = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'identity_document' => '11111111',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerA->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'active',
            'final_price' => 50.00,
        ]);

        $customerB = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'identity_document' => '22222222',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerB->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'suspended',
            'final_price' => 50.00,
        ]);

        $results = $this->repository->getWithFilters(['contract_status' => 'active']);
        $customerIds = $results->pluck('id')->toArray();

        $this->assertContains($customerA->id, $customerIds);
        $this->assertNotContains($customerB->id, $customerIds);
    }

    public function test_filter_by_contract_status_suspended(): void
    {
        $customerA = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'identity_document' => '11111111',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerA->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'active',
            'final_price' => 50.00,
        ]);

        $customerB = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'identity_document' => '22222222',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerB->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'suspended',
            'final_price' => 50.00,
        ]);

        $results = $this->repository->getWithFilters(['contract_status' => 'suspended']);
        $customerIds = $results->pluck('id')->toArray();

        $this->assertNotContains($customerA->id, $customerIds);
        $this->assertContains($customerB->id, $customerIds);
    }

    public function test_filter_by_contract_status_expired(): void
    {
        $customerA = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Expired',
            'last_name' => 'User',
            'identity_document' => '33333333',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerA->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'expired',
            'final_price' => 50.00,
        ]);

        $customerB = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Active',
            'last_name' => 'User',
            'identity_document' => '44444444',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerB->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'active',
            'final_price' => 50.00,
        ]);

        $results = $this->repository->getWithFilters(['contract_status' => 'expired']);
        $customerIds = $results->pluck('id')->toArray();

        $this->assertContains($customerA->id, $customerIds);
        $this->assertNotContains($customerB->id, $customerIds);
    }

    public function test_filter_by_contract_status_cancelled(): void
    {
        $customerA = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Cancelled',
            'last_name' => 'User',
            'identity_document' => '55555555',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerA->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'cancelled',
            'final_price' => 50.00,
        ]);

        $customerB = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Suspended',
            'last_name' => 'User',
            'identity_document' => '66666666',
            'active' => true,
        ]);
        Contract::create([
            'customer_id' => $customerB->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'suspended',
            'final_price' => 50.00,
        ]);

        $results = $this->repository->getWithFilters(['contract_status' => 'cancelled']);
        $customerIds = $results->pluck('id')->toArray();

        $this->assertContains($customerA->id, $customerIds);
        $this->assertNotContains($customerB->id, $customerIds);
    }
}
