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
        Schema::create('router_metrics_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained('routers')->onDelete('cascade');
            
            // Métricas
            $table->integer('connected_clients')->default(0);
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->decimal('memory_usage', 5, 2)->nullable();
            $table->decimal('signal_quality', 5, 2)->nullable();
            $table->bigInteger('bandwidth_usage')->default(0);
            $table->bigInteger('bandwidth_download')->default(0);
            $table->bigInteger('bandwidth_upload')->default(0);
            $table->integer('uptime')->default(0);
            $table->decimal('temperature', 5, 2)->nullable()->comment('Temperatura en °C');
            
            // Estado
            $table->enum('status', ['active', 'inactive', 'error'])->default('active');
            $table->integer('packet_loss')->nullable()->comment('Pérdida de paquetes en %');
            $table->integer('latency')->nullable()->comment('Latencia en ms');
            
            $table->timestamp('recorded_at')->useCurrent();
            
            // Índices
            $table->index(['router_id', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_metrics_history');
    }
};
