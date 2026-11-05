<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_reminders', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_reminders', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('invoice_id')
                    ->constrained('customers')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('payment_reminders', 'target_phone')) {
                $table->string('target_phone', 40)->nullable()->after('channel');
            }

            if (! Schema::hasColumn('payment_reminders', 'reminder_key')) {
                $table->string('reminder_key')->nullable()->unique()->after('type');
            }

            if (! Schema::hasColumn('payment_reminders', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->after('reminder_date');
            }

            if (! Schema::hasColumn('payment_reminders', 'batch_id')) {
                $table->uuid('batch_id')->nullable()->after('target_phone');
            }

            if (! Schema::hasColumn('payment_reminders', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('batch_id');
            }

            if (! Schema::hasColumn('payment_reminders', 'openwa_response')) {
                $table->json('openwa_response')->nullable()->after('attempts');
            }

            if (! Schema::hasColumn('payment_reminders', 'error_message')) {
                $table->text('error_message')->nullable()->after('openwa_response');
            }

            if (! Schema::hasColumn('payment_reminders', 'metadata')) {
                $table->json('metadata')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_reminders', function (Blueprint $table) {
            if (Schema::hasColumn('payment_reminders', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }

            if (Schema::hasColumn('payment_reminders', 'reminder_key')) {
                $table->dropUnique(['reminder_key']);
                $table->dropColumn('reminder_key');
            }

            foreach ([
                'metadata',
                'error_message',
                'openwa_response',
                'attempts',
                'batch_id',
                'scheduled_for',
                'target_phone',
            ] as $column) {
                if (Schema::hasColumn('payment_reminders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
