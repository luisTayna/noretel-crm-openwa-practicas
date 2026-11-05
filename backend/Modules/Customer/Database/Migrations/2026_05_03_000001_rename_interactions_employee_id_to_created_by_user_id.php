<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('interactions')) {
            return;
        }

        if (Schema::hasColumn('interactions', 'created_by_user_id')) {
            return;
        }

        if (Schema::hasColumn('interactions', 'employee_id')) {
            $this->dropForeignIfExists('interactions', 'employee_id');

            DB::statement('ALTER TABLE `interactions` CHANGE `employee_id` `created_by_user_id` BIGINT UNSIGNED NULL');

            $this->addUsersForeignKey('created_by_user_id');

            return;
        }

        Schema::table('interactions', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        if (!Schema::hasTable('interactions') || !Schema::hasColumn('interactions', 'created_by_user_id')) {
            return;
        }

        if (Schema::hasColumn('interactions', 'employee_id')) {
            return;
        }

        $this->dropForeignIfExists('interactions', 'created_by_user_id');

        DB::statement('ALTER TABLE `interactions` CHANGE `created_by_user_id` `employee_id` BIGINT UNSIGNED NULL');

        Schema::table('interactions', function (Blueprint $table) {
            $table->foreign('employee_id')
                ->references('id')
                ->on('users');
        });
    }

    private function addUsersForeignKey(string $column): void
    {
        Schema::table('interactions', function (Blueprint $table) use ($column) {
            $table->foreign($column)
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $constraint = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->value('CONSTRAINT_NAME');

        if ($constraint) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
