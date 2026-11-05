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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('description');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('amount', 10, 2);
            $table->integer('order')->default(1);
            $table->string('item_type')->nullable(); // service, plan, additional_service, one_time, etc.
            $table->foreignId('service_id')->nullable(); // Referencia opcional al servicio o plan
            $table->string('period_start')->nullable(); // Fecha inicio del período facturado (si aplica)
            $table->string('period_end')->nullable(); // Fecha fin del período facturado (si aplica)
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
        Schema::dropIfExists('invoice_items');
    }
};
