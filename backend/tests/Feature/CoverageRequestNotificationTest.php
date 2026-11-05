<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Entities\Notification;
use Modules\Core\Entities\User;
use Modules\Public\Entities\CoverageRequest;
use Modules\Public\Emails\CoverageRequestConfirmationMail;
use Modules\Public\Http\Controllers\CoverageRequestController;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers Modules\Public\Http\Controllers\CoverageRequestController::store().
 *
 * NOTE: the Public module has no module.json/ServiceProvider and is not
 * listed in modules_statuses.json, so these routes
 * (Modules/Public/Routes/api.php) are not currently registered by the
 * running application. This is exercised here by invoking the controller
 * action directly, the same way the HTTP kernel would once the module is
 * wired up.
 */
class CoverageRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The Public module has no module.json, so nwidart-laravel-modules
        // never discovers it and its migrations do not run automatically.
        // Run the coverage_requests migration explicitly for this test only.
        (include base_path('Modules/Public/Database/Migrations/2025_11_26_012625_create_coverage_requests_table.php'))->up();

        // CoverageRequest::countInRadius() uses raw MySQL trig functions
        // (acos/cos/sin/radians) that SQLite does not provide natively.
        // Register them for this test's in-memory SQLite connection only.
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('acos', 'acos', 1);
        $pdo->sqliteCreateFunction('cos', 'cos', 1);
        $pdo->sqliteCreateFunction('sin', 'sin', 1);
        $pdo->sqliteCreateFunction('radians', fn ($degrees) => deg2rad($degrees), 1);

        foreach (['operations.view', 'operations.manage'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
    }

    public function test_reaching_the_threshold_notifies_admin_and_technician(): void
    {
        $admin = $this->makeUser('admin', 'admin@test.com');
        $technician = $this->makeUser('technician', 'technician@test.com');
        $sales = $this->makeUser('sales', 'sales@test.com');

        $this->seedPendingRequestsInZone(14, -12.0464, -77.0428);

        $response = $this->submit([
            'name' => 'Cliente Nuevo',
            'email' => 'cliente15@test.com',
            'phone' => '999999999',
            'address' => 'Av. Test 123',
            'coordinates' => ['lat' => -12.0464, 'lng' => -77.0428],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false);
        $this->assertSame(15, $data['requests_in_zone']);

        $this->assertSame(1, Notification::where('recipient', $admin->email)->count());
        $this->assertSame(1, Notification::where('recipient', $technician->email)->count());
        $this->assertSame(0, Notification::where('recipient', $sales->email)->count());
    }

    public function test_below_threshold_does_not_notify(): void
    {
        $admin = $this->makeUser('admin', 'admin@test.com');

        $this->seedPendingRequestsInZone(5, -12.0464, -77.0428);

        $response = $this->submit([
            'name' => 'Cliente Nuevo',
            'email' => 'cliente6@test.com',
            'phone' => '999999999',
            'address' => 'Av. Test 123',
            'coordinates' => ['lat' => -12.0464, 'lng' => -77.0428],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false);
        $this->assertSame(0, Notification::where('recipient', $admin->email)->count());
    }

    public function test_exceeding_threshold_does_not_renotify(): void
    {
        $admin = $this->makeUser('admin', 'admin@test.com');

        // Already 15 pending requests in the zone (threshold already reached).
        $this->seedPendingRequestsInZone(15, -12.0464, -77.0428);

        $response = $this->submit([
            'name' => 'Cliente Extra',
            'email' => 'cliente16@test.com',
            'phone' => '999999999',
            'address' => 'Av. Test 123',
            'coordinates' => ['lat' => -12.0464, 'lng' => -77.0428],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false);
        $this->assertSame(16, $data['requests_in_zone']);

        $this->assertSame(
            0,
            Notification::where('recipient', $admin->email)->count(),
            'Admin should not be re-notified once the threshold was already reached.'
        );
    }

    public function test_confirmation_email_is_sent_to_the_requester(): void
    {
        Mail::fake();

        $response = $this->submit([
            'name' => 'Cliente Mail',
            'email' => 'cliente-mail@test.com',
            'phone' => '999999999',
            'address' => 'Av. Test 123',
            'coordinates' => ['lat' => -12.0464, 'lng' => -77.0428],
        ]);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false);

        Mail::assertSent(CoverageRequestConfirmationMail::class, function ($mail) {
            return $mail->hasTo('cliente-mail@test.com');
        });
    }

    public function test_request_still_succeeds_when_the_mailer_throws(): void
    {
        $pendingMail = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pendingMail->shouldReceive('send')->andThrow(new \Exception('smtp down'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        $response = $this->submit([
            'name' => 'Cliente Mail Falla',
            'email' => 'cliente-mail-falla@test.com',
            'phone' => '999999999',
            'address' => 'Av. Test 123',
            'coordinates' => ['lat' => -12.0464, 'lng' => -77.0428],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success'] ?? false);
    }

    private function makeUser(string $role, string $email): User
    {
        $user = User::create([
            'username' => str_replace('@test.com', '', $email),
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function seedPendingRequestsInZone(int $count, float $lat, float $lng): void
    {
        for ($i = 0; $i < $count; $i++) {
            CoverageRequest::create([
                'name' => 'Vecino ' . $i,
                'email' => 'vecino' . $i . uniqid() . '@test.com',
                'phone' => '999999999',
                'address' => 'Zona de prueba',
                'latitude' => $lat,
                'longitude' => $lng,
                'status' => 'pending',
            ]);
        }
    }

    private function submit(array $payload)
    {
        $request = Request::create('/test-coverage-request', 'POST', $payload);

        return app(CoverageRequestController::class)->store($request);
    }
}
