<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'deactivated_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->timestamp('deactivated_at')->nullable()->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'deactivated_at')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('deactivated_at');
            });
        }
    }
};
