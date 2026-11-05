<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;
use Modules\Billing\Entities\YapeReceiverEvent;
use Modules\Billing\Services\YapeReconciliationService;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

class YapeResumeWithDniTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_gregorio_exact_resume_creates_one_payment_and_links_receiver_once(): void
    {
        [$customer, $contract, $oldestInvoice] = $this->createCustomerWithInvoice('42574431', 65);
        $canonical = $this->createEvidence('34819668', '668', 65);
        $resend = $this->createEvidence('34819668', '668', 65, $canonical->occurred_at->toDateTimeString());
        $receiver = $this->createReceiver(null, '668', 65, $canonical);
        $service = app(YapeReconciliationService::class);

        $first = $service->resumeClientEvidenceByDni($canonical, '42574431');
        $second = $service->resumeClientEvidenceByDni($canonical, '42574431');

        $this->assertTrue($first['resolved']);
        $this->assertFalse($first['idempotent']);
        $this->assertTrue($second['idempotent']);
        $this->assertSame(1, YapePaymentIntent::count());
        $this->assertSame(1, Payment::count());
        $this->assertSame($oldestInvoice->id, $first['payment']->invoice_id);
        $this->assertSame($customer->id, $first['intent']->customer_id);
        $this->assertSame($oldestInvoice->id, $first['intent']->invoice_id);
        $this->assertSame($receiver->id, $first['intent']->receiver_event_id);
        $this->assertSame($first['intent']->id, $receiver->fresh()->yape_payment_intent_id);
        $this->assertSame('duplicate', $resend->fresh()->status);
        $this->assertSame($first['intent']->id, $resend->fresh()->yape_payment_intent_id);
        Http::assertNothingSent();
    }

    public function test_operation_with_existing_intent_is_reused_without_creating_payment(): void
    {
        $this->createCustomerWithInvoice('42574431', 65);
        $evidence = $this->createEvidence('34819668', '668', 65);
        $intent = YapePaymentIntent::create([
            'intent_number' => 'YPI-TEST-0001',
            'client_evidence_id' => $evidence->id,
            'expected_amount' => 65,
            'payment_mode' => 'single',
            'status' => YapePaymentIntent::STATUS_OBSERVED,
            'operation_code' => '34819668',
            'security_code' => '668',
            'expires_at' => now()->addDay(),
        ]);
        $evidence->update([
            'payload' => [
                'yape_chat_state' => ['state' => 'resolved'],
                'dni_resolution' => ['resolved_at' => now()->toDateTimeString()],
            ],
        ]);

        $result = app(YapeReconciliationService::class)->resumeClientEvidenceByDni($evidence, '42574431');

        $this->assertTrue($result['idempotent']);
        $this->assertSame($intent->id, $result['intent']->id);
        $this->assertSame(1, YapePaymentIntent::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_linked_capture_waiting_for_dni_is_resumed_instead_of_being_treated_as_processed(): void
    {
        [$customer, $contract, $invoice] = $this->createCustomerWithInvoice('42574431', 65);
        $evidence = $this->createEvidence('27604592', '592', 65);
        $intent = YapePaymentIntent::create([
            'intent_number' => 'YPI-LINKED-AWAITING-DNI',
            'customer_id' => $customer->id,
            'contract_id' => $contract->id,
            'invoice_id' => $invoice->id,
            'client_evidence_id' => $evidence->id,
            'expected_amount' => 65,
            'payment_mode' => 'single',
            'status' => YapePaymentIntent::STATUS_WAITING_RECEIVER,
            'operation_code' => '27604592',
            'security_code' => '592',
            'expires_at' => now()->addDay(),
        ]);
        $evidence->update([
            'yape_payment_intent_id' => $intent->id,
            'status' => 'attached',
        ]);

        $result = app(YapeReconciliationService::class)->resumeClientEvidenceByDni($evidence, '42574431');

        $this->assertTrue($result['resolved']);
        $this->assertFalse($result['idempotent']);
        $this->assertSame($intent->id, $result['intent']->id);
        $this->assertSame('resolved', data_get($result['evidence']->payload, 'yape_chat_state.state'));
        $this->assertSame('42574431', data_get($result['evidence']->payload, 'dni_resolution.last_dni'));
        $this->assertSame(0, Payment::count());
    }

    public function test_operation_with_existing_payment_does_not_create_an_intent(): void
    {
        [, , $invoice] = $this->createCustomerWithInvoice('42574431', 65);
        $evidence = $this->createEvidence('34819668', '668', 65);
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => 65,
            'payment_date' => '2026-07-15',
            'payment_method' => 'yape',
            'reference' => '34819668',
            'status' => 'completed',
        ]);

        $result = app(YapeReconciliationService::class)->resumeClientEvidenceByDni($evidence, '42574431');

        $this->assertTrue($result['idempotent']);
        $this->assertSame($payment->id, $result['payment']->id);
        $this->assertSame(0, YapePaymentIntent::count());
        $this->assertSame(1, Payment::count());
    }

    public function test_ambiguous_dni_stays_in_normal_review_without_creating_payment(): void
    {
        $this->createCustomerWithInvoice('77777777', 65);
        $this->createCustomerWithInvoice('77777777', 65);
        $evidence = $this->createEvidence('77889900', '900', 65);

        $result = app(YapeReconciliationService::class)->resumeClientEvidenceByDni($evidence, '77777777');

        $this->assertFalse($result['resolved']);
        $this->assertContains($result['reason'], [
            YapeReconciliationService::DNI_RESOLUTION_SERVICE_SELECTION_REQUIRED,
            YapeReconciliationService::DNI_RESOLUTION_AMBIGUOUS_PAYMENT,
        ]);
        $this->assertSame(0, Payment::count());
    }

    public function test_multiple_exact_invoices_stay_observed_without_creating_a_payment(): void
    {
        [$customer, $contract] = $this->createCustomerWithInvoice('42574431', 65);
        Invoice::create([
            'contract_id' => $contract->id,
            'invoice_number' => 'INV-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'amount' => 65,
            'taxes' => 0,
            'total_amount' => 65,
            'issue_date' => '2026-08-01',
            'due_date' => '2026-08-30',
            'status' => 'pending',
            'document_type' => 'invoice',
            'generation_type' => 'automatic',
            'billing_period' => 'agosto 2026',
            'sent' => false,
        ]);
        $evidence = $this->createEvidence('34819669', '669', 65);
        $this->createReceiver(null, '669', 65, $evidence);

        $result = app(YapeReconciliationService::class)->resumeClientEvidenceByDni($evidence, '42574431');

        $this->assertSame(1, YapePaymentIntent::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(YapePaymentIntent::STATUS_OBSERVED, $result['intent']->fresh()->status);
    }

    public function test_late_capture_with_unique_historical_receiver_requires_manual_validation(): void
    {
        $this->createCustomerWithInvoice('42574431', 65);
        $evidence = $this->createEvidence('99112233', '233', 65, now()->toDateTimeString());
        $receiver = $this->createReceiver('99112233', '233', 65, $evidence);
        $receiver->update(['occurred_at' => now()->subDays(13)]);

        $result = app(YapeReconciliationService::class)->resumeClientEvidenceByDni($evidence, '42574431');

        $intent = $result['intent']->fresh(['receiverEvent']);
        $this->assertSame(1, YapePaymentIntent::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(YapePaymentIntent::STATUS_OBSERVED, $intent->status);
        $this->assertSame($receiver->id, $intent->receiver_event_id);
        $this->assertTrue((bool) data_get($intent->source_payload, 'historical_review.is_historical'));
        $this->assertSame($intent->id, $receiver->fresh()->yape_payment_intent_id);
        Http::assertNothingSent();
    }

    private function createCustomerWithInvoice(string $dni, float $amount): array
    {
        $service = Service::create([
            'name' => 'Internet '.uniqid(),
            'description' => 'Prueba',
            'service_type' => Service::SERVICE_TYPE_INTERNET,
            'technology' => Service::TECHNOLOGY_FIBER_WIFI,
            'active' => true,
        ]);
        $plan = Plan::create([
            'service_id' => $service->id,
            'name' => 'Plan '.uniqid(),
            'price' => $amount,
            'upload_speed' => 50,
            'download_speed' => 100,
            'active' => true,
        ]);
        $customer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Cliente',
            'last_name' => uniqid(),
            'identity_document' => $dni,
            'phone' => (string) random_int(900000000, 999999999),
            'active' => true,
        ]);
        $contract = Contract::create([
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
            'start_date' => '2026-01-01',
            'status' => 'active',
            'final_price' => $amount,
        ]);
        $invoice = Invoice::create([
            'contract_id' => $contract->id,
            'invoice_number' => 'INV-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'amount' => $amount,
            'taxes' => 0,
            'total_amount' => $amount,
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-30',
            'status' => 'pending',
            'document_type' => 'invoice',
            'generation_type' => 'automatic',
            'billing_period' => 'julio 2026',
            'sent' => false,
        ]);

        return [$customer, $contract, $invoice];
    }

    private function createEvidence(string $operation, string $security, float $amount, ?string $occurredAt = null): YapeClientEvidence
    {
        return YapeClientEvidence::create([
            'source_message_id' => 'msg_'.uniqid('', true),
            'source_chat_name' => '238164660760703@lid',
            'image_path' => 'yape/client-evidences/'.uniqid('capture_', true).'.jpg',
            'parsed_amount' => $amount,
            'operation_code' => $operation,
            'security_code' => $security,
            'occurred_at' => $occurredAt ?: now(),
            'confidence' => 0.95,
            'status' => 'parsed',
            'payload' => ['yape_chat_state' => ['state' => 'awaiting_dni']],
        ]);
    }

    private function createReceiver(?string $operation, string $security, float $amount, YapeClientEvidence $evidence): YapeReceiverEvent
    {
        return YapeReceiverEvent::create([
            'screenshot_path' => 'yape/receiver-events/'.uniqid('receiver_', true).'.jpg',
            'parsed_amount' => $amount,
            'operation_code' => $operation,
            'security_code' => $security,
            'occurred_at' => $evidence->occurred_at->copy()->addSeconds(20),
            'confidence' => 0.98,
            'status' => 'parsed',
        ]);
    }
}
