<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    private const PERMISSIONS = [
        'operations.view', 'operations.manage',
        'onboarding_orders.view', 'onboarding_orders.create', 'onboarding_orders.edit', 'onboarding_orders.delete',

        'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
        'contracts.view', 'contracts.create', 'contracts.edit', 'contracts.delete',
        'installations.view', 'installations.create', 'installations.edit', 'installations.delete',
        'routes.view', 'routes.create', 'routes.edit', 'routes.delete',
        'slas.view', 'slas.create', 'slas.edit', 'slas.delete',
        'routers.view', 'routers.ssh', 'service_requests.view', 'service_requests.create', 'service_requests.edit', 'service_requests.delete',

        'services.view', 'services.create', 'services.edit', 'services.delete',
        'plans.view', 'plans.create', 'plans.edit', 'plans.delete',
        'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
        'additional-services.view', 'additional-services.create', 'additional-services.edit', 'additional-services.delete',

        'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.delete', 'invoices.void',
        'payments.view', 'payments.create', 'payments.edit', 'payments.delete', 'payments.void',
        'credit_notes.view', 'credit_notes.create', 'credit_notes.edit', 'credit_notes.delete',

        'users.view', 'users.create', 'users.edit', 'users.delete',
        'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
        'configuration.view', 'configuration.edit',
        'security.view', 'security.edit',
        'audit.view', 'audit.export',
    ];

    private const ROLE_PERMISSIONS = [
        'admin' => self::PERMISSIONS,
        'sales' => [
            'operations.view',

            'customers.view', 'customers.create', 'customers.edit',
            'services.view', 'services.create', 'services.edit', 'plans.view', 'promotions.view', 'additional-services.view',

            'onboarding_orders.view', 'onboarding_orders.create', 'onboarding_orders.edit',
            'installations.view', 'installations.create', 'installations.edit',
            'contracts.view', 'contracts.create', 'contracts.edit',
            'invoices.view', 'invoices.create', 'invoices.edit',
            'payments.view', 'payments.create', 'payments.edit',
            'service_requests.view', 'service_requests.create', 'service_requests.edit',
            'routes.view',
        ],
        'technician' => [
            'operations.view', 'operations.manage',
            'onboarding_orders.view', 'onboarding_orders.create', 'onboarding_orders.edit',

            'customers.view', 'customers.create', 'customers.edit',
            'contracts.view', 'contracts.create', 'contracts.edit',
            'installations.view', 'installations.create', 'installations.edit',
            'routes.view', 'routes.create', 'routes.edit',
            'routers.view', 'service_requests.view', 'service_requests.create', 'service_requests.edit',
            'services.view', 'plans.view', 'additional-services.view',
            'invoices.view', 'invoices.create',
            'payments.view', 'payments.create',
        ],
    ];

    public function run(): void
    {
        DB::beginTransaction();

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach (self::PERMISSIONS as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }

            foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);

                $role->syncPermissions($permissions);
            }

            Role::firstOrCreate([
                'name' => 'customer',
                'guard_name' => 'web',
            ]);

            if ($legacySuperAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first()) {
                $legacySuperAdmin->users()->each(function (User $user) {
                    $user->syncRoles(['admin']);
                });

                $legacySuperAdmin->delete();
            }

            DB::table('model_has_permissions')->delete();

            $admin = User::firstOrCreate(
                ['email' => 'administrador@example.test'],
                [
                    'username' => 'evaluador1',
                    'password' => Hash::make((string) env('DEMO_SEED_PASSWORD', 'change-me-before-use')),
                    'status' => 'active',
                    'requires_2fa' => false,
                    'email_verified_at' => now(),
                ]
            );
            $admin->syncRoles(['admin']);

            $testUser = User::updateOrCreate(
                ['email' => 'personal@example.test'],
                [
                    'username' => 'evaluador2',
                    'password' => Hash::make((string) env('DEMO_SEED_PASSWORD', 'change-me-before-use')),
                    'status' => 'active',
                    'requires_2fa' => false,
                    'email_verified_at' => now(),
                ]
            );
            $testUser->syncRoles(['customer']);

            Customer::updateOrCreate(
                ['email' => 'personal@example.test'],
                [
                    'user_id' => $testUser->id,
                    'customer_type' => 'individual',
                    'first_name' => 'Ejemplo',
                    'last_name' => 'Prueba',
                    'identity_document' => '00000000',
                    'phone' => '900000001',
                    'customer_status' => 'active',
                    'registration_date' => now(),
                    'active' => true,
                ]
            );

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            DB::commit();

            $this->command?->info('✅ Matriz ISP de 3 roles sincronizada con Spatie.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command?->error('❌ Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
