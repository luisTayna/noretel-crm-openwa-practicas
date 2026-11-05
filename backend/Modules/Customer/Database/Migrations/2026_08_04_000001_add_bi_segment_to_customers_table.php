<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'bi_segment')) {
                $table->string('bi_segment', 50)->nullable()->after('segment')->index();
            }
            if (!Schema::hasColumn('customers', 'bi_score')) {
                $table->decimal('bi_score', 5, 2)->nullable()->after('bi_segment');
            }
            if (!Schema::hasColumn('customers', 'bi_updated_at')) {
                $table->timestamp('bi_updated_at')->nullable()->after('bi_score');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['bi_segment', 'bi_score', 'bi_updated_at']);
        });
    }
};
