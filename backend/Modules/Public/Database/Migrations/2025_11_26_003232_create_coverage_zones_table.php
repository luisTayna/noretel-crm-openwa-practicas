<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre de la zona (ej: "San Jerónimo")
            $table->string('department')->default('Cusco');
            $table->string('province');
            $table->string('district');
            $table->decimal('latitude', 10, 7); // Centro de la zona
            $table->decimal('longitude', 10, 7);
            $table->decimal('radius_km', 5, 2)->default(2.0); // Radio de cobertura en km
            $table->enum('quality', ['excelente', 'buena', 'estable', 'baja'])->default('buena');
            $table->boolean('active')->default(true);
            $table->json('available_plans')->nullable(); // IDs de planes disponibles
            $table->text('description')->nullable();
            $table->timestamps();

            // Índices para búsquedas geográficas
            $table->index(['latitude', 'longitude']);
            $table->index('district');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coverage_zones');
    }
};
