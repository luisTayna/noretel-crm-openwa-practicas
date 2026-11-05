<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contracts', 'suspension_reason')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->string('suspension_reason')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contracts', 'suspension_reason')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('suspension_reason');
            });
        }
    }
};
