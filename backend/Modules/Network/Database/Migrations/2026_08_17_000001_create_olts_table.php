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
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('brand')->default('huawei');
            $table->string('model')->default('MA5800');

            // Acceso de gestión. El colector solo lee; nunca configura.
            $table->string('management_ip');
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('ssh_username')->nullable();
            $table->text('credentials')->nullable()->comment('Credenciales cifradas del usuario de solo lectura');
            $table->boolean('snmp_enabled')->default(false)->comment('Si está activo, las métricas masivas van por SNMP en lugar de SSH');
            $table->unsignedSmallInteger('snmp_port')->default(161);

            // Ubicación
            $table->foreignId('node_id')->nullable()->constrained('nodes')->onDelete('set null');
            $table->string('location')->nullable();
            $table->string('zone')->nullable();

            // Estado
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamp('last_sync_at')->nullable()->comment('Última lectura correcta del colector');
            $table->text('last_sync_error')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
