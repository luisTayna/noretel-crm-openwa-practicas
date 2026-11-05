<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Entities\AuditLog;
use Modules\Core\Entities\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $email, string $password = 'correct-password'): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::create([
            'username' => 'user_' . uniqid(),
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        return $user;
    }

    public function test_sixth_web_attempt_is_blocked_even_with_correct_password(): void
    {
        $user = $this->createUser('locked@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('core.auth.login.post'), [
                'email' => 'locked@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('core.auth.login.post'), [
            'email' => 'locked@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_before_lockout_clears_the_counter(): void
    {
        $user = $this->createUser('cleared@example.com');

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('core.auth.login.post'), [
                'email' => 'cleared@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $success = $this->post(route('core.auth.login.post'), [
            'email' => 'cleared@example.com',
            'password' => 'correct-password',
        ]);
        $this->assertAuthenticatedAs($user);

        auth()->logout();

        // After a successful login the counter should be reset: another
        // failed attempt right after should not trigger the lockout.
        $response = $this->post(route('core.auth.login.post'), [
            'email' => 'cleared@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')?->get('email') ?? [];
        $this->assertStringNotContainsString('Demasiados intentos', implode(' ', $errors));
    }

    public function test_different_email_and_ip_is_not_affected_by_another_lockout(): void
    {
        $this->createUser('victim@example.com');
        $otherUser = $this->createUser('bystander@example.com');

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('core.auth.login.post'), [
                'email' => 'victim@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('core.auth.login.post'), [
            'email' => 'bystander@example.com',
            'password' => 'correct-password',
        ]);

        $this->assertAuthenticatedAs($otherUser);
    }

    public function test_lockout_writes_one_audit_row(): void
    {
        $this->createUser('audited@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('core.auth.login.post'), [
                'email' => 'audited@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->assertDatabaseCount('audit_logs', 0);

        $this->post(route('core.auth.login.post'), [
            'email' => 'audited@example.com',
            'password' => 'correct-password',
        ]);

        $this->assertDatabaseCount('audit_logs', 1);
        $log = AuditLog::first();
        $this->assertSame('auth_login_throttled', $log->action_type);
        $this->assertSame('auth', $log->module);
        $this->assertStringNotContainsString('correct-password', $log->action_detail);
    }

    public function test_api_login_returns_429_when_throttled(): void
    {
        $this->createUser('api-locked@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('api.auth.login'), [
                'email' => 'api-locked@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->postJson(route('api.auth.login'), [
            'email' => 'api-locked@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(429);
        $this->assertGuest();
    }
}
