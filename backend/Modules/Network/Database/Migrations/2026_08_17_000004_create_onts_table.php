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
        Schema::create('onts', function (Blueprint $table) {
            $table->id();

            // Identidad física. El número de serie GPON es el identificador real del equipo;
            // sobrevive a cambios de puerto y de cliente.
            $table->string('serial_number')->unique()->comment('Número de serie GPON, por ejemplo HWTC1A2B3C4D');
            $table->string('model')->nullable();

            // Ubicación en la red. Queda nula mientras el ONT esté en almacén o dado de baja.
            $table->foreignId('olt_pon_port_id')->nullable()->constrained('olt_pon_ports')->onDelete('set null');
            $table->unsignedSmallInteger('ont_index')->nullable()->comment('ID del ONT dentro del puerto PON, 0-127');

            // Puente con el negocio. Es lo que convierte una lectura óptica en un cliente con nombre.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->onDelete('set null');
            $table->foreignId('installed_equipment_id')->nullable()->comment('Trazabilidad con el acta de instalación; sin llave foránea a propósito');

            // Estado operativo reportado por la OLT.
            $table->enum('status', ['online', 'offline', 'los', 'dying_gasp', 'provisioned', 'unknown'])
                ->default('unknown');

            // Última lectura conocida. Se guarda aquí, y no solo en el histórico,
            // para que la ficha del cliente se abra sin consultar la tabla de métricas.
            $table->decimal('last_rx_power', 6, 2)->nullable()->comment('Potencia óptica recibida por el ONT, en dBm');
            $table->decimal('last_tx_power', 6, 2)->nullable();
            $table->decimal('baseline_rx_power', 6, 2)->nullable()->comment('Línea base propia del ONT, para detectar degradación relativa');
            $table->unsignedInteger('distance_m')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // Estado legible. Se persiste calculado para que la misma palabra aparezca
            // en todas las pantallas y los umbrales se cambien en un solo sitio.
            $table->enum('signal_status', ['good', 'warning', 'critical', 'no_signal', 'unknown'])
                ->default('unknown');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Un ONT ocupa una sola posición dentro de su puerto.
            $table->unique(['olt_pon_port_id', 'ont_index']);
            $table->index('signal_status');
            $table->index('status');
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onts');
    }
};
