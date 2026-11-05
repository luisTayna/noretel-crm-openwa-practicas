<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            
            // Trigger (disparador)
            $table->enum('trigger_type', [
                'service_request',
                'schedule',
                'threshold',
                'event',
                'manual'
            ]);
            
            $table->json('trigger_conditions')->nullable()->comment('Condiciones para ejecutar la regla');
            
            // Acción
            $table->enum('action_type', [
                'router_reboot',
                'bandwidth_adjust',
                'send_notification',
                'create_ticket',
                'suspend_service',
                'activate_service',
                'run_script',
                'multiple_actions'
            ]);
            
            $table->json('action_config')->nullable()->comment('Configuración de la acción');
            
            // Alcance
            $table->enum('scope', ['all_routers', 'specific_routers', 'zone', 'node'])->default('specific_routers');
            $table->json('scope_config')->nullable()->comment('IDs o filtros de alcance');
            
            // Programación
            $table->string('schedule_cron')->nullable()->comment('Expresión cron para ejecuciones programadas');
            $table->timestamp('next_execution')->nullable();
            $table->timestamp('last_execution')->nullable();
            
            // Estado
            $table->boolean('is_active')->default(true);
            $table->integer('execution_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failure_count')->default(0);
            
            // Control
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['is_active', 'trigger_type']);
            $table->index('next_execution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
