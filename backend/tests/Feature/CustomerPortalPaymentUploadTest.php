<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\Notification;
use Modules\Core\Entities\User;
use Modules\Core\Services\NotificationService;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the real customer-portal payment-receipt-upload endpoint:
 * POST api/customer/upload-payment (route name customer.portal.upload-payment),
 * handled by Modules\Customer\Http\Controllers\CustomerAPIController::uploadPayment(),
 * protected by the "auth:sanctum" middleware.
 */
class CustomerPortalPaymentUploadTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 900000;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        foreach (['payments.view', 'payments.create', 'payments.edit'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->givePermissionTo(['payments.view', 'payments.create', 'payments.edit']);

        $salesRole = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $salesRole->givePermissionTo(['payments.view', 'payments.create', 'payments.edit']);

        $technicianRole = Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
        $technicianRole->givePermissionTo(['payments.view', 'payments.create']);

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    }

    public function test_uploading_a_receipt_notifies_admin_and_sales_but_not_technician(): void
    {
        $admin = $this->makeUser('admin', 'admin@test.com');
        $sales = $this->makeUser('sales', 'sales@test.com');
        $technician = $this->makeUser('technician', 'technician@test.com');

        [$customerUser] = $this->makeCustomerWithPendingInvoice();

        $response = $this->actingAs($customerUser, 'sanctum')
            ->postJson(route('customer.portal.upload-payment'), [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'amount' => '100',
                'method' => 'yape',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', ['status' => 'pending']);

        $this->assertSame(1, Notification::where('recipient', $admin->email)->count());
        $this->assertSame(1, Notification::where('recipient', $sales->email)->count());
        $this->assertSame(0, Notification::where('recipient', $technician->email)->count());
    }

    public function test_notification_service_failure_does_not_break_the_upload(): void
    {
        $this->makeUser('admin', 'admin@test.com');

        [$customerUser] = $this->makeCustomerWithPendingInvoice();

        $this->mock(NotificationService::class, function ($mock) {
            $mock->shouldReceive('notifySystemUsers')->andThrow(new \RuntimeException('notification service down'));
        });

        $response = $this->actingAs($customerUser, 'sanctum')
            ->postJson(route('customer.portal.upload-payment'), [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'amount' => '100',
                'method' => 'yape',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('payments', ['status' => 'pending']);
    }

    public function test_an_unexpected_failure_returns_a_generic_message_without_exception_details(): void
    {
        $this->makeUser('admin', 'admin@test.com');

        [$customerUser] = $this->makeCustomerWithPendingInvoice();

        // Force an unexpected DB failure while looking up the pending invoice.
        \Illuminate\Support\Facades\Schema::drop('invoices');

        $response = $this->actingAs($customerUser, 'sanctum')
            ->postJson(route('customer.portal.upload-payment'), [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'amount' => '100',
                'method' => 'yape',
            ]);

        $response->assertStatus(500);
        $data = $response->json();
        $this->assertFalse($data['success'] ?? true);
        $this->assertStringNotContainsString('Exception', $data['message'] ?? '');
        $this->assertStringNotContainsString('SQLSTATE', $data['message'] ?? '');
        $this->assertStringNotContainsString('no such table', $data['message'] ?? '');
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

    /**
     * @return array{0: User, 1: Customer}
     */
    private function makeCustomerWithPendingInvoice(): array
    {
        $customerUser = User::create([
            'username' => 'customer_' . $this->sequence++,
            'email' => 'customer_' . $this->sequence . '@test.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $customerUser->assignRole('customer');

        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'customer_type' => 'individual',
            'first_name' => 'Juan',
            'last_name' => 'Perez ' . $this->sequence,
            'identity_document' => 'DOC' . $this->sequence++,
            'active' => true,
        ]);

        $service = Service::create([
            'name' => 'Internet portal upload test',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan portal upload test',
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
            'invoice_number' => 'INV-PORTAL' . $this->sequence++,
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
