<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('addresses', 'location_name')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('location_name')->nullable()->after('address_type');
            });
        }

        if (!Schema::hasColumn('addresses', 'reference')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('reference')->nullable()->after('postal_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('addresses', 'reference')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('reference');
            });
        }

        if (Schema::hasColumn('addresses', 'location_name')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('location_name');
            });
        }
    }
};
