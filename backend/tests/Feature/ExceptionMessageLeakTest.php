<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Services\CustomerCreditService;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Modules\Network\Entities\Router;
use Modules\Services\Repositories\ServiceRepository;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Security-hardening regression tests: raw exception messages must never
 * reach the HTTP response, only the application log.
 */
class ExceptionMessageLeakTest extends TestCase
{
    use RefreshDatabase;

    private const LEAK_MARKER = 'boom: SQLSTATE[23000] leaked internal detail xyz789';

    public function test_router_store_does_not_leak_exception_message_on_redirect(): void
    {
        Log::spy();

        Router::creating(function () {
            throw new \RuntimeException(self::LEAK_MARKER);
        });

        $response = $this->actingAs($this->adminUser())->post(route('network.routers.store'), [
            'name' => 'Router Troncal Centro',
            'brand' => 'MikroTik',
            'ip_address' => '192.168.60.1',
            'zone' => 'Centro',
            'max_clients' => 50,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringNotContainsString(self::LEAK_MARKER, $errorMessage);
        $this->assertStringContainsString('No se pudo crear el router', $errorMessage);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_add_credit_does_not_leak_exception_message_on_redirect_with_errors(): void
    {
        Log::spy();

        $customer = Customer::create([
            'customer_type' => 'residential',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
        ]);

        $fakeCreditService = \Mockery::mock(CustomerCreditService::class);
        $fakeCreditService->shouldReceive('consumeCredit')
            ->once()
            ->andThrow(new \RuntimeException(self::LEAK_MARKER));
        $this->app->instance(CustomerCreditService::class, $fakeCreditService);

        $response = $this->actingAs($this->adminUser())->post(
            route('billing.customers.add-credit', $customer->id),
            [
                'action' => 'consume',
                'amount' => 10,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');

        $errorMessage = session('errors')->first('error');
        $this->assertStringNotContainsString(self::LEAK_MARKER, $errorMessage);
        $this->assertStringContainsString('No se pudo realizar la transacción', $errorMessage);

        Log::shouldHaveReceived('error')->once();
    }

    public function test_services_api_index_does_not_leak_exception_message_in_json(): void
    {
        Log::spy();

        $fakeRepository = \Mockery::mock(ServiceRepository::class);
        $fakeRepository->shouldReceive('query')
            ->andThrow(new \RuntimeException(self::LEAK_MARKER));
        $this->app->instance(ServiceRepository::class, $fakeRepository);

        $response = $this->getJson('/api/public/services');

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
        $this->assertStringNotContainsString(self::LEAK_MARKER, $response->getContent());
        $response->assertJsonFragment(['message' => 'Error al obtener servicios']);

        Log::shouldHaveReceived('error')->atLeast()->once();
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'exception_leak_admin_' . uniqid(),
            'email' => 'exception_leak_admin_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
