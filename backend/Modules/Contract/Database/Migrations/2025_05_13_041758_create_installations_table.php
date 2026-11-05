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
        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('technician_id')->nullable()->constrained('employees');
            $table->foreignId('route_id')->nullable()->constrained('routes');
            $table->timestamp('scheduled_date');
            $table->timestamp('completed_date')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->string('customer_signature')->nullable();
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
        Schema::dropIfExists('installations');
    }
};