<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;
use Modules\Billing\Http\Controllers\YapeValidationController;
use Tests\TestCase;

class YapeAwaitingDniReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 404)]);
    }

    public function test_review_queue_exposes_only_parsed_evidences_waiting_for_dni_without_an_intent(): void
    {
        $awaitingDni = YapeClientEvidence::create([
            'whatsapp_phone' => '238164660760703',
            'source_message_id' => 'false_238164660760703@lid_AC36FC57C3942A03F94595D4C2C2D4F7',
            'source_chat_name' => '238164660760703@lid',
            'image_path' => 'yape/client-evidences/client-20260715225348-test.jpg',
            'parsed_amount' => 65,
            'operation_code' => '34819668',
            'security_code' => '668',
            'occurred_at' => '2026-07-15 22:53:00',
            'confidence' => 0.95,
            'status' => 'parsed',
            'payload' => [
                'yape_chat_state' => [
                    'state' => 'awaiting_dni',
                ],
            ],
        ]);

        YapeClientEvidence::create([
            'image_path' => 'yape/client-evidences/not-awaiting-dni.jpg',
            'status' => 'parsed',
            'payload' => [
                'yape_chat_state' => [
                    'state' => 'idle',
                ],
            ],
        ]);

        $view = app(YapeValidationController::class)->reviewQueue(new Request([
            'tab' => 'awaiting-dni',
        ]));
        $cases = $view->getData()['awaitingDniCases'];
        $summary = $view->getData()['awaitingDniSummary'];

        $this->assertCount(1, $cases);
        $this->assertSame($awaitingDni->id, $cases->first()->primaryEvidence->id);
        $this->assertSame(1, $summary['operations']);
        $this->assertSame(1, $summary['physical_captures']);
        $this->assertSame(1, $summary['lid_digits_as_phone']);
        $this->assertSame('billing::yape.review-queue', $view->getName());
    }

    public function test_default_review_tab_skips_the_heavy_awaiting_dni_projection(): void
    {
        YapeClientEvidence::create([
            'image_path' => 'yape/client-evidences/awaiting-dni.jpg',
            'parsed_amount' => 65,
            'status' => 'parsed',
            'payload' => [
                'yape_chat_state' => [
                    'state' => 'awaiting_dni',
                ],
            ],
        ]);

        $view = app(YapeValidationController::class)->reviewQueue(new Request());

        $this->assertCount(0, $view->getData()['awaitingDniCases']);
        $this->assertSame(1, $view->getData()['awaitingDniPaginator']->total());
        $this->assertCount(0, $view->getData()['customerQueues']);
    }

    public function test_review_queue_keeps_linked_evidence_visible_while_dni_is_pending(): void
    {
        $evidence = YapeClientEvidence::create([
            'source_message_id' => 'msg-linked-awaiting-dni',
            'source_chat_name' => 'linked-awaiting-dni@lid',
            'image_path' => 'yape/client-evidences/linked-awaiting-dni.jpg',
            'parsed_amount' => 65,
            'operation_code' => '27604592',
            'security_code' => '592',
            'occurred_at' => now(),
            'confidence' => 0.95,
            'status' => 'attached',
            'payload' => ['yape_chat_state' => ['state' => 'awaiting_dni']],
        ]);
        $intent = YapePaymentIntent::create([
            'intent_number' => 'YPI-LINKED-AWAITING-DNI',
            'client_evidence_id' => $evidence->id,
            'expected_amount' => 65,
            'payment_mode' => 'single',
            'status' => YapePaymentIntent::STATUS_OBSERVED,
            'operation_code' => '27604592',
            'security_code' => '592',
            'expires_at' => now()->addDay(),
        ]);
        $evidence->update(['yape_payment_intent_id' => $intent->id]);

        $view = app(YapeValidationController::class)->reviewQueue(new Request([
            'tab' => 'awaiting-dni',
        ]));

        $cases = $view->getData()['awaitingDniCases'];
        $this->assertCount(1, $cases);
        $this->assertSame($evidence->id, $cases->first()->primaryEvidence->id);
    }
}
