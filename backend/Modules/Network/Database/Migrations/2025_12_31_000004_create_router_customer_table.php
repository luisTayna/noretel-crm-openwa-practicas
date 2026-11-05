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
        Schema::create('router_customer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->onDelete('set null');
            
            // Configuración específica del cliente en el router
            $table->string('port')->nullable()->comment('Puerto asignado');
            $table->integer('vlan')->nullable()->comment('VLAN asignada');
            $table->ipAddress('assigned_ip')->nullable()->comment('IP asignada al cliente');
            $table->string('pppoe_username')->nullable();
            $table->text('pppoe_password')->nullable()->comment('Encriptado');
            
            // Limitaciones
            $table->integer('bandwidth_limit_down')->nullable()->comment('Límite de descarga en Mbps');
            $table->integer('bandwidth_limit_up')->nullable()->comment('Límite de subida en Mbps');
            
            // Estado
            $table->enum('connection_status', ['active', 'suspended', 'disconnected'])->default('active');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_connection')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Índices
            $table->unique(['router_id', 'customer_id']);
            $table->index('connection_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_customer');
    }
};
