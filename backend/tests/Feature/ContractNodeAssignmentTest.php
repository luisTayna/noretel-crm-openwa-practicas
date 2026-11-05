<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractNodeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private Plan $plan;
    private int $nodeId;
    private int $otherNodeId;

    protected function setUp(): void
    {
        parent::setUp();

        $service = new Service([
            'name' => 'Internet',
            'description' => 'Servicio de prueba',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);
        $service->save();

        $this->plan = new Plan([
            'service_id' => $service->id,
            'name' => 'Plan 65',
            'price' => 65,
            'upload_speed' => 50,
            'download_speed' => 100,
            'active' => true,
        ]);
        $this->plan->save();

        $this->customer = new Customer([
            'customer_type' => 'individual',
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'identity_document' => 'DOC-NODE-1',
            'active' => true,
        ]);
        $this->customer->save();

        $this->nodeId = $this->createNode('NODE-A');
        $this->otherNodeId = $this->createNode('NODE-B');
    }

    public function test_storing_a_contract_with_node_id_persists_it(): void
    {
        $response = $this->actingAs($this->adminUser())->post(route('contract.contracts.store'), $this->contractPayload([
            'node_id' => $this->nodeId,
        ]));

        $response->assertRedirect();
        $contract = Contract::where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertSame($this->nodeId, $contract->node_id);
    }

    public function test_updating_a_contract_node_id_persists_it(): void
    {
        $contract = $this->createContract(null);

        $response = $this->actingAs($this->adminUser())->put(route('contract.contracts.update', $contract->id), $this->contractPayload([
            'node_id' => $this->nodeId,
        ]));

        $response->assertRedirect();
        $this->assertSame($this->nodeId, $contract->fresh()->node_id);
    }

    public function test_update_without_node_id_in_payload_does_not_wipe_existing_node(): void
    {
        $contract = $this->createContract($this->nodeId);

        $payload = $this->contractPayload([]);
        unset($payload['node_id']);

        $response = $this->actingAs($this->adminUser())->put(route('contract.contracts.update', $contract->id), $payload);

        $response->assertRedirect();
        $this->assertSame($this->nodeId, $contract->fresh()->node_id);
    }

    public function test_update_can_explicitly_clear_node_id(): void
    {
        $contract = $this->createContract($this->nodeId);

        $response = $this->actingAs($this->adminUser())->put(route('contract.contracts.update', $contract->id), $this->contractPayload([
            'node_id' => '',
        ]));

        $response->assertRedirect();
        $this->assertNull($contract->fresh()->node_id);
    }

    public function test_invalid_node_id_is_rejected_on_store(): void
    {
        $response = $this->actingAs($this->adminUser())->post(route('contract.contracts.store'), $this->contractPayload([
            'node_id' => 999999,
        ]));

        $response->assertSessionHasErrors('node_id');
        $this->assertDatabaseMissing('contracts', ['customer_id' => $this->customer->id]);
    }

    public function test_invalid_node_id_is_rejected_on_update(): void
    {
        $contract = $this->createContract($this->nodeId);

        $response = $this->actingAs($this->adminUser())->put(route('contract.contracts.update', $contract->id), $this->contractPayload([
            'node_id' => 999999,
        ]));

        $response->assertSessionHasErrors('node_id');
        $this->assertSame($this->nodeId, $contract->fresh()->node_id);
    }

    private function contractPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'start_date' => '2026-01-01',
            'final_price' => 65,
        ], $overrides);
    }

    private function createContract(?int $nodeId): Contract
    {
        $contract = new Contract([
            'customer_id' => $this->customer->id,
            'plan_id' => $this->plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'final_price' => 65,
        ]);
        $contract->node_id = $nodeId;
        $contract->save();

        return $contract;
    }

    private function createNode(string $code): int
    {
        return (int) DB::table('nodes')->insertGetId([
            'name' => $code,
            'code' => $code,
            'type' => 'access',
            'location' => 'Sede de prueba',
            'zone' => 'Zona 1',
            'capacity' => 100,
            'current_load' => 0,
            'status' => 'active',
            'is_operational' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'node_admin_' . uniqid(),
            'email' => 'node_admin_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
