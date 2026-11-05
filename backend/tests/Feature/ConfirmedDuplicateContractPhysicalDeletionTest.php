<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\Installation;
use Modules\Contract\Services\DuplicateContractRepairService;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

class ConfirmedDuplicateContractPhysicalDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $service = new Service([
            'name' => 'Internet',
            'description' => 'Servicio de prueba',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);
        $service->id = 1;
        $service->save();

        $plan = new Plan([
            'service_id' => 1,
            'name' => 'Plan 65',
            'price' => 65,
            'upload_speed' => 50,
            'download_speed' => 100,
            'active' => true,
        ]);
        $plan->id = 1;
        $plan->save();
    }

    public function test_physically_deletes_each_confirmed_duplicate_and_preserves_two_month_debt(): void
    {
        $this->seedLeonidas();
        $this->seedDelia();
        $service = app(DuplicateContractRepairService::class);

        foreach ([
            DuplicateContractRepairService::LEONIDAS_37_38 => [37, 38, [6003, 6976], [6004, 6977, 9038]],
            DuplicateContractRepairService::DELIA_159_160 => [159, 160, [7304, 8081], [7305, 8082, 9160]],
        ] as $key => [$keeperId, $duplicateId, $keeperInvoices, $duplicateInvoices]) {
            $result = $service->physicallyDeleteConfirmedDuplicate($key, null, '127.0.0.1');

            $this->assertFalse($result['skipped']);
            $this->assertDatabaseHas('contracts', ['id' => $keeperId, 'status' => 'active']);
            $this->assertDatabaseMissing('contracts', ['id' => $duplicateId]);
            foreach ($keeperInvoices as $invoiceId) {
                $this->assertDatabaseHas('invoices', ['id' => $invoiceId, 'contract_id' => $keeperId, 'status' => 'pending']);
            }
            foreach ($duplicateInvoices as $invoiceId) {
                $this->assertDatabaseMissing('invoices', ['id' => $invoiceId]);
            }
            $this->assertDatabaseMissing('installations', ['contract_id' => $duplicateId]);
            $this->assertSame(130.0, round((float) Invoice::whereIn('id', $keeperInvoices)->get()->sum('pending_amount'), 2));

            $second = $service->physicallyDeleteConfirmedDuplicate($key, null, '127.0.0.1');
            $this->assertTrue($second['skipped']);
        }

        $this->assertSame(2, \Modules\Core\Entities\AuditLog::where('action_type', 'duplicate_contract_physically_deleted')->count());
    }

    public function test_blocks_physical_deletion_when_any_related_payment_exists(): void
    {
        $this->seedLeonidas();
        Payment::create([
            'invoice_id' => 6004,
            'amount' => 1,
            'payment_date' => '2026-06-30',
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        try {
            app(DuplicateContractRepairService::class)->physicallyDeleteConfirmedDuplicate(
                DuplicateContractRepairService::LEONIDAS_37_38,
                null,
                '127.0.0.1'
            );
            $this->fail('La eliminación debía bloquearse por el pago relacionado.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('pagos o notas de crédito', $exception->getMessage());
        }

        $this->assertDatabaseHas('contracts', ['id' => 38, 'status' => 'active']);
        $this->assertDatabaseHas('invoices', ['id' => 6004, 'status' => 'pending']);
        $this->assertDatabaseMissing('audit_logs', ['action_type' => 'duplicate_contract_physically_deleted']);
    }

    public function test_preview_marks_both_confirmed_cases_ready_without_writes(): void
    {
        $this->seedLeonidas();
        $this->seedDelia();

        $cases = app(DuplicateContractRepairService::class)->previewConfirmedPhysicalDeletionCases();

        $this->assertSame('ready', $cases->firstWhere('key', DuplicateContractRepairService::LEONIDAS_37_38)['status']);
        $this->assertSame('ready', $cases->firstWhere('key', DuplicateContractRepairService::DELIA_159_160)['status']);
        $this->assertDatabaseHas('contracts', ['id' => 38, 'status' => 'active']);
        $this->assertDatabaseHas('contracts', ['id' => 160, 'status' => 'active']);
    }

    private function seedLeonidas(): void
    {
        $this->seedPair(
            37,
            38,
            37,
            'Leónidas',
            'Cárdenas Peña',
            [6003, 6976],
            [6004, 6977],
            ['INV-000063', 'INV-000976'],
            ['INV-000064', 'INV-000977']
        );
    }

    private function seedDelia(): void
    {
        $this->seedPair(
            159,
            160,
            159,
            'Delia',
            'Paucar Suca',
            [7304, 8081],
            [7305, 8082],
            ['INV-000304', 'INV-001081'],
            ['INV-000305', 'INV-001082']
        );
    }

    private function seedPair(
        int $keeperId,
        int $duplicateId,
        int $customerId,
        string $firstName,
        string $lastName,
        array $keeperInvoiceIds,
        array $duplicateInvoiceIds,
        array $keeperNumbers,
        array $duplicateNumbers
    ): void {
        $customer = new Customer([
            'customer_type' => 'individual',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'identity_document' => 'DOC'.$customerId,
            'active' => true,
        ]);
        $customer->id = $customerId;
        $customer->save();

        $this->createContract($keeperId, $customerId, '2026-06-01 10:00:00');
        $this->createContract($duplicateId, $customerId, '2026-06-01 10:00:01');
        $this->createInstallation($keeperId);
        $this->createInstallation($duplicateId);
        $this->createInvoice($keeperInvoiceIds[0], $keeperId, $keeperNumbers[0], '01/06/2026 - 30/06/2026 (prorrateado)', 'manual', '2026-06-01');
        $this->createInvoice($duplicateInvoiceIds[0], $duplicateId, $duplicateNumbers[0], '01/06/2026 - 30/06/2026 (prorrateado)', 'manual', '2026-06-01');
        $this->createInvoice($keeperInvoiceIds[1], $keeperId, $keeperNumbers[1], 'julio 2026', 'automatic', '2026-07-01');
        $this->createInvoice($duplicateInvoiceIds[1], $duplicateId, $duplicateNumbers[1], 'julio 2026', 'automatic', '2026-07-01');
        $this->createInvoice(9000 + $duplicateId, $duplicateId, 'INV-EXTRA-'.$duplicateId, 'mayo 2026', 'manual', '2026-05-01');
    }

    private function createContract(int $id, int $customerId, string $createdAt): void
    {
        $contract = new Contract([
            'customer_id' => $customerId,
            'plan_id' => 1,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'final_price' => 65,
            'notes' => 'Registro histórico duplicado.',
        ]);
        $contract->id = $id;
        $contract->created_at = $createdAt;
        $contract->updated_at = $createdAt;
        $contract->save();
    }

    private function createInstallation(int $contractId): void
    {
        $installation = new Installation([
            'contract_id' => $contractId,
            'scheduled_date' => '2026-01-01',
            'completed_date' => '2026-01-01',
            'status' => 'completed',
            'notes' => 'Instalación histórica.',
        ]);
        $installation->id = $contractId;
        $installation->save();
    }

    private function createInvoice(int $id, int $contractId, string $number, string $period, string $generation, string $issueDate): void
    {
        $invoice = new Invoice([
            'contract_id' => $contractId,
            'invoice_number' => $number,
            'amount' => 65,
            'taxes' => 0,
            'total_amount' => 65,
            'issue_date' => $issueDate,
            'due_date' => substr($issueDate, 0, 7).'-30',
            'status' => 'pending',
            'document_type' => 'invoice',
            'generation_type' => $generation,
            'billing_period' => $period,
            'sent' => false,
        ]);
        $invoice->id = $id;
        $invoice->save();
    }
}
