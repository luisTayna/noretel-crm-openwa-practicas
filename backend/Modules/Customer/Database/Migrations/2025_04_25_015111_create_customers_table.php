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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // Usuario asociado (si tiene cuenta)
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('customer_type');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('identity_document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->integer('credit_score')->nullable();
            $table->string('contact_preferences')->nullable();
            $table->string('segment')->nullable();
            $table->timestamp('registration_date')->nullable();
            $table->boolean('active')->default(true);
            $table->enum('customer_status', [
                'lead',           // Cliente potencial (solo pregunta)
                'prospect',       // Cliente interesado (en proceso de compra)
                'new',            // Cliente nuevo (< 3 meses)
                'active',         // Cliente oficial (> 3 meses)
                'inactive',       // Cliente inactivo (suspendido temporalmente)
                'former',         // Cliente antiguo (ya no está)
                'blacklist'       // Cliente en lista negra
            ])->default('lead');


            // Fechas importantes
            $table->timestamp('first_purchase_at')->nullable();
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamp('churned_at')->nullable(); // Fecha de cancelación

            // Métricas
            $table->decimal('lifetime_value', 10, 2)->default(0); // Valor total gastado
            $table->integer('contract_count')->default(0); // Número de contratos
            $table->integer('months_as_customer')->default(0); // Meses como cliente

            // Marketing
            $table->string('acquisition_channel')->nullable(); // web, referral, phone, etc.
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();

            // Índices
            $table->index('customer_status');
            $table->index('user_id');
            $table->index('first_purchase_at');

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
        Schema::dropIfExists('customers');
    }
};
