<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yape_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('intent_number')->unique();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('client_evidence_id')->nullable();
            $table->foreignId('receiver_event_id')->nullable();
            $table->decimal('expected_amount', 10, 2);
            $table->string('payment_mode')->default('single');
            $table->unsignedSmallInteger('months_to_pay')->nullable();
            $table->string('whatsapp_phone')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('status')->default('waiting_client_evidence');
            $table->string('operation_code')->nullable();
            $table->string('receiver_account_name')->nullable();
            $table->string('receiver_account_phone')->nullable();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->json('validation_result')->nullable();
            $table->json('source_payload')->nullable();
            $table->text('observed_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'status']);
            $table->index(['operation_code', 'status']);
            $table->index(['whatsapp_phone', 'expected_amount']);
        });

        Schema::create('yape_client_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yape_payment_intent_id')->nullable()->constrained('yape_payment_intents')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('whatsapp_phone')->nullable();
            $table->string('source_message_id')->nullable()->unique();
            $table->string('source_chat_name')->nullable();
            $table->string('image_path');
            $table->longText('raw_text')->nullable();
            $table->decimal('parsed_amount', 10, 2)->nullable();
            $table->string('operation_code')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_phone')->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('status')->default('parsed');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['operation_code', 'parsed_amount']);
            $table->index(['whatsapp_phone', 'parsed_amount']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('yape_receiver_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('yape_payment_intent_id')->nullable()->constrained('yape_payment_intents')->nullOnDelete();
            $table->string('device_serial')->nullable();
            $table->string('screenshot_path');
            $table->longText('raw_text')->nullable();
            $table->decimal('parsed_amount', 10, 2)->nullable();
            $table->string('operation_code')->nullable()->unique();
            $table->timestamp('occurred_at')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->string('status')->default('parsed');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['parsed_amount', 'occurred_at']);
            $table->index(['sender_phone', 'parsed_amount']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yape_receiver_events');
        Schema::dropIfExists('yape_client_evidences');
        Schema::dropIfExists('yape_payment_intents');
    }
};
