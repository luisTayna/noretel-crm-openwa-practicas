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
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique()->comment('Código único del router');
            $table->enum('brand', ['Huawei', 'MikroTik', 'Cisco', 'TP-Link', 'Ubiquiti'])->default('MikroTik');
            $table->string('model')->nullable();
            $table->string('serial_number')->unique()->nullable();
            $table->ipAddress('ip_address')->unique();
            $table->macAddress('mac_address')->unique()->nullable();
            
            // Configuración de API
            $table->string('api_endpoint')->nullable();
            $table->text('api_key')->nullable()->comment('Encriptado');
            $table->text('credentials')->nullable()->comment('JSON encriptado con usuario/contraseña');
            
            // Ubicación
            $table->string('location')->nullable()->comment('Dirección física');
            $table->string('zone')->nullable()->comment('Zona de cobertura');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Estado y configuración
            $table->enum('status', ['active', 'inactive', 'maintenance', 'error', 'offline'])->default('active');
            $table->string('firmware_version')->nullable();
            $table->integer('max_clients')->default(50);
            $table->integer('connected_clients')->default(0);
            
            // Métricas
            $table->decimal('signal_quality', 5, 2)->nullable()->comment('Calidad de señal en %');
            $table->bigInteger('bandwidth_usage')->default(0)->comment('Uso de ancho de banda en KB');
            $table->decimal('cpu_usage', 5, 2)->nullable()->comment('Uso de CPU en %');
            $table->decimal('memory_usage', 5, 2)->nullable()->comment('Uso de memoria en %');
            $table->integer('uptime')->default(0)->comment('Tiempo activo en segundos');
            
            // Relaciones
            $table->foreignId('node_id')->nullable()->constrained('nodes')->onDelete('set null');
            $table->foreignId('parent_router_id')->nullable()->constrained('routers')->onDelete('set null')->comment('Router padre en caso de cascada');
            
            // Control
            $table->timestamp('last_reboot')->nullable();
            $table->timestamp('last_health_check')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['status', 'zone']);
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
