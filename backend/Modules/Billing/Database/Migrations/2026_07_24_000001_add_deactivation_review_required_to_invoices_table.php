<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'deactivation_review_required')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->boolean('deactivation_review_required')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'deactivation_review_required')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('deactivation_review_required');
            });
        }
    }
};
