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
        Schema::create('router_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            
            // Acción
            $table->enum('action', [
                'reboot',
                'configuration_change',
                'status_check',
                'bandwidth_adjustment',
                'firmware_update',
                'client_connected',
                'client_disconnected',
                'error',
                'health_check',
                'manual_command'
            ]);
            
            $table->enum('status', ['initiated', 'success', 'failed', 'timeout'])->default('initiated');
            
            // Detalles
            $table->text('description')->nullable();
            $table->json('request_data')->nullable()->comment('Datos enviados');
            $table->json('response_data')->nullable()->comment('Respuesta recibida');
            $table->text('error_message')->nullable();
            
            // Métricas antes/después
            $table->json('metrics_before')->nullable();
            $table->json('metrics_after')->nullable();
            
            // Usuario/Sistema
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests')->onDelete('set null');
            $table->foreignId('automation_rule_id')->nullable()->constrained('automation_rules')->onDelete('set null');
            
            $table->boolean('is_automated')->default(false);
            $table->integer('execution_time')->nullable()->comment('Tiempo de ejecución en milisegundos');
            
            $table->timestamps();
            
            // Índices
            $table->index(['router_id', 'created_at']);
            $table->index(['action', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_logs');
    }
};
