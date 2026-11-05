<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite indexes for the invoice/payment query shapes that are
 * actually executed today (status + due_date / payment_date filters used by
 * the collections dashboard, aging reports and payment reminders, plus the
 * invoice_id+status lookup used when totaling paid amounts per invoice).
 * No columns, tables or behavior are changed.
 *
 * Note: an invoices(contract_id, status) composite was considered (it
 * matches Invoice::where('contract_id', ...)->whereIn('status', ...) in
 * ContractController::update) but was dropped because it changes the
 * unordered row-return order of Invoice::whereIn('contract_id', ...)->get()
 * queries that have no explicit orderBy(), which changes observable output
 * (e.g. DuplicateContractRepairService::compareContractInvoices()'s
 * overlap_periods order). Adding an index must not change behavior, so it
 * was left out; `contract_id` already has its FK index for equality lookups.
 *
 * Kept in sync with database/manual/2026_09_27_performance_indexes.sql for
 * production, which is applied manually through phpMyAdmin.
 */
return new class extends Migration
{
    /**
     * table => [index_name => [columns]]
     */
    private array $indexes = [
        'invoices' => [
            'idx_invoices_status_due_date' => ['status', 'due_date'],
        ],
        'payments' => [
            'idx_payments_status_payment_date' => ['status', 'payment_date'],
            'idx_payments_invoice_id_status' => ['invoice_id', 'status'],
        ],
    ];

    public function up(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        foreach ($this->indexes as $table => $tableIndexes) {
            foreach ($tableIndexes as $indexName => $columns) {
                if ($isMysql) {
                    if ($this->mysqlIndexExists($table, $indexName)) {
                        continue;
                    }

                    Schema::table($table, function ($blueprint) use ($columns, $indexName) {
                        $blueprint->index($columns, $indexName);
                    });
                } else {
                    // sqlite (used by the test suite) has no SHOW INDEX
                    // equivalent; creating the index directly is safe here.
                    Schema::table($table, function ($blueprint) use ($columns, $indexName) {
                        $blueprint->index($columns, $indexName);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        foreach ($this->indexes as $table => $tableIndexes) {
            foreach (array_keys($tableIndexes) as $indexName) {
                if ($isMysql && ! $this->mysqlIndexExists($table, $indexName)) {
                    continue;
                }

                Schema::table($table, function ($blueprint) use ($indexName) {
                    $blueprint->dropIndex($indexName);
                });
            }
        }
    }

    private function mysqlIndexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return ! empty($result);
    }
};
