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
        Schema::create('olt_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('olt_id')->constrained('olts')->onDelete('cascade');

            // Direccionamiento Huawei: frame/slot/port. El frame casi siempre es 0.
            $table->unsignedSmallInteger('frame')->default(0);
            $table->unsignedSmallInteger('slot');

            $table->string('board_type')->nullable()->comment('Tipo de tarjeta reportado por la OLT, por ejemplo GPBD');
            $table->unsignedSmallInteger('port_count')->default(0);
            $table->enum('status', ['normal', 'failed', 'offline', 'unknown'])->default('unknown');
            $table->timestamps();

            // Una tarjeta por ranura física
            $table->unique(['olt_id', 'frame', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olt_boards');
    }
};
