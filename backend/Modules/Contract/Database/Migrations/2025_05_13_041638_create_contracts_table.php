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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('plan_id')->constrained('plans');
            $table->unsignedBigInteger('node_id')->nullable();
            // $table->foreign('node_id')->references('id')->on('nodes');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->string('status')->default('pending_installation');
            $table->decimal('final_price', 10, 2);
            $table->string('assigned_ip')->nullable();
            $table->string('vlan')->nullable();
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('contracts');
    }
};
