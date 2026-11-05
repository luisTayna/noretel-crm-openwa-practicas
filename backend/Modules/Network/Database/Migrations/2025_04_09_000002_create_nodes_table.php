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
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['core', 'distribution', 'access', 'backbone'])->default('access');
            
            // Ubicación
            $table->string('location');
            $table->string('zone');
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('department')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // Capacidad
            $table->integer('capacity')->default(100)->comment('Capacidad máxima de clientes');
            $table->integer('current_load')->default(0)->comment('Clientes actuales conectados');
            $table->decimal('coverage_radius', 8, 2)->default(500)->comment('Radio de cobertura en metros');
            
            // Estado
            $table->enum('status', ['active', 'inactive', 'maintenance', 'planned'])->default('active');
            $table->boolean('is_operational')->default(true);
            
            // Relaciones
            $table->foreignId('parent_node_id')->nullable()->constrained('nodes')->onDelete('set null');
            
            $table->text('description')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index(['zone', 'status']);
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
