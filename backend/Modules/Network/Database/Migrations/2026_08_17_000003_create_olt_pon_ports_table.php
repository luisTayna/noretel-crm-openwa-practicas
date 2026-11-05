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
        Schema::create('olt_pon_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_board_id')->constrained('olt_boards')->onDelete('cascade');
            $table->unsignedSmallInteger('port');

            $table->string('description')->nullable()->comment('Zona o sector que alimenta este puerto');
            $table->enum('status', ['up', 'down', 'unknown'])->default('unknown');

            // Capacidad. El colector actualiza ont_count; max_onts lo fija el administrador.
            $table->unsignedSmallInteger('max_onts')->default(128);
            $table->unsignedSmallInteger('ont_count')->default(0);

            $table->timestamps();

            $table->unique(['olt_board_id', 'port']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_pon_ports');
    }
};
