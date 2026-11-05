<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Services\MultiMonthPaymentSurgicalRepairService;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

class MultiMonthPaymentSurgicalRepairTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $service = Service::create([
            'name' => 'Internet',
            'description' => 'Test internet service',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);

        $this->plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan de prueba',
            'price' => 100,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_repairs_jose_without_creating_or_refunding_payments_and_is_idempotent(): void
    {
        $this->createContract(379, 65, 'José', 'Luis');
        $invoices = [
            826 => $this->createInvoice(379, 826, 'INV-000826', 'mayo 2026', 'manual', 'paid', 65),
            827 => $this->createInvoice(379, 827, 'INV-000827', 'junio 2026', 'manual', 'paid', 65),
            828 => $this->createInvoice(379, 828, 'INV-000828', 'June 2026', 'advance_payment', 'paid', 65),
            1254 => $this->createInvoice(379, 1254, 'INV-001254', 'julio 2026', 'automatic', 'pending', 65),
        ];
        $applied = $this->appliedInvoices([$invoices[826], $invoices[827], $invoices[828]], 65);
        $this->createGroupedPayment(294, $invoices[826], 65, 'jose-group', $applied);
        $this->createGroupedPayment(295, $invoices[827], 65, 'jose-group', $applied);
        $this->createGroupedPayment(296, $invoices[828], 65, 'jose-group', $applied);

        $service = app(MultiMonthPaymentSurgicalRepairService::class);
        $paymentCount = Payment::count();
        $result = $service->repair(MultiMonthPaymentSurgicalRepairService::JOSE_379, null, '127.0.0.1');

        $this->assertFalse($result['skipped']);
        $this->assertSame($paymentCount, Payment::count());
        $this->assertDatabaseHas('payments', ['id' => 296, 'invoice_id' => 1254, 'status' => 'completed']);
        $this->assertDatabaseHas('payments', ['id' => 294, 'invoice_id' => 826, 'status' => 'completed']);
        $this->assertDatabaseHas('payments', ['id' => 295, 'invoice_id' => 827, 'status' => 'completed']);
        $this->assertDatabaseHas('invoices', ['id' => 828, 'status' => 'void']);
        $this->assertDatabaseHas('invoices', ['id' => 1254, 'status' => 'paid']);
        $this->assertDatabaseHas('audit_logs', ['action_type' => 'multimonth_payment_reassigned', 'module' => 'payments']);
        $this->assertGroupMetadataPointsTo([294, 295, 296], 1254, 828);

        $second = $service->repair(MultiMonthPaymentSurgicalRepairService::JOSE_379, null, '127.0.0.1');
        $this->assertTrue($second['skipped']);
        $this->assertSame($paymentCount, Payment::count());
    }

    public function test_repairs_victoria_by_creating_one_august_invoice_and_is_idempotent(): void
    {
        $this->createContract(400, 100, 'Victoria', 'Prueba');
        $invoices = [
            859 => $this->createInvoice(400, 859, 'INV-000859', 'junio 2026', 'manual', 'paid', 100),
            860 => $this->createInvoice(400, 860, 'INV-000860', 'julio 2026', 'manual', 'paid', 100),
            861 => $this->createInvoice(400, 861, 'INV-000861', 'July 2026', 'advance_payment', 'paid', 100),
        ];
        $applied = $this->appliedInvoices([$invoices[859], $invoices[860], $invoices[861]], 100);
        $this->createGroupedPayment(314, $invoices[859], 100, 'victoria-group', $applied);
        $this->createGroupedPayment(315, $invoices[860], 100, 'victoria-group', $applied);
        $this->createGroupedPayment(316, $invoices[861], 100, 'victoria-group', $applied);

        $service = app(MultiMonthPaymentSurgicalRepairService::class);
        $paymentCount = Payment::count();
        $invoiceCount = Invoice::count();
        $result = $service->repair(MultiMonthPaymentSurgicalRepairService::VICTORIA_400, null, '127.0.0.1');
        $destination = Invoice::findOrFail($result['destination_invoice_id']);

        $this->assertFalse($result['skipped']);
        $this->assertSame('2026-08', app(\Modules\Billing\Services\PaymentService::class)->canonicalInvoicePeriodKey($destination));
        $this->assertSame('advance_payment', $destination->generation_type);
        $this->assertSame('paid', $destination->status);
        $this->assertSame(100.0, round((float) $destination->total_amount, 2));
        $this->assertSame($paymentCount, Payment::count());
        $this->assertSame($invoiceCount + 1, Invoice::count());
        $this->assertDatabaseHas('payments', ['id' => 316, 'invoice_id' => $destination->id, 'status' => 'completed']);
        $this->assertDatabaseHas('invoices', ['id' => 861, 'status' => 'void']);
        $this->assertGroupMetadataPointsTo([314, 315, 316], $destination->id, 861);

        $second = $service->repair(MultiMonthPaymentSurgicalRepairService::VICTORIA_400, null, '127.0.0.1');
        $this->assertTrue($second['skipped']);
        $this->assertSame($invoiceCount + 1, Invoice::count());
        $this->assertSame($paymentCount, Payment::count());
    }

    public function test_jose_stops_without_changes_when_destination_already_has_a_payment(): void
    {
        $this->createContract(379, 65, 'José', 'Luis');
        $invoices = [
            826 => $this->createInvoice(379, 826, 'INV-000826', 'mayo 2026', 'manual', 'paid', 65),
            827 => $this->createInvoice(379, 827, 'INV-000827', 'junio 2026', 'manual', 'paid', 65),
            828 => $this->createInvoice(379, 828, 'INV-000828', 'June 2026', 'advance_payment', 'paid', 65),
            1254 => $this->createInvoice(379, 1254, 'INV-001254', 'julio 2026', 'automatic', 'pending', 65),
        ];
        $applied = $this->appliedInvoices([$invoices[826], $invoices[827], $invoices[828]], 65);
        $this->createGroupedPayment(294, $invoices[826], 65, 'jose-group', $applied);
        $this->createGroupedPayment(295, $invoices[827], 65, 'jose-group', $applied);
        $this->createGroupedPayment(296, $invoices[828], 65, 'jose-group', $applied);
        $this->createGroupedPayment(999, $invoices[1254], 10, 'other-group', []);

        try {
            app(MultiMonthPaymentSurgicalRepairService::class)->repair(
                MultiMonthPaymentSurgicalRepairService::JOSE_379,
                null,
                '127.0.0.1'
            );
            $this->fail('La reparación debía detenerse por el pago existente en destino.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('sin pagos', $exception->getMessage());
        }

        $this->assertDatabaseHas('payments', ['id' => 296, 'invoice_id' => 828, 'status' => 'completed']);
        $this->assertDatabaseHas('invoices', ['id' => 828, 'status' => 'paid']);
        $this->assertDatabaseHas('invoices', ['id' => 1254, 'status' => 'pending']);
        $this->assertDatabaseMissing('audit_logs', ['action_type' => 'multimonth_payment_reassigned']);
    }

    public function test_victoria_stops_without_changes_when_august_already_exists(): void
    {
        $this->createContract(400, 100, 'Victoria', 'Prueba');
        $invoices = [
            859 => $this->createInvoice(400, 859, 'INV-000859', 'junio 2026', 'manual', 'paid', 100),
            860 => $this->createInvoice(400, 860, 'INV-000860', 'julio 2026', 'manual', 'paid', 100),
            861 => $this->createInvoice(400, 861, 'INV-000861', 'July 2026', 'advance_payment', 'paid', 100),
        ];
        $applied = $this->appliedInvoices([$invoices[859], $invoices[860], $invoices[861]], 100);
        $this->createGroupedPayment(314, $invoices[859], 100, 'victoria-group', $applied);
        $this->createGroupedPayment(315, $invoices[860], 100, 'victoria-group', $applied);
        $this->createGroupedPayment(316, $invoices[861], 100, 'victoria-group', $applied);
        $this->createInvoice(400, 1400, 'INV-001400', 'agosto 2026', 'automatic', 'pending', 100);
        $invoiceCount = Invoice::count();

        try {
            app(MultiMonthPaymentSurgicalRepairService::class)->repair(
                MultiMonthPaymentSurgicalRepairService::VICTORIA_400,
                null,
                '127.0.0.1'
            );
            $this->fail('La reparación debía detenerse por el período agosto existente.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Ya existe una factura canónica', $exception->getMessage());
        }

        $this->assertSame($invoiceCount, Invoice::count());
        $this->assertDatabaseHas('payments', ['id' => 316, 'invoice_id' => 861, 'status' => 'completed']);
        $this->assertDatabaseHas('invoices', ['id' => 861, 'status' => 'paid']);
        $this->assertDatabaseMissing('audit_logs', ['action_type' => 'multimonth_payment_reassigned']);
    }

    private function createContract(int $id, float $price, string $firstName, string $lastName): Contract
    {
        $customer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'identity_document' => 'DOC'.$id,
            'active' => true,
        ]);

        $contract = new Contract([
            'customer_id' => $customer->id,
            'plan_id' => $this->plan->id,
            'start_date' => '2026-05-01',
            'status' => 'active',
            'final_price' => $price,
        ]);
        $contract->id = $id;
        $contract->save();

        return $contract;
    }

    private function createInvoice(
        int $contractId,
        int $id,
        string $number,
        string $period,
        string $generationType,
        string $status,
        float $amount
    ): Invoice {
        $invoice = new Invoice([
            'contract_id' => $contractId,
            'invoice_number' => $number,
            'amount' => $amount,
            'taxes' => 0,
            'total_amount' => $amount,
            'issue_date' => str_contains(strtolower($period), 'mayo') ? '2026-05-01' : (str_contains(strtolower($period), 'agosto') ? '2026-08-01' : (str_contains(strtolower($period), 'jul') ? '2026-07-01' : '2026-06-01')),
            'due_date' => '2026-08-15',
            'status' => $status,
            'document_type' => 'invoice',
            'generation_type' => $generationType,
            'billing_period' => $period,
            'sent' => false,
        ]);
        $invoice->id = $id;
        $invoice->save();

        return $invoice;
    }

    private function createGroupedPayment(int $id, Invoice $invoice, float $amount, string $group, array $applied): Payment
    {
        $payment = new Payment([
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'payment_date' => '2026-05-30',
            'payment_method' => 'cash',
            'status' => 'completed',
            'reference' => 'TEST-'.$id,
            'payment_details' => [
                'payment_group_id' => $group,
                'months_requested' => 3,
                'applied_invoices' => $applied,
            ],
        ]);
        $payment->id = $id;
        $payment->save();

        return $payment;
    }

    private function appliedInvoices(array $invoices, float $amount): array
    {
        return collect($invoices)->map(fn (Invoice $invoice) => [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'billing_period' => $invoice->billing_period,
            'amount' => $amount,
        ])->values()->all();
    }

    private function assertGroupMetadataPointsTo(array $paymentIds, int $destinationId, int $sourceId): void
    {
        foreach (Payment::whereIn('id', $paymentIds)->get() as $payment) {
            $ids = collect(data_get($payment->payment_details, 'applied_invoices'))->pluck('invoice_id')->map(fn ($id) => (int) $id);
            $this->assertTrue($ids->contains($destinationId));
            $this->assertFalse($ids->contains($sourceId));
        }
    }
}
