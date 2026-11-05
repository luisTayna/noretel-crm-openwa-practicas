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
        Schema::create('slas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('service_level');
            $table->integer('response_time');
            $table->integer('resolution_time');
            $table->json('penalties')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Pivot table for contracts and SLAs
        Schema::create('contract_sla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('sla_id')->constrained('slas');
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
        Schema::dropIfExists('contract_sla');
        Schema::dropIfExists('slas');
    }
};