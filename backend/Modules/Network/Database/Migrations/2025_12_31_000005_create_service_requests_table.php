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
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            
            // Relaciones
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('router_id')->nullable()->constrained('routers')->onDelete('set null');
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->onDelete('set null');
            
            // Tipo de solicitud
            $table->enum('type', [
                'router_reboot',
                'connection_issue',
                'slow_speed',
                'no_internet',
                'configuration_change',
                'technical_visit',
                'equipment_replacement',
                'other'
            ]);
            
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'cancelled'])->default('pending');
            
            // Descripción
            $table->text('description');
            $table->text('customer_notes')->nullable();
            
            // Asignación
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null')->comment('Técnico asignado');
            $table->timestamp('assigned_at')->nullable();
            
            // Resolución
            $table->text('resolution_notes')->nullable();
            $table->text('technical_notes')->nullable();
            $table->json('response_data')->nullable()->comment('Respuesta de la API del router');
            
            // Control de tiempo
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('resolution_time')->nullable()->comment('Tiempo de resolución en minutos');
            
            // Automatización
            $table->boolean('is_automated')->default(false);
            $table->boolean('requires_visit')->default(false);
            $table->timestamp('scheduled_visit')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['customer_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('ticket_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
