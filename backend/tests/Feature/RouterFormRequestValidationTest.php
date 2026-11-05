<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Entities\User;
use Modules\Network\Entities\Router;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RouterFormRequestValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_route_creates_a_router_with_valid_data(): void
    {
        $response = $this->actingAs($this->adminUser())->post(route('network.routers.store'), $this->storePayload());

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $router = Router::where('name', 'Router Troncal Centro')->firstOrFail();
        $this->assertSame('MikroTik', $router->brand);
        $this->assertSame('admin', $router->credentials['username']);
        $this->assertSame('secret123', $router->credentials['password']);
    }

    public function test_store_rejects_invalid_ip_address(): void
    {
        $response = $this->actingAs($this->adminUser())->post(
            route('network.routers.store'),
            $this->storePayload(['ip_address' => 'not-an-ip'])
        );

        $response->assertSessionHasErrors('ip_address');
        $this->assertDatabaseMissing('routers', ['name' => 'Router Troncal Centro']);
    }

    public function test_store_rejects_unsupported_brand(): void
    {
        $response = $this->actingAs($this->adminUser())->post(
            route('network.routers.store'),
            $this->storePayload(['brand' => 'Nokia'])
        );

        $response->assertSessionHasErrors('brand');
        $this->assertDatabaseMissing('routers', ['name' => 'Router Troncal Centro']);
    }

    public function test_store_rejects_duplicate_ip_address(): void
    {
        $this->createRouter(['ip_address' => '10.0.0.5']);

        $response = $this->actingAs($this->adminUser())->post(
            route('network.routers.store'),
            $this->storePayload(['ip_address' => '10.0.0.5'])
        );

        $response->assertSessionHasErrors('ip_address');
        $this->assertDatabaseMissing('routers', ['name' => 'Router Troncal Centro']);
    }

    public function test_update_route_persists_valid_changes(): void
    {
        $router = $this->createRouter();

        $response = $this->actingAs($this->adminUser())->put(
            route('network.routers.update', $router),
            $this->updatePayload($router, ['name' => 'Router Renombrado'])
        );

        $response->assertRedirect();
        $this->assertSame('Router Renombrado', $router->fresh()->name);
    }

    public function test_update_with_blank_password_keeps_existing_password(): void
    {
        $router = $this->createRouter([], ['username' => 'admin', 'password' => 'old-secret', 'api_port' => 8728]);

        $payload = $this->updatePayload($router);
        $payload['credentials']['password'] = '';

        $response = $this->actingAs($this->adminUser())->put(route('network.routers.update', $router), $payload);

        $response->assertRedirect();
        $fresh = $router->fresh();
        $this->assertSame('old-secret', $fresh->credentials['password']);
        $this->assertSame('admin', $fresh->credentials['username']);
    }

    public function test_update_can_set_a_new_password(): void
    {
        $router = $this->createRouter([], ['username' => 'admin', 'password' => 'old-secret', 'api_port' => 8728]);

        $payload = $this->updatePayload($router);
        $payload['credentials']['password'] = 'new-secret';

        $response = $this->actingAs($this->adminUser())->put(route('network.routers.update', $router), $payload);

        $response->assertRedirect();
        $this->assertSame('new-secret', $router->fresh()->credentials['password']);
    }

    public function test_update_rejects_duplicate_ip_address_from_another_router(): void
    {
        $this->createRouter(['ip_address' => '10.0.0.9', 'code' => 'MK-CEN-9999']);
        $router = $this->createRouter(['ip_address' => '10.0.0.10', 'code' => 'MK-CEN-9998']);

        $payload = $this->updatePayload($router, ['ip_address' => '10.0.0.9']);

        $response = $this->actingAs($this->adminUser())->put(route('network.routers.update', $router), $payload);

        $response->assertSessionHasErrors('ip_address');
        $this->assertSame('10.0.0.10', $router->fresh()->ip_address);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Router Troncal Centro',
            'brand' => 'MikroTik',
            'ip_address' => '192.168.50.1',
            'zone' => 'Centro',
            'max_clients' => 50,
            'credentials' => [
                'username' => 'admin',
                'password' => 'secret123',
                'api_port' => 8728,
            ],
        ], $overrides);
    }

    private function updatePayload(Router $router, array $overrides = []): array
    {
        return array_merge([
            'name' => $router->name,
            'code' => $router->code,
            'brand' => $router->brand,
            'ip_address' => $router->ip_address,
            'zone' => $router->zone,
            'max_clients' => $router->max_clients,
            'status' => $router->status,
            'credentials' => [
                'username' => '',
                'password' => '',
                'api_port' => '',
            ],
        ], $overrides);
    }

    private function createRouter(array $overrides = [], array $credentials = []): Router
    {
        $router = new Router(array_merge([
            'name' => 'Router Existente',
            'code' => 'MK-CEN-0001',
            'brand' => 'MikroTik',
            'ip_address' => '172.16.0.1',
            'zone' => 'Centro',
            'max_clients' => 50,
            'status' => 'active',
            'credentials' => $credentials,
        ], $overrides));
        $router->save();

        return $router;
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'router_admin_' . uniqid(),
            'email' => 'router_admin_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }
}
