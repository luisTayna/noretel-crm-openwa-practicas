<?php

namespace Modules\Customer\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customer\Entities\Customer;
use Modules\Contract\Entities\Contract;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Modules\Customer\Repositories\CustomerRepository;

class CustomerIncompleteFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $repository;
    protected $service;
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(CustomerRepository::class);

        // Create base service and plan for contracts
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

    public function test_filter_by_no_plan_returns_customers_without_active_contracts(): void
    {
        // Customer A: with active contract
        $customerA = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'identity_document' => '12345678',
            'phone' => '999888777',
        ]);
        Contract::create([
            'customer_id' => $customerA->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'active',
            'final_price' => 50.00,
        ]);

        // Customer B: with inactive/suspended contract
        $customerB = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'identity_document' => '87654321',
            'phone' => '999888666',
        ]);
        Contract::create([
            'customer_id' => $customerB->id,
            'plan_id' => $this->plan->id,
            'start_date' => now(),
            'status' => 'suspended',
            'final_price' => 50.00,
        ]);

        // Customer C: with no contracts at all
        $customerC = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'identity_document' => '11223344',
            'phone' => '999888555',
        ]);

        // Apply no_plan filter
        $results = $this->repository->getWithFilters(['incomplete' => 'no_plan']);

        $customerIds = $results->pluck('id')->toArray();

        $this->assertNotContains($customerA->id, $customerIds);
        $this->assertContains($customerB->id, $customerIds);
        $this->assertContains($customerC->id, $customerIds);
    }

    public function test_filter_by_no_dni_returns_customers_with_null_or_empty_identity_document(): void
    {
        // Customer D: valid DNI
        $customerD = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Alice',
            'last_name' => 'Green',
            'identity_document' => '12345678',
            'phone' => '999888444',
        ]);

        // Customer E: null DNI
        $customerE = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'identity_document' => null,
            'phone' => '999888333',
        ]);

        // Customer F: empty string DNI
        $customerF = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'David',
            'last_name' => 'White',
            'identity_document' => '',
            'phone' => '999888222',
        ]);

        // Apply no_dni filter
        $results = $this->repository->getWithFilters(['incomplete' => 'no_dni']);

        $customerIds = $results->pluck('id')->toArray();

        $this->assertNotContains($customerD->id, $customerIds);
        $this->assertContains($customerE->id, $customerIds);
        $this->assertContains($customerF->id, $customerIds);
    }

    public function test_filter_by_no_phone_returns_customers_with_null_or_empty_phone(): void
    {
        // Customer G: valid phone
        $customerG = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Frank',
            'last_name' => 'Miller',
            'identity_document' => '11112222',
            'phone' => '999888111',
        ]);

        // Customer H: null phone
        $customerH = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Grace',
            'last_name' => 'Davis',
            'identity_document' => '33334444',
            'phone' => null,
        ]);

        // Customer I: empty string phone
        $customerI = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Henry',
            'last_name' => 'Wilson',
            'identity_document' => '55556666',
            'phone' => '',
        ]);

        // Apply no_phone filter
        $results = $this->repository->getWithFilters(['incomplete' => 'no_phone']);

        $customerIds = $results->pluck('id')->toArray();

        $this->assertNotContains($customerG->id, $customerIds);
        $this->assertContains($customerH->id, $customerIds);
        $this->assertContains($customerI->id, $customerIds);
    }

    public function test_controller_passes_incomplete_filter(): void
    {
        // Verify whitelisting and route parameter
        \Spatie\Permission\Models\Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $user = \Modules\Core\Entities\User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        $customerWithoutPhone = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'NoPhone',
            'last_name' => 'Test',
            'identity_document' => '99999999',
            'phone' => null,
        ]);

        $customerWithPhone = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'WithPhone',
            'last_name' => 'Test',
            'identity_document' => '88888888',
            'phone' => '999999999',
        ]);

        $response = $this->actingAs($user)->get(route('customer.customers.index', ['incomplete' => 'no_phone']));

        $response->assertStatus(200);
        $response->assertViewHas('customers');
        
        $returnedCustomers = $response->viewData('customers')->pluck('id')->toArray();
        $this->assertContains($customerWithoutPhone->id, $returnedCustomers);
        $this->assertNotContains($customerWithPhone->id, $returnedCustomers);
    }
}
