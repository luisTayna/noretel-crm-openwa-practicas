<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Entities\User;
use Modules\Network\Adapters\RouterShellClient;
use Modules\Network\Entities\Router;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SshConsoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_permission_cannot_view_ssh_console(): void
    {
        $router = $this->createRouter();
        $user = $this->userWithoutPermission();

        $response = $this->actingAs($user)->get(route('network.ssh.show', $router));

        $response->assertStatus(403);
    }

    public function test_user_without_permission_cannot_execute_command_and_client_is_never_called(): void
    {
        $router = $this->createRouter();
        $user = $this->userWithoutPermission();
        $fakeClient = $this->bindFakeClient();

        $response = $this->actingAs($user)->postJson(route('network.ssh.execute', $router), [
            'command' => 'ls -la',
        ]);

        $response->assertStatus(403);
        $this->assertFalse($fakeClient->called);
    }

    public function test_user_with_permission_can_execute_command_and_audit_is_recorded(): void
    {
        $router = $this->createRouter();
        $user = $this->userWithPermission();
        $fakeClient = $this->bindFakeClient(output: 'ok output');

        $response = $this->actingAs($user)->postJson(route('network.ssh.execute', $router), [
            'command' => 'uptime',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'output' => 'ok output']);
        $this->assertTrue($fakeClient->called);
        $this->assertSame('uptime', $fakeClient->receivedCommand);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'routers',
        ]);
    }

    public function test_client_failure_returns_generic_error_and_audit_records_failure(): void
    {
        $router = $this->createRouter();
        $user = $this->userWithPermission();
        $this->bindFakeClient(throw: new \Exception('boom: leaked internal ssh detail'));

        $response = $this->actingAs($user)->postJson(route('network.ssh.execute', $router), [
            'command' => 'uptime',
        ]);

        $response->assertStatus(500);
        $response->assertJson(['success' => false]);
        $this->assertStringNotContainsString('boom', $response->getContent());
        $this->assertStringNotContainsString('leaked internal ssh detail', $response->getContent());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'routers',
        ]);
    }

    public function test_command_exceeding_max_length_is_rejected(): void
    {
        $router = $this->createRouter();
        $user = $this->userWithPermission();
        $fakeClient = $this->bindFakeClient();

        $response = $this->actingAs($user)->postJson(route('network.ssh.execute', $router), [
            'command' => str_repeat('a', 501),
        ]);

        $response->assertStatus(422);
        $this->assertFalse($fakeClient->called);
    }

    private function createRouter(): Router
    {
        return Router::create([
            'name' => 'Test Router',
            'code' => 'TR-' . uniqid(),
            'ip_address' => '10.0.0.' . random_int(1, 254),
            'brand' => 'MikroTik',
        ]);
    }

    private function userWithPermission(): User
    {
        Permission::firstOrCreate(['name' => 'routers.ssh', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'ssh-tester-with-permission', 'guard_name' => 'web']);
        $role->givePermissionTo('routers.ssh');

        $user = User::create([
            'username' => 'ssh_operator_' . uniqid(),
            'email' => 'ssh_operator_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('ssh-tester-with-permission');

        return $user;
    }

    private function userWithoutPermission(): User
    {
        Role::firstOrCreate(['name' => 'ssh-tester-without-permission', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'technician_' . uniqid(),
            'email' => 'technician_' . uniqid() . '@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('ssh-tester-without-permission');

        return $user;
    }

    private function bindFakeClient(?string $output = 'output', ?\Throwable $throw = null): object
    {
        $fakeClient = new class($output, $throw) implements RouterShellClient {
            public bool $called = false;
            public ?string $receivedCommand = null;

            public function __construct(private ?string $output, private ?\Throwable $throw)
            {
            }

            public function execute(Router $router, string $command): string
            {
                $this->called = true;
                $this->receivedCommand = $command;

                if ($this->throw !== null) {
                    throw $this->throw;
                }

                return $this->output ?? '';
            }
        };

        $this->app->instance(RouterShellClient::class, $fakeClient);

        return $fakeClient;
    }
}
