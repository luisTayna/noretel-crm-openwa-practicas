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
        Schema::create('installed_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_id')->constrained('installations')->onDelete('cascade');
            $table->foreignId('equipment_id')->nullable();
            $table->string('serial')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('status')->default('assigned');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('installed_equipment');
    }
};
