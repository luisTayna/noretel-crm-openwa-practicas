<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yape_payment_intents', function (Blueprint $table) {
            if (! Schema::hasColumn('yape_payment_intents', 'security_code')) {
                $table->string('security_code', 20)->nullable()->after('operation_code');
                $table->index(['security_code', 'status'], 'ypi_security_status_idx');
            }
        });

        Schema::table('yape_client_evidences', function (Blueprint $table) {
            if (! Schema::hasColumn('yape_client_evidences', 'security_code')) {
                $table->string('security_code', 20)->nullable()->after('operation_code');
                $table->index(['security_code', 'parsed_amount'], 'yce_security_amount_idx');
            }
        });

        Schema::table('yape_receiver_events', function (Blueprint $table) {
            if (! Schema::hasColumn('yape_receiver_events', 'security_code')) {
                $table->string('security_code', 20)->nullable()->after('operation_code');
                $table->index(['security_code', 'parsed_amount'], 'yre_security_amount_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('yape_receiver_events', function (Blueprint $table) {
            if (Schema::hasColumn('yape_receiver_events', 'security_code')) {
                $table->dropIndex('yre_security_amount_idx');
                $table->dropColumn('security_code');
            }
        });

        Schema::table('yape_client_evidences', function (Blueprint $table) {
            if (Schema::hasColumn('yape_client_evidences', 'security_code')) {
                $table->dropIndex('yce_security_amount_idx');
                $table->dropColumn('security_code');
            }
        });

        Schema::table('yape_payment_intents', function (Blueprint $table) {
            if (Schema::hasColumn('yape_payment_intents', 'security_code')) {
                $table->dropIndex('ypi_security_status_idx');
                $table->dropColumn('security_code');
            }
        });
    }
};