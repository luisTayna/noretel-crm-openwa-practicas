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

class DuplicateContractRepairServiceTest extends TestCase
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

        $customer = new Customer([
            'customer_type' => 'individual',
            'first_name' => 'Gregorio',
            'last_name' => 'Casilla Oquinaya',
            'identity_document' => '12345678',
            'active' => true,
        ]);
        $customer->id = 122;
        $customer->save();

        $this->createContract(253, '2026-06-26 14:31:50');
        $this->createContract(254, '2026-06-26 14:31:51');
        $this->createInstallation(253);
        $this->createInstallation(254);
        $this->createInvoice(505, 253, 'INV-000501', '01/06/2026 - 30/06/2026 (prorrateado)', 'manual', 'void', 'Recibo anulado. Motivo: doble factura.');
        $this->createInvoice(506, 254, 'INV-000502', 'junio 2026', 'manual', 'pending');
        $this->createInvoice(1190, 253, 'INV-001150', 'julio 2026', 'automatic', 'pending');
        $this->createInvoice(1191, 254, 'INV-001151', 'July 2026', 'automatic', 'pending');
    }

    public function test_repairs_gregorio_without_deleting_or_modifying_payments_and_is_idempotent(): void
    {
        $service = app(DuplicateContractRepairService::class);
        $paymentCount = Payment::count();
        $result = $service->repairGregorio(null, '127.0.0.1');

        $this->assertFalse($result['skipped']);
        $this->assertSame($paymentCount, Payment::count());
        $this->assertDatabaseHas('contracts', ['id' => 253, 'status' => 'active']);
        $this->assertDatabaseHas('contracts', ['id' => 254, 'status' => 'cancelled']);
        $this->assertDatabaseHas('installations', ['id' => 253, 'status' => 'completed']);
        $this->assertDatabaseHas('installations', ['id' => 254, 'status' => 'completed']);
        $this->assertDatabaseHas('invoices', ['id' => 505, 'status' => 'pending']);
        $this->assertDatabaseHas('invoices', ['id' => 506, 'status' => 'void']);
        $this->assertDatabaseHas('invoices', ['id' => 1190, 'status' => 'pending']);
        $this->assertDatabaseHas('invoices', ['id' => 1191, 'status' => 'void']);
        $this->assertSame(130.0, round((float) Invoice::where('contract_id', 253)->where('status', 'pending')->get()->sum('pending_amount'), 2));
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'duplicate_contract_repaired', 'module' => 'contracts']);

        $second = $service->repairGregorio(null, '127.0.0.1');
        $this->assertTrue($second['skipped']);
        $this->assertSame(1, \Modules\Core\Entities\AuditLog::where('action_type', 'duplicate_contract_repaired')->count());
    }

    public function test_aborts_everything_if_any_invoice_has_a_payment(): void
    {
        Payment::create([
            'invoice_id' => 506,
            'amount' => 10,
            'payment_date' => '2026-06-30',
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        try {
            app(DuplicateContractRepairService::class)->repairGregorio(null, '127.0.0.1');
            $this->fail('La reparación debía bloquearse por el pago encontrado.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Aparecieron pagos', $exception->getMessage());
        }

        $this->assertDatabaseHas('contracts', ['id' => 254, 'status' => 'active']);
        $this->assertDatabaseHas('invoices', ['id' => 505, 'status' => 'void']);
        $this->assertDatabaseHas('invoices', ['id' => 506, 'status' => 'pending']);
        $this->assertDatabaseMissing('audit_logs', ['action_type' => 'duplicate_contract_repaired']);
    }

    public function test_global_audit_finds_gregorio_as_low_risk_without_modifying_data(): void
    {
        $candidates = app(DuplicateContractRepairService::class)->auditCandidates();
        $candidate = $candidates->firstWhere('duplicate_contract_id', 254);

        $this->assertNotNull($candidate);
        $this->assertSame(253, $candidate['keeper_contract_id']);
        $this->assertSame('low', $candidate['risk']);
        $this->assertTrue($candidate['automatic_available']);
        $this->assertSame(['2026-06', '2026-07'], $candidate['overlap_periods']);
        $this->assertDatabaseHas('contracts', ['id' => 254, 'status' => 'active']);
        $this->assertDatabaseHas('invoices', ['id' => 505, 'status' => 'void']);
    }

    public function test_physically_deletes_cancelled_gregorio_duplicate_after_preserving_real_debt(): void
    {
        $service = app(DuplicateContractRepairService::class);
        $service->repairGregorio(null, '127.0.0.1');

        $result = $service->physicallyDeleteConfirmedDuplicate(
            DuplicateContractRepairService::GREGORIO_253_254_PHYSICAL,
            null,
            '127.0.0.1'
        );

        $this->assertFalse($result['skipped']);
        $this->assertDatabaseHas('contracts', ['id' => 253, 'status' => 'active']);
        $this->assertDatabaseMissing('contracts', ['id' => 254]);
        $this->assertDatabaseHas('invoices', ['id' => 505, 'status' => 'pending']);
        $this->assertDatabaseHas('invoices', ['id' => 1190, 'status' => 'pending']);
        $this->assertDatabaseMissing('invoices', ['id' => 506]);
        $this->assertDatabaseMissing('invoices', ['id' => 1191]);
        $this->assertDatabaseMissing('installations', ['contract_id' => 254]);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'duplicate_contract_physically_deleted']);

        $second = $service->physicallyDeleteConfirmedDuplicate(
            DuplicateContractRepairService::GREGORIO_253_254_PHYSICAL,
            null,
            '127.0.0.1'
        );
        $this->assertTrue($second['skipped']);
    }

    private function createContract(int $id, string $createdAt): void
    {
        $contract = new Contract([
            'customer_id' => 122,
            'plan_id' => 1,
            'start_date' => '2026-01-16',
            'status' => 'active',
            'final_price' => 65,
            'notes' => 'Registro histórico: cliente ya instalado.',
        ]);
        $contract->id = $id;
        $contract->created_at = $createdAt;
        $contract->updated_at = $createdAt;
        $contract->save();
    }

    private function createInstallation(int $id): void
    {
        $installation = new Installation([
            'contract_id' => $id,
            'scheduled_date' => '2026-01-16',
            'completed_date' => '2026-01-16',
            'status' => 'completed',
            'notes' => 'Instalación histórica.',
        ]);
        $installation->id = $id;
        $installation->save();
    }

    private function createInvoice(
        int $id,
        int $contractId,
        string $number,
        string $period,
        string $generation,
        string $status,
        ?string $notes = null
    ): void {
        $invoice = new Invoice([
            'contract_id' => $contractId,
            'invoice_number' => $number,
            'amount' => 65,
            'taxes' => 0,
            'total_amount' => 65,
            'issue_date' => str_contains(strtolower($period), 'jul') ? '2026-07-01' : '2026-06-01',
            'due_date' => str_contains(strtolower($period), 'jul') ? '2026-07-30' : '2026-06-30',
            'status' => $status,
            'document_type' => 'invoice',
            'generation_type' => $generation,
            'billing_period' => $period,
            'notes' => $notes,
            'sent' => false,
        ]);
        $invoice->id = $id;
        $invoice->save();
    }
}
