<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('es_PE');
        $now = Carbon::now();
        $password = Hash::make('password123');

        // ==== 1. CREAR 10 MIEMBROS DEL STAFF ====
        // No dependas de IDs fijos: en una BD limpia los IDs pueden cambiar según
        // el orden real de creación de roles. Se resuelve por nombre para respetar
        // la integridad referencial de Spatie.
        $rolesStaff = DB::table('roles')
            ->whereIn('name', ['admin', 'technician'])
            ->pluck('id', 'name');

        foreach (['admin', 'technician'] as $requiredRole) {
            if (! $rolesStaff->has($requiredRole)) {
                throw new \RuntimeException("El rol '{$requiredRole}' no existe. Ejecuta primero CoreDatabaseSeeder.");
            }
        }

        $customerRoleId = DB::table('roles')->where('name', 'customer')->value('id');
        
        for ($i = 0; $i < 10; $i++) {
            $roleName = $faker->randomElement(['admin', 'technician']);
            $roleId = $rolesStaff[$roleName];
            
            $userId = DB::table('users')->insertGetId([
                'username' => 'staff_' . strtolower($faker->firstName) . '_' . $i . rand(10, 99),
                'email' => $faker->unique()->safeEmail,
                'password' => $password,
                'status' => 'active',
                'email_verified_at' => $now,
                'last_access' => $faker->dateTimeBetween('-1 month', 'now'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Asignar rol
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'Modules\Core\Entities\User',
                'model_id' => $userId
            ]);
        }

        // ==== 2. CREAR 20 CLIENTES ====
        $zonas = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('coverage_zones')) {
            $zonas = DB::table('coverage_zones')->pluck('name')->toArray();
        }
        
        if (empty($zonas)) {
            $zonas = ['Centro', 'Wanchaq', 'San Sebastián', 'San Jerónimo', 'Santiago'];
        }
        
        $planes = DB::table('plans')->get();
        if ($planes->isEmpty()) {
            $this->command->error('No hay planes en la base de datos. No se pueden crear contratos.');
            return;
        }

        for ($i = 0; $i < 20; $i++) {
            // A. Crear usuario del cliente
            $firstName = $faker->firstName;
            $lastName = $faker->lastName;
            $email = $faker->unique()->safeEmail;
            
            $userId = DB::table('users')->insertGetId([
                'username' => strtolower($firstName) . '.' . strtolower($lastName),
                'email' => $email,
                'password' => $password,
                'status' => 'active',
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Asignar rol customer solo si existe. La matriz actual crea admin,
            // sales y technician; insertar un ID fijo aquí rompe una BD limpia.
            if ($customerRoleId) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $customerRoleId,
                    'model_type' => 'Modules\Core\Entities\User',
                    'model_id' => $userId
                ]);
            }

            // B. Crear registro en tabla customers
            $customerId = DB::table('customers')->insertGetId([
                'user_id' => $userId,
                'customer_type' => 'individual',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'identity_document' => $faker->numerify('########'),
                'email' => $email,
                'phone' => '9' . $faker->numerify('########'),
                'customer_status' => 'active',
                'registration_date' => $faker->dateTimeBetween('-1 year', '-1 month'),
                'active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // C. Crear dirección (para el filtro de zonas)
            DB::table('addresses')->insert([
                'customer_id' => $customerId,
                'address_type' => 'home',
                'street' => $faker->streetAddress,
                'city' => $faker->randomElement($zonas),
                'state' => 'Cusco',
                'is_primary' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // D. Crear contrato activo
            $plan = $planes->random();
            $startDate = Carbon::instance($faker->dateTimeBetween('-6 months', '-1 month'));
            
            $contractId = DB::table('contracts')->insertGetId([
                'customer_id' => $customerId,
                'plan_id' => $plan->id,
                'start_date' => $startDate,
                'status' => 'active',
                'final_price' => $plan->price,
                'created_at' => $startDate,
                'updated_at' => $now,
            ]);

            // E. Generar facturas (Invoices) de los últimos meses (desde startDate hasta hoy)
            $monthsDiff = $startDate->diffInMonths($now);
            $monthsDiff = $monthsDiff == 0 ? 1 : $monthsDiff; // Al menos 1 mes

            for ($m = 0; $m <= $monthsDiff; $m++) {
                $invoiceDate = (clone $startDate)->addMonths($m);
                $dueDate = (clone $invoiceDate)->addDays(5);
                
                // Determinar estado de la factura
                // Si es del mes actual o anterior, puede estar pendiente o vencida
                $isRecent = $invoiceDate->isCurrentMonth();
                $status = 'paid';
                
                if ($isRecent) {
                    $status = $faker->randomElement(['paid', 'pending', 'overdue']);
                } elseif ($invoiceDate->isLastMonth() && $faker->boolean(20)) { // 20% mora mes pasado
                    $status = 'overdue';
                }

                $invoiceId = DB::table('invoices')->insertGetId([
                    'contract_id' => $contractId,
                    'invoice_number' => 'INV-' . $invoiceDate->format('Ym') . '-' . str_pad($customerId, 4, '0', STR_PAD_LEFT) . '-' . $m,
                    'amount' => $plan->price,
                    'total_amount' => $plan->price,
                    'issue_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'document_type' => 'invoice',
                    'created_at' => $invoiceDate,
                    'updated_at' => $invoiceDate,
                ]);

                // Si está pagada, crear registro de pago
                if ($status === 'paid') {
                    $paymentDate = (clone $dueDate)->subDays(rand(0, 5));
                    if ($paymentDate > $now) $paymentDate = $now; // no pagar en el futuro
                    
                    DB::table('payments')->insert([
                        'invoice_id' => $invoiceId,
                        'user_id' => $userId,
                        'amount' => $plan->price,
                        'payment_date' => $paymentDate,
                        'payment_method' => $faker->randomElement(['yape', 'transferencia', 'efectivo']),
                        'status' => 'completed',
                        'created_at' => $paymentDate,
                        'updated_at' => $paymentDate,
                    ]);
                }
            }
        }
        
        $this->command->info('¡30 Usuarios creados exitosamente! (10 Staff, 20 Clientes con contratos y facturas)');
    }
}
