<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;
use Modules\Billing\Entities\YapeReceiverEvent;
use Modules\Billing\Services\YapeAwaitingDniReviewService;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Services\Entities\Service;
use Tests\TestCase;

class YapeAwaitingDniReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (config('tests.openwa_lid_mapping') === true
                && str_ends_with($request->url(), '/sessions/pagosyape-nuevo-numero')) {
                return Http::response(['id' => 'session-1'], 200);
            }

            if (config('tests.openwa_lid_failure') === true
                && str_contains($request->url(), '/contacts/resolve/')) {
                return Http::response(['message' => 'OpenWA no disponible'], 503);
            }

            if (config('tests.openwa_lid_mapping') === true
                && str_contains($request->url(), '/contacts/resolve/238164660760703%40lid')) {
                return Http::response([
                    'chatId' => '238164660760703@lid',
                    'sessionId' => 'pagosyape-nuevo-numero',
                    'resolved' => true,
                    'identity' => [
                        'id' => '51943064236@c.us',
                        'lid' => '238164660760703@lid',
                        'phoneNumber' => '51943064236',
                        'source' => 'whatsapp_web_js.getContactLidAndPhone',
                        'confidence' => 'high',
                    ],
                    'confirmedAt' => '2026-07-17T12:00:00Z',
                ], 200);
            }

            return Http::response([], 404);
        });
    }

    public function test_repeated_evidences_are_grouped_as_one_operation_with_separate_capture_count(): void
    {
        $first = $this->createAwaitingEvidence('CHAT@lid', '34819668', '668', 65, '2026-07-15 22:53:00');
        $resend = $this->createAwaitingEvidence('CHAT@lid', '34819668', '668', 65, '2026-07-15 22:53:00');
        YapeReceiverEvent::create([
            'screenshot_path' => 'yape/receiver-events/receiver.jpg',
            'parsed_amount' => 65,
            'security_code' => '668',
            'occurred_at' => '2026-07-15 22:53:28',
            'confidence' => 0.98,
            'status' => 'parsed',
        ]);

        $review = app(YapeAwaitingDniReviewService::class)->build();
        $case = $review['cases']->first();

        $this->assertCount(1, $review['cases']);
        $this->assertSame(2, $case->physicalEvidenceCount);
        $this->assertSame($first->id, $case->primaryEvidence->id);
        $this->assertSame([$resend->id], $case->resends->pluck('id')->all());
        $this->assertCount(1, $case->receiverEvents);
        $this->assertSame(1, $review['summary']['operations']);
        $this->assertSame(2, $review['summary']['physical_captures']);
    }

    public function test_lid_digits_are_never_interpreted_as_a_customer_phone(): void
    {
        Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Cliente',
            'last_name' => 'Lid Incorrecto',
            'identity_document' => '11111111',
            'phone' => '238164660760703',
            'active' => true,
        ]);
        $this->createAwaitingEvidence('238164660760703@lid', '11112222', '222', 77, now()->toDateTimeString());

        $case = app(YapeAwaitingDniReviewService::class)->build()['cases']->first();

        $this->assertTrue($case->candidates->isEmpty());
    }

    public function test_exact_chat_history_suggests_a_customer_with_high_confidence(): void
    {
        [$customer] = $this->createCustomerWithDebt('Gregorio', 'Casilla Oquinaya', '42574431', '943064236', 65);
        $this->createHistoricalEvidence($customer, '238164660760703@lid');
        $this->createAwaitingEvidence('238164660760703@lid', '34819668', '668', 65, now()->toDateTimeString());

        $candidate = app(YapeAwaitingDniReviewService::class)->build()['cases']->first()->candidates->first();

        $this->assertSame($customer->id, $candidate->customerId);
        $this->assertSame('alta', $candidate->confidence);
        $this->assertTrue($candidate->reasons->contains('Coincidencia por historial del mismo chat'));
    }

    public function test_amount_only_never_names_a_customer(): void
    {
        [$customer] = $this->createCustomerWithDebt('Cliente', 'Solo Monto', '22222222', '944444444', 65);
        $this->createAwaitingEvidence('unrelated@lid', '22334455', '455', 65, now()->toDateTimeString());

        $case = app(YapeAwaitingDniReviewService::class)->build()['cases']->first();

        $this->assertTrue($case->candidates->isEmpty());
    }

    public function test_confirmed_c_us_phone_can_suggest_a_customer_but_lid_itself_cannot(): void
    {
        [$customer] = $this->createCustomerWithDebt('Teléfono', 'Confirmado', '23232323', '943064236', 80);
        $evidence = $this->createAwaitingEvidence('238164660760703@lid', '12121212', '212', 80, now()->toDateTimeString());
        $payload = $evidence->payload;
        data_set($payload, 'openwa.data.sender', [
            'id' => '51943064236@c.us',
            'lid' => '238164660760703@lid',
            'phoneNumber' => '51943064236',
            'source' => 'whatsapp_web_js.getContactLidAndPhone',
            'confidence' => 'high',
        ]);
        $evidence->update(['payload' => $payload]);

        $candidate = app(YapeAwaitingDniReviewService::class)->build()['cases']->first()->candidates->first();

        $this->assertSame($customer->id, $candidate->customerId);
        $this->assertSame('alta', $candidate->confidence);
        $this->assertTrue($candidate->reasons->contains('Teléfono confirmado por OpenWA para el mismo chat LID'));
    }

    public function test_dni_recorded_previously_in_the_same_chat_suggests_the_exact_customer(): void
    {
        [$customer] = $this->createCustomerWithDebt('DNI', 'Confirmado', '67676767', '977777777', 90);
        YapeClientEvidence::create([
            'source_message_id' => 'dni_history_'.uniqid('', true),
            'source_chat_name' => 'dni-known@lid',
            'image_path' => 'yape/client-evidences/dni-history.jpg',
            'parsed_amount' => 90,
            'operation_code' => '99887766',
            'security_code' => '766',
            'occurred_at' => now()->subDay(),
            'confidence' => 0.95,
            'status' => 'ambiguous',
            'payload' => [
                'dni_resolution' => ['last_dni' => '67676767'],
                'yape_chat_state' => ['state' => 'resolved'],
            ],
        ]);
        $this->createAwaitingEvidence('dni-known@lid', '13131313', '313', 90, now()->toDateTimeString());

        $candidate = app(YapeAwaitingDniReviewService::class)->build()['cases']->first()->candidates->first();

        $this->assertSame($customer->id, $candidate->customerId);
        $this->assertSame('alta', $candidate->confidence);
        $this->assertTrue($candidate->reasons->contains('Coincidencia por DNI recibido'));
    }

    public function test_two_customers_with_the_same_confirmed_phone_mark_the_case_as_ambiguous(): void
    {
        $this->createCustomerWithDebt('Primer', 'Candidato', '33333333', '955555551', 65);
        $this->createCustomerWithDebt('Segundo', 'Candidato', '44444444', '955555551', 65);
        $evidence = $this->createAwaitingEvidence('ambiguous@lid', '33445566', '566', 65, now()->toDateTimeString());
        $payload = $evidence->payload;
        data_set($payload, 'openwa.data.sender', [
            'id' => '51955555551@c.us',
            'lid' => 'ambiguous@lid',
            'phoneNumber' => '51955555551',
            'source' => 'whatsapp_web_js.getContactLidAndPhone',
            'confidence' => 'high',
        ]);
        $evidence->update(['payload' => $payload]);

        $case = app(YapeAwaitingDniReviewService::class)->build()['cases']->first();

        $this->assertCount(2, $case->candidates);
        $this->assertTrue($case->ambiguous);
        $this->assertTrue($case->candidates->every(fn ($candidate) => $candidate->confidence === 'alta'));
    }

    public function test_review_queue_never_calls_openwa_to_resolve_lid_chats(): void
    {
        config([
            'billing.yape.openwa.api_url' => 'http://openwa.test/api',
            'billing.yape.openwa.session' => 'pagosyape-nuevo-numero',
            'billing.yape.openwa.api_key' => 'test-key',
            'tests.openwa_lid_mapping' => true,
        ]);
        $this->createCustomerWithDebt(
            'Gregorio',
            'Casilla Oquinaya',
            '42574431',
            '943064236',
            65
        );
        $evidence = $this->createAwaitingEvidence('238164660760703@lid', '34819668', '668', 65, now()->toDateTimeString());
        $case = app(YapeAwaitingDniReviewService::class)->build()['cases']->first();

        $this->assertFalse($case->phoneResolution->resolved);
        $this->assertTrue($case->candidates->isEmpty());
        Http::assertNothingSent();
    }

    public function test_openwa_failure_does_not_break_the_review_queue(): void
    {
        config([
            'billing.yape.openwa.api_url' => 'http://openwa.test/api',
            'billing.yape.openwa.session' => 'pagosyape-nuevo-numero',
            'billing.yape.openwa.api_key' => 'test-key',
            'tests.openwa_lid_mapping' => true,
            'tests.openwa_lid_failure' => true,
        ]);
        $this->createAwaitingEvidence('unresolved@lid', '35819668', '768', 65, now()->toDateTimeString());

        $case = app(YapeAwaitingDniReviewService::class)->build()['cases']->first();

        $this->assertNotNull($case);
        $this->assertFalse($case->phoneResolution->resolved);
        $this->assertSame('unidentified', $case->state->key);
    }

    public function test_lid_audit_counts_misclassified_and_unassociated_evidences_without_changing_them(): void
    {
        $evidence = $this->createAwaitingEvidence('238164660760703@lid', '15151515', '515', 65, now()->toDateTimeString());
        $evidence->update(['whatsapp_phone' => '238164660760703']);

        $summary = app(YapeAwaitingDniReviewService::class)->build()['summary'];

        $this->assertSame(1, $summary['lid_evidences']);
        $this->assertSame(1, $summary['lid_digits_as_phone']);
        $this->assertSame(1, $summary['lid_unassociated']);
        $this->assertSame('238164660760703', $evidence->fresh()->whatsapp_phone);
    }

    public function test_common_amount_with_more_than_two_customers_does_not_name_a_customer(): void
    {
        $this->createCustomerWithDebt('Primer', 'Monto Común', '34343434', '955555561', 65);
        $this->createCustomerWithDebt('Segundo', 'Monto Común', '45454545', '955555562', 65);
        $this->createCustomerWithDebt('Tercer', 'Monto Común', '56565656', '955555563', 65);
        $this->createAwaitingEvidence('common-amount@lid', '14141414', '414', 65, now()->toDateTimeString());

        $case = app(YapeAwaitingDniReviewService::class)->build()['cases']->first();

        $this->assertTrue($case->candidates->isEmpty());
        $this->assertFalse($case->ambiguous);
    }

    public function test_customer_without_debt_is_not_a_high_confidence_payment_candidate(): void
    {
        $customer = Customer::create([
            'customer_type' => 'individual',
            'first_name' => 'Sin',
            'last_name' => 'Deuda',
            'identity_document' => '55555555',
            'phone' => '966666666',
            'active' => true,
        ]);
        $this->createHistoricalEvidence($customer, 'known-chat@lid');
        $this->createAwaitingEvidence('known-chat@lid', '44556677', '677', 65, now()->toDateTimeString());

        $candidate = app(YapeAwaitingDniReviewService::class)->build()['cases']->first()->candidates->first();

        $this->assertNotSame('alta', $candidate->confidence);
        $this->assertSame(0.0, $candidate->debt);
        $this->assertTrue($candidate->reasons->contains('Cliente sin deuda pendiente; requiere revisión'));
    }

    public function test_build_is_strictly_read_only(): void
    {
        $evidence = $this->createAwaitingEvidence('readonly@lid', '55667788', '788', 65, now()->toDateTimeString());
        $before = $evidence->fresh()->getRawOriginal();
        $counts = [
            'evidences' => YapeClientEvidence::count(),
            'intents' => YapePaymentIntent::count(),
            'payments' => Payment::count(),
            'receiver_events' => YapeReceiverEvent::count(),
        ];

        app(YapeAwaitingDniReviewService::class)->build();

        $this->assertSame($counts['evidences'], YapeClientEvidence::count());
        $this->assertSame($counts['intents'], YapePaymentIntent::count());
        $this->assertSame($counts['payments'], Payment::count());
        $this->assertSame($counts['receiver_events'], YapeReceiverEvent::count());
        $this->assertSame($before, $evidence->fresh()->getRawOriginal());
    }

    public function test_genara_without_an_exact_invoice_stays_in_manual_review(): void
    {
        [$customer] = $this->createCustomerWithDebt('Genara', 'Condori Serrano', '61616161', '988888888', 205.83);
        $evidence = $this->createAwaitingEvidence('genara@lid', '17397423', '423', 140, now()->toDateTimeString());
        $payload = $evidence->payload;
        data_set($payload, 'dni_resolution.last_dni', '61616161');
        data_set($payload, 'openwa.data.sender', [
            'id' => '51988888888@c.us',
            'lid' => 'genara@lid',
            'phoneNumber' => '51988888888',
            'source' => 'whatsapp_web_js.getContactLidAndPhone',
            'confidence' => 'high',
        ]);
        $evidence->update(['payload' => $payload]);

        $case = app(YapeAwaitingDniReviewService::class)->build()['cases']->first();

        $this->assertSame($customer->id, $case->primaryCandidate->customerId);
        $this->assertSame('review', $case->state->key);
        $this->assertNull($case->proposedInvoice);
        $this->assertStringContainsString('no coincide exactamente', mb_strtolower($case->state->explanation));
    }

    public function test_server_pagination_projects_only_the_requested_number_of_operations(): void
    {
        foreach (range(1, 25) as $index) {
            $this->createAwaitingEvidence(
                "chat-{$index}@lid",
                (string) (80000000 + $index),
                str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                65,
                now()->addSeconds($index)->toDateTimeString()
            );
        }

        $review = app(YapeAwaitingDniReviewService::class)->build('', 'all', 2, 10);

        $this->assertCount(10, $review['cases']);
        $this->assertSame(25, $review['paginator']->total());
        $this->assertSame(2, $review['paginator']->currentPage());
        $this->assertSame(10, $review['paginator']->perPage());
    }

    public function test_candidate_loading_uses_bounded_batch_queries_instead_of_n_plus_one(): void
    {
        foreach (range(1, 10) as $index) {
            [$customer] = $this->createCustomerWithDebt(
                'Cliente',
                "Batch {$index}",
                str_pad((string) (70000000 + $index), 8, '0', STR_PAD_LEFT),
                (string) (950000000 + $index),
                65
            );
            $chatId = "batch-{$index}@lid";
            $this->createHistoricalEvidence($customer, $chatId);
            $this->createAwaitingEvidence(
                $chatId,
                (string) (81000000 + $index),
                str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                65,
                now()->addSeconds($index)->toDateTimeString()
            );
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $review = app(YapeAwaitingDniReviewService::class)->build('', 'all', 1, 10);
        $queries = collect(DB::getQueryLog());
        $queryCount = $queries->count();
        DB::disableQueryLog();

        $this->assertCount(10, $review['cases']);
        $this->assertLessThanOrEqual(
            12,
            $queryCount,
            "La cola ejecutó {$queryCount} consultas para 10 casos: ".$queries->pluck('query')->countBy()->toJson()
        );
    }

    public function test_administrative_language_is_primary_and_technical_data_is_collapsed(): void
    {
        $view = file_get_contents(base_path('Modules/Billing/Resources/views/yape/review-queue.blade.php'));

        $this->assertStringContainsString('Listas para reanudar', $view);
        $this->assertStringContainsString('Requieren revisión', $view);
        $this->assertStringContainsString('Cliente no identificado', $view);
        $this->assertStringContainsString('Confirmar y procesar', $view);
        $this->assertStringNotContainsString('Marcar como ya procesado', $view);
        $this->assertStringNotContainsString('Descartar captura', $view);
        $this->assertStringContainsString('details class="technical', $view);
        $this->assertStringContainsString('Detalles técnicos', $view);
    }

    private function createAwaitingEvidence(string $chatId, string $operation, string $security, float $amount, string $occurredAt): YapeClientEvidence
    {
        return YapeClientEvidence::create([
            'whatsapp_phone' => null,
            'source_message_id' => 'msg_'.uniqid('', true),
            'source_chat_name' => $chatId,
            'image_path' => 'yape/client-evidences/'.uniqid('capture_', true).'.jpg',
            'parsed_amount' => $amount,
            'operation_code' => $operation,
            'security_code' => $security,
            'occurred_at' => $occurredAt,
            'confidence' => 0.95,
            'status' => 'parsed',
            'payload' => [
                'yape_chat_state' => [
                    'state' => 'awaiting_dni',
                    'reason' => 'valid_yape_capture',
                ],
            ],
        ]);
    }

    private function createHistoricalEvidence(Customer $customer, string $chatId): YapeClientEvidence
    {
        return YapeClientEvidence::create([
            'customer_id' => $customer->id,
            'source_message_id' => 'history_'.uniqid('', true),
            'source_chat_name' => $chatId,
            'image_path' => 'yape/client-evidences/'.uniqid('history_', true).'.jpg',
            'parsed_amount' => 65,
            'operation_code' => (string) random_int(10000000, 99999999),
            'security_code' => '999',
            'occurred_at' => now()->subDay(),
            'confidence' => 0.95,
            'status' => 'attached',
            'payload' => ['yape_chat_state' => ['state' => 'resolved']],
        ]);
    }

    private function createCustomerWithDebt(string $firstName, string $lastName, string $dni, string $phone, float $amount): array
    {
        $service = Service::create([
            'name' => 'Internet '.uniqid(),
            'description' => 'Servicio de prueba',
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
            'first_name' => $firstName,
            'last_name' => $lastName,
            'identity_document' => $dni,
            'phone' => $phone,
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
}
