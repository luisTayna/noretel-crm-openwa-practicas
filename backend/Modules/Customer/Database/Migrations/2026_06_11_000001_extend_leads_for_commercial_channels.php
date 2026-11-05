<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'phone')) {
                $table->string('phone', 40)->nullable()->after('contact')->index();
            }

            if (! Schema::hasColumn('leads', 'email')) {
                $table->string('email')->nullable()->after('phone')->index();
            }

            if (! Schema::hasColumn('leads', 'message')) {
                $table->text('message')->nullable()->after('potential_value');
            }

            if (! Schema::hasColumn('leads', 'interest')) {
                $table->string('interest', 80)->nullable()->after('message')->index();
            }

            if (! Schema::hasColumn('leads', 'metadata')) {
                $table->json('metadata')->nullable()->after('interest');
            }

            if (! Schema::hasColumn('leads', 'last_contacted_at')) {
                $table->timestamp('last_contacted_at')->nullable()->after('metadata')->index();
            }

            if (! Schema::hasColumn('leads', 'assigned_to_user_id')) {
                $table->foreignId('assigned_to_user_id')
                    ->nullable()
                    ->after('last_contacted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'assigned_to_user_id')) {
                $table->dropConstrainedForeignId('assigned_to_user_id');
            }

            foreach ([
                'last_contacted_at',
                'metadata',
                'interest',
                'message',
                'email',
                'phone',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
