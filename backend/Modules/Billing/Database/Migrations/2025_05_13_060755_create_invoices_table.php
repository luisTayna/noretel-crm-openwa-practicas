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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('taxes', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->date('issue_date');
            $table->date('due_date');
            $table->enum('status', ['draft', 'pending', 'paid', 'partial', 'overdue', 'cancelled', 'void'])->default('draft');
            $table->string('document_type')->default('invoice');
            $table->text('notes')->nullable();
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('billing_name')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_document')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('services_detail')->nullable();
            $table->string('generation_type')->default('manual'); // manual, automatic, recurring
            $table->string('billing_period')->nullable(); // Para almacenar el período que cubre la factura (Ej: "Mayo 2023")
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
        Schema::dropIfExists('invoices');
    }
};
