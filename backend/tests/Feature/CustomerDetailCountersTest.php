<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Http\Controllers\CustomerController;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

class CustomerDetailCountersTest extends TestCase
{
    use RefreshDatabase;

    public function test_gregorio_detail_counts_only_active_contracts_and_separates_void_invoices(): void
    {
        $service = Service::create([
            'name' => 'Internet',
            'description' => 'Servicio de prueba',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);
        $plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan 65',
            'price' => 65,
            'upload_speed' => 50,
            'download_speed' => 100,
            'active' => true,
        ]);
        $customer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Gregorio',
            'last_name' => 'Casilla Oquinaya',
            'identity_document' => '12345678',
            'active' => true,
        ]);
        $active = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-01-16',
            'status' => 'active',
            'final_price' => 65,
        ]);
        $cancelled = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-01-16',
            'status' => 'cancelled',
            'final_price' => 65,
        ]);

        $this->createInvoice($active->id, 'INV-000501', 'pending');
        $this->createInvoice($active->id, 'INV-001150', 'pending');
        $this->createInvoice($cancelled->id, 'INV-000502', 'void');
        $this->createInvoice($cancelled->id, 'INV-001151', 'void');

        $view = app(CustomerController::class)->show($customer->id);
        $data = $view->getData();

        $this->assertCount(1, $data['activeContracts']);
        $this->assertSame('active', $data['activeContract']->status);
        $this->assertSame(1, $data['billingSummary']['active_contract_count']);
        $this->assertSame(2, $data['billingSummary']['registered_contract_count']);
        $this->assertSame(1, $data['billingSummary']['historical_contract_count']);
        $this->assertSame(2, $data['billingSummary']['active_invoice_count']);
        $this->assertSame(2, $data['billingSummary']['void_invoice_count']);
        $this->assertSame(4, $data['billingSummary']['invoice_count']);
    }

    private function createInvoice(int $contractId, string $number, string $status): void
    {
        Invoice::create([
            'contract_id' => $contractId,
            'invoice_number' => $number,
            'amount' => 65,
            'taxes' => 0,
            'total_amount' => 65,
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-30',
            'status' => $status,
            'document_type' => 'invoice',
            'generation_type' => 'automatic',
            'billing_period' => 'julio 2026',
            'sent' => false,
        ]);
    }
}
