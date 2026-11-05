<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceIndexesMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string[]}>
     */
    public static function expectedIndexesProvider(): array
    {
        return [
            'invoices status+due_date' => ['invoices', 'idx_invoices_status_due_date'],
            'payments status+payment_date' => ['payments', 'idx_payments_status_payment_date'],
            'payments invoice_id+status' => ['payments', 'idx_payments_invoice_id_status'],
        ];
    }

    /** @test */
    public function invoices_table_has_the_new_performance_index(): void
    {
        $indexNames = collect(DB::select("PRAGMA index_list('invoices')"))
            ->pluck('name')
            ->all();

        $this->assertContains('idx_invoices_status_due_date', $indexNames);
    }

    /** @test */
    public function payments_table_has_the_new_performance_indexes(): void
    {
        $indexNames = collect(DB::select("PRAGMA index_list('payments')"))
            ->pluck('name')
            ->all();

        $this->assertContains('idx_payments_status_payment_date', $indexNames);
        $this->assertContains('idx_payments_invoice_id_status', $indexNames);
    }

    /** @test */
    public function invoices_status_due_date_index_covers_the_expected_columns(): void
    {
        $columns = collect(DB::select("PRAGMA index_info('idx_invoices_status_due_date')"))
            ->pluck('name')
            ->all();

        $this->assertSame(['status', 'due_date'], $columns);
    }

    /** @test */
    public function payments_status_payment_date_index_covers_the_expected_columns(): void
    {
        $columns = collect(DB::select("PRAGMA index_info('idx_payments_status_payment_date')"))
            ->pluck('name')
            ->all();

        $this->assertSame(['status', 'payment_date'], $columns);
    }
}
