<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreignId('onboarding_order_id')
                ->nullable()
                ->after('contract_id')
                ->constrained('onboarding_orders')
                ->nullOnDelete();
            $table->foreignId('installation_id')
                ->nullable()
                ->after('onboarding_order_id')
                ->constrained('installations')
                ->nullOnDelete();
            $table->json('activation_data')->nullable()->after('response_data');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE service_requests MODIFY type ENUM('router_reboot','connection_issue','slow_speed','no_internet','configuration_change','technical_visit','equipment_replacement','equipment_activation','other') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE service_requests MODIFY type ENUM('router_reboot','connection_issue','slow_speed','no_internet','configuration_change','technical_visit','equipment_replacement','other') NOT NULL");
        }

        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['onboarding_order_id']);
            $table->dropForeign(['installation_id']);
            $table->dropColumn(['onboarding_order_id', 'installation_id', 'activation_data']);
        });
    }
};
