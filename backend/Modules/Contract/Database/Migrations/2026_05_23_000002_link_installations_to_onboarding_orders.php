<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installations', function (Blueprint $table) {
            $table->foreignId('onboarding_order_id')
                ->nullable()
                ->after('id')
                ->constrained('onboarding_orders')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('installations', function (Blueprint $table) {
                $table->dropForeign(['contract_id']);
            });

            DB::statement('ALTER TABLE installations MODIFY contract_id BIGINT UNSIGNED NULL');

            Schema::table('installations', function (Blueprint $table) {
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('installations', function (Blueprint $table) {
                $table->dropForeign(['contract_id']);
            });

            DB::statement('ALTER TABLE installations MODIFY contract_id BIGINT UNSIGNED NOT NULL');

            Schema::table('installations', function (Blueprint $table) {
                $table->foreign('contract_id')->references('id')->on('contracts');
            });
        }

        Schema::table('installations', function (Blueprint $table) {
            $table->dropForeign(['onboarding_order_id']);
            $table->dropColumn('onboarding_order_id');
        });
    }
};
