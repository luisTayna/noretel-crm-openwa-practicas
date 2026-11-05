<?php

namespace Tests\Unit;

use Modules\Billing\Services\OpenWaService;
use ReflectionClass;
use Tests\TestCase;

class OpenWaReceiptDeliveryTest extends TestCase
{
    public function test_chat_id_falls_back_to_the_customer_whatsapp_phone(): void
    {
        config(['billing.yape.openwa.default_country_code' => '51']);

        $chatId = $this->resolveChatId([
            'source_chat_name' => 'Edwin Torres',
            'whatsapp_phone' => '970233366',
        ]);

        $this->assertSame('51970233366@c.us', $chatId);
    }

    public function test_existing_openwa_chat_id_is_preserved(): void
    {
        $chatId = $this->resolveChatId([
            'source_chat_name' => '51970233366@c.us',
            'whatsapp_phone' => '970233366',
        ]);

        $this->assertSame('51970233366@c.us', $chatId);
    }

    public function test_receipt_delivery_reports_a_missing_payment_instead_of_failing_silently(): void
    {
        config(['billing.yape.openwa.send_receipt_on_validation' => true]);

        $result = app(OpenWaService::class)->sendPaymentReceipt([], null);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('pago', mb_strtolower($result['error']));
    }

    private function resolveChatId(array $data): ?string
    {
        $service = app(OpenWaService::class);
        $method = (new ReflectionClass($service))->getMethod('openWaChatId');
        $method->setAccessible(true);

        return $method->invoke($service, $data);
    }
}
