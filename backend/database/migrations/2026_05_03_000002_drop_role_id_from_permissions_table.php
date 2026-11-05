<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('permissions', 'role_id')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('role_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('permissions', 'role_id')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id')->nullable()->after('id');
            });
        }
    }
};
