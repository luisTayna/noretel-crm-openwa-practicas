<?php

namespace Tests\Unit;

use Illuminate\Support\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Services\YapeReconciliationService;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Tests\TestCase;

class YapeMultiServicePaymentDecisionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('amount', 10, 2);
            $table->string('status');
        });
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('amount', 10, 2);
            $table->string('status');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('payments');

        parent::tearDown();
    }

    public function test_exact_yape_amount_can_cover_oldest_invoices_from_multiple_services_of_one_customer(): void
    {
        $customer = new Customer(['first_name' => 'Edwin', 'last_name' => 'Torres']);
        $customer->id = 419;

        $firstInvoice = $this->invoiceFor($customer, 501, 801, 250, '2026-07-10');
        $secondInvoice = $this->invoiceFor($customer, 502, 802, 100, '2026-07-11');
        $service = $this->decisionService(new Collection([$firstInvoice, $secondInvoice]));

        $decision = $service->decideForTest(
            new Collection([$customer]),
            $this->evidenceFor(350),
            '46924725'
        );

        $this->assertSame('oldest_invoices_sum_match', $decision['decision']);
        $this->assertSame([801, 802], $decision['invoices']->pluck('id')->all());
        $this->assertSame([501, 502], $decision['invoices']->pluck('contract_id')->all());
    }

    public function test_invoices_from_different_customer_records_are_not_automatically_combined(): void
    {
        $firstCustomer = new Customer(['first_name' => 'Persona', 'last_name' => 'Uno']);
        $firstCustomer->id = 1;
        $secondCustomer = new Customer(['first_name' => 'Persona', 'last_name' => 'Dos']);
        $secondCustomer->id = 2;

        $firstInvoice = $this->invoiceFor($firstCustomer, 601, 901, 250, '2026-07-10');
        $secondInvoice = $this->invoiceFor($secondCustomer, 602, 902, 100, '2026-07-11');
        $service = $this->decisionService(new Collection([$firstInvoice, $secondInvoice]));

        $decision = $service->decideForTest(
            new Collection([$firstCustomer, $secondCustomer]),
            $this->evidenceFor(350),
            '12345678'
        );

        $this->assertNotSame('oldest_invoices_sum_match', $decision['decision']);
    }

    private function invoiceFor(
        Customer $customer,
        int $contractId,
        int $invoiceId,
        float $pendingAmount,
        string $dueDate
    ): Invoice {
        $contract = new Contract(['customer_id' => $customer->id]);
        $contract->id = $contractId;
        $contract->setRelation('customer', $customer);

        $invoice = new Invoice([
            'contract_id' => $contractId,
            'pending_amount' => $pendingAmount,
            'total_amount' => $pendingAmount,
            'status' => 'pending',
            'due_date' => $dueDate,
            'issue_date' => '2026-07-01',
            'billing_period' => 'Julio 2026',
        ]);
        $invoice->id = $invoiceId;
        $invoice->setRelation('contract', $contract);
        $invoice->setRelation('payments', new Collection());
        $invoice->setRelation('creditNotes', new Collection());

        return $invoice;
    }

    private function evidenceFor(float $amount): YapeClientEvidence
    {
        $evidence = new YapeClientEvidence(['parsed_amount' => $amount]);
        $evidence->id = 77;

        return $evidence;
    }

    private function decisionService(Collection $pendingInvoices): object
    {
        return new class($this->createMock(PaymentService::class), $pendingInvoices) extends YapeReconciliationService {
            public function __construct(PaymentService $paymentService, private readonly Collection $pendingInvoices)
            {
                parent::__construct($paymentService);
            }

            public function decideForTest(Collection $customers, YapeClientEvidence $evidence, string $dni): array
            {
                return $this->decideInvoiceForDniEvidence($customers, $evidence, $dni, 'test-chat');
            }

            protected function pendingInvoicesForCustomers($customers)
            {
                return $this->pendingInvoices;
            }

            protected function appendYapeDirectDebug(string $event, array $context = []): void
            {
                // Intentionally disabled in unit tests.
            }

            protected function invoiceDecisionSelectionOptions($decisions): array
            {
                return [];
            }
        };
    }
}
