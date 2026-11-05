<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('parameter');
            $table->text('value');
            $table->string('data_type');
            $table->boolean('editable')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Índice compuesto para búsquedas rápidas
            $table->unique(['module', 'parameter']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_configurations');
    }
};
