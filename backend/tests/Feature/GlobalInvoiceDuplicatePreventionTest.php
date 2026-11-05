<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Services\InvoicePeriodGuardService;
use Modules\Billing\Services\InvoiceGlobalAuditService;
use Modules\Billing\Services\InvoiceService;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Modules\Core\Entities\User;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

class GlobalInvoiceDuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private int $sequence = 900000;

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'username' => 'invoice-guard-admin',
            'email' => 'invoice-guard@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);

        $service = Service::create([
            'name' => 'Internet global guard',
            'description' => 'Test',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);
        $this->plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan guard',
            'price' => 65,
            'upload_speed' => 100,
            'download_speed' => 100,
            'active' => true,
        ]);
    }

    public function test_advance_paid_blocks_automatic_same_month(): void
    {
        $contract = $this->contract();
        $this->invoice($contract, 'July 2026', 'paid', 'advance_payment', '2026-07-01');

        $result = app(InvoiceService::class)->generateInvoiceForContract($contract->id, 'julio 2026', '2026-07-15');

        $this->assertTrue($result['skipped']);
        $this->assertSame(1, Invoice::where('contract_id', $contract->id)->count());
    }

    public function test_prorated_date_range_blocks_same_canonical_month(): void
    {
        $contract = $this->contract();
        $this->invoice($contract, '30/06/2026 - 30/07/2026 (prorrateado)', 'pending', 'manual', '2026-06-30');

        $result = app(InvoiceService::class)->generateInvoiceForContract($contract->id, 'July 2026', '2026-07-15');

        $this->assertTrue($result['skipped']);
    }

    public function test_void_invoice_does_not_block_august(): void
    {
        $contract = $this->contract();
        $this->invoice($contract, 'agosto 2026', 'void', 'manual', '2026-08-01');

        $result = app(InvoiceService::class)->generateInvoiceForContract($contract->id, 'August 2026', '2026-08-15');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['skipped'] ?? false);
        $this->assertSame(2, Invoice::where('contract_id', $contract->id)->count());
    }

    public function test_missing_september_is_created_once(): void
    {
        $contract = $this->contract();

        $first = app(InvoiceService::class)->generateInvoiceForContract($contract->id, 'septiembre 2026', '2026-09-15');
        $second = app(InvoiceService::class)->generateInvoiceForContract($contract->id, 'September 2026', '2026-09-15');

        $this->assertTrue($first['success']);
        $this->assertTrue($second['skipped']);
        $this->assertSame(1, Invoice::where('contract_id', $contract->id)->count());
    }

    public function test_two_contracts_of_same_customer_are_evaluated_separately(): void
    {
        $customer = $this->customer();
        $firstContract = $this->contract($customer);
        $secondContract = $this->contract($customer);
        $this->invoice($firstContract, 'octubre 2026', 'paid', 'manual', '2026-10-01');

        $result = app(InvoiceService::class)->generateInvoiceForContract($secondContract->id, 'October 2026', '2026-10-15');

        $this->assertTrue($result['success']);
        $this->assertSame(1, Invoice::where('contract_id', $firstContract->id)->count());
        $this->assertSame(1, Invoice::where('contract_id', $secondContract->id)->count());
    }

    public function test_cancelled_contract_is_not_billed(): void
    {
        $contract = $this->contract(status: 'cancelled');

        $result = app(InvoiceService::class)->generateInvoiceForContract($contract->id, 'noviembre 2026', '2026-11-15');

        $this->assertFalse($result['success']);
        $this->assertSame(0, Invoice::where('contract_id', $contract->id)->count());
    }

    public function test_december_and_january_are_distinct_across_year_boundary(): void
    {
        $guard = app(InvoicePeriodGuardService::class);

        $this->assertSame('2026-12', $guard->canonicalKey('diciembre 2026'));
        $this->assertSame('2027-01', $guard->canonicalKey('January 2027'));
        $this->assertNotSame($guard->canonicalKey('12/2026'), $guard->canonicalKey('2027-01'));
    }

    public function test_repeated_creation_equivalent_to_competing_requests_creates_only_one(): void
    {
        $contract = $this->contract();
        $guard = app(InvoicePeriodGuardService::class);
        $creator = fn (Contract $locked, string $key) => $this->invoice($locked, $key, 'pending', 'automatic', $key.'-15');

        $first = $guard->createForPeriod($contract->id, 'julio 2026', '2026-07-15', $creator);
        $second = $guard->createForPeriod($contract->id, 'July 2026', '2026-07-15', $creator);

        $this->assertTrue($first['created']);
        $this->assertTrue($second['skipped']);
        $this->assertSame(1, Invoice::where('contract_id', $contract->id)->count());
    }

    public function test_advance_and_cron_collision_keeps_one_valid_invoice(): void
    {
        $contract = $this->contract();
        $guard = app(InvoicePeriodGuardService::class);
        $guard->createForPeriod(
            $contract->id,
            '2026-08',
            '2026-08-01',
            fn (Contract $locked, string $key) => $this->invoice($locked, $key, 'paid', 'advance_payment', $key.'-01')
        );

        $cron = app(InvoiceService::class)->generateInvoiceForContract($contract->id, 'agosto 2026', '2026-08-15');

        $this->assertTrue($cron['skipped']);
        $this->assertSame(1, Invoice::where('contract_id', $contract->id)->count());
    }

    public function test_monthly_command_is_idempotent_when_executed_twice(): void
    {
        $contract = $this->contract();

        $this->artisan('billing:generate-monthly-invoices', ['--date' => '2026-09-15', '--force' => true])->assertExitCode(0);
        $this->artisan('billing:generate-monthly-invoices', ['--date' => '2026-09-15', '--force' => true])->assertExitCode(0);

        $this->assertSame(1, Invoice::where('contract_id', $contract->id)->count());
    }

    public function test_global_audit_detects_all_requested_categories_without_writes(): void
    {
        $customer = $this->customer();
        $first = $this->contract($customer);
        $second = $this->contract($customer);
        $cancelled = $this->contract(status: 'cancelled');

        $this->invoice($first, 'julio 2026', 'pending', 'manual', '2026-07-01');
        $this->invoice($first, 'July 2026', 'paid', 'advance_payment', '2026-07-02');
        $this->invoice($second, '2026-07', 'pending', 'automatic', '2026-07-15');
        $this->invoice($cancelled, 'agosto 2026', 'pending', 'automatic', '2026-08-15');
        $this->invoice($second, 'periodo legado desconocido', 'void', 'manual', '2026-09-15');
        $before = Invoice::count();

        $audit = app(InvoiceGlobalAuditService::class)->audit();

        $this->assertNotEmpty($audit['duplicateContractMonths']);
        $this->assertNotEmpty($audit['duplicateActiveContracts']);
        $this->assertNotEmpty($audit['automaticOnCancelled']);
        $this->assertNotEmpty($audit['crossContractCustomerMonths']);
        $this->assertNotEmpty($audit['unrecognized']);
        $this->assertSame($before, Invoice::count());
    }
    private function customer(): Customer
    {
        return Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Cliente',
            'last_name' => 'Guard '.$this->sequence++,
            'identity_document' => 'DOC'.$this->sequence,
            'active' => true,
        ]);
    }

    private function contract(?Customer $customer = null, string $status = 'active'): Contract
    {
        return Contract::create([
            'customer_id' => ($customer ?? $this->customer())->id,
            'plan_id' => $this->plan->id,
            'start_date' => '2026-01-01',
            'status' => $status,
            'final_price' => 65,
        ]);
    }

    private function invoice(Contract $contract, string $period, string $status, string $generationType, string $issueDate): Invoice
    {
        $number = 'INV-T'.$this->sequence++;

        return Invoice::create([
            'contract_id' => $contract->id,
            'invoice_number' => $number,
            'amount' => 65,
            'taxes' => 0,
            'total_amount' => 65,
            'issue_date' => $issueDate,
            'due_date' => substr($issueDate, 0, 7).'-28',
            'status' => $status,
            'document_type' => 'invoice',
            'generation_type' => $generationType,
            'billing_period' => $period,
            'sent' => false,
        ]);
    }
}