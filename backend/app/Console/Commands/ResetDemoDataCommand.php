<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetDemoDataCommand extends Command
{
    protected $signature = 'noretel:reset-demo-data
        {--force : Ejecutar sin confirmación interactiva}
        {--dry-run : Mostrar tablas que se limpiarían sin borrar datos}
        {--with-logs : También limpiar audit_logs, notifications, sessions y jobs}';

    protected $description = 'Vacía datos operativos/de prueba conservando usuarios, roles, permisos, empleados, configuración y catálogos base.';

    public function handle(): int
    {
        if (! $this->isSafeEnvironment() && ! $this->option('force')) {
            $this->error('Protección activa: este comando solo corre en local/development/testing salvo que uses --force.');
            $this->warn('Entorno actual: ' . app()->environment());

            return self::FAILURE;
        }

        $tables = $this->existingTables($this->tablesToTruncate());

        if ($this->option('with-logs')) {
            $tables = array_values(array_unique(array_merge($tables, $this->existingTables($this->logTables()))));
        }

        if ($tables === []) {
            $this->warn('No hay tablas configuradas para limpiar o no existen en esta base de datos.');

            return self::SUCCESS;
        }

        $this->info('Tablas que se limpiarán:');
        foreach ($tables as $table) {
            $this->line(' - ' . $table);
        }

        $this->newLine();
        $this->warn('Se conservarán usuarios, roles, permisos, empleados, configuración, planes/servicios, routers/nodos y zonas de cobertura.');

        if ($this->option('dry-run')) {
            $this->info('Dry-run: no se borró ningún dato.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('¿Seguro que quieres vaciar estos datos operativos?', false)) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        $this->truncateTables($tables);

        $this->info('Datos operativos/de prueba limpiados correctamente.');

        return self::SUCCESS;
    }

    protected function isSafeEnvironment(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    protected function tablesToTruncate(): array
    {
        return [
            // Yape / conciliación de pagos
            'yape_payment_intents',
            'yape_client_evidences',
            'yape_receiver_events',

            // Facturación y cobranza
            'payment_reminders',
            'customer_credit_movements',
            'payments',
            'credit_notes',
            'invoice_items',
            'invoices',

            // Operación comercial / clientes
            'customer_lead',
            'leads',
            'interactions',
            'document_versions',
            'documents',
            'emergency_contacts',
            'addresses',
            'customers',

            // Contratos e instalación
            'contract_sla',
            'contracted_services',
            'contracts',
            'installation_photos',
            'used_materials',
            'installed_equipment',
            'installations',
            'onboarding_orders',

            // Solicitudes y asignaciones operativas
            'service_requests',
            'router_customer',
            'router_metrics_history',
            'router_logs',

            // Cobertura solicitada por prospectos/clientes
            'coverage_requests',
        ];
    }

    protected function logTables(): array
    {
        return [
            'audit_logs',
            'notifications',
            'sessions',
            'jobs',
            'failed_jobs',
        ];
    }

    protected function existingTables(array $tables): array
    {
        return array_values(array_filter($tables, static fn (string $table): bool => Schema::hasTable($table)));
    }

    protected function truncateTables(array $tables): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
                $this->line('Limpiada: ' . $table);
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }
}
