<?php

namespace Tests\Unit;

use Modules\Billing\Http\Controllers\YapeValidationController;
use Modules\Billing\Services\OpenWaWebhookPayloadService;
use Modules\Billing\Services\OpenWaService;
use Modules\Billing\Services\YapeReconciliationService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OpenWaWebhookPayloadServiceTest extends TestCase
{
    public function test_it_normalizes_incoming_image_message(): void
    {
        $service = new OpenWaWebhookPayloadService();

        $result = $service->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'id' => ['_serialized' => 'ABC123'],
                    'from' => '51987654321@c.us',
                    'notifyName' => 'Cliente Prueba',
                    'type' => 'image',
                    'mimetype' => 'image/png',
                    'caption' => 'Pago Yape S/ 50.00 Operación 12345678',
                    'timestamp' => 1780000000,
                    'media' => [
                        'url' => '/sessions/noretel-yape/messages/ABC123/media',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($result['should_process']);
        $this->assertSame('image', $result['type']);
        $this->assertSame('image', $result['message_kind']);
        $this->assertSame('51987654321', $result['data']['whatsapp_phone']);
        $this->assertSame('ABC123', $result['data']['source_message_id']);
        $this->assertSame('51987654321@c.us', $result['data']['source_chat_name']);
        $this->assertSame('Cliente Prueba', $result['data']['source_chat_display_name']);
        $this->assertSame('/sessions/noretel-yape/messages/ABC123/media', $result['data']['media_url']);
    }

    public function test_lid_chat_is_preserved_but_never_stored_as_a_phone_without_a_confirmed_mapping(): void
    {
        $result = (new OpenWaWebhookPayloadService())->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'id' => 'LID-IMAGE-1',
                    'from' => '238164660760703@lid',
                    'chatId' => '238164660760703@lid',
                    'type' => 'image',
                    'mimetype' => 'image/png',
                    'media' => ['data' => base64_encode('image')],
                ],
            ],
        ]);

        $this->assertTrue($result['should_process']);
        $this->assertSame('238164660760703@lid', $result['data']['source_chat_name']);
        $this->assertSame('238164660760703@lid', $result['data']['openwa_chat_id']);
        $this->assertNull($result['data']['whatsapp_phone']);
        $this->assertNull($result['data']['whatsapp_phone_source']);
    }

    public function test_confirmed_openwa_lid_mapping_provides_the_real_phone_without_replacing_chat_id(): void
    {
        $result = (new OpenWaWebhookPayloadService())->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'id' => 'LID-IMAGE-2',
                    'from' => '238164660760703@lid',
                    'chatId' => '238164660760703@lid',
                    'sender' => [
                        'id' => '51943064236@c.us',
                        'lid' => '238164660760703@lid',
                        'phoneNumber' => '51943064236',
                        'source' => 'whatsapp_web_js.getContactLidAndPhone',
                        'confidence' => 'high',
                    ],
                    'type' => 'image',
                    'mimetype' => 'image/png',
                    'media' => ['data' => base64_encode('image')],
                ],
            ],
        ]);

        $this->assertSame('51943064236', $result['data']['whatsapp_phone']);
        $this->assertSame('whatsapp_web_js.getContactLidAndPhone', $result['data']['whatsapp_phone_source']);
        $this->assertSame('high', $result['data']['whatsapp_phone_confidence']);
        $this->assertSame('238164660760703@lid', $result['data']['source_chat_name']);
        $this->assertSame('238164660760703@lid', $result['data']['openwa_chat_id']);
    }

    public function test_unverified_or_mismatched_lid_mapping_is_rejected(): void
    {
        $result = (new OpenWaWebhookPayloadService())->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'from' => '238164660760703@lid',
                    'sender' => [
                        'id' => '51943064236@c.us',
                        'lid' => 'different@lid',
                        'phoneNumber' => '51943064236',
                        'source' => 'whatsapp_web_js.getContactLidAndPhone',
                        'confidence' => 'high',
                    ],
                    'type' => 'chat',
                    'body' => '42574431',
                ],
            ],
        ]);

        $this->assertNull($result['data']['whatsapp_phone']);
        $this->assertSame('238164660760703@lid', $result['data']['source_chat_name']);
    }

    public function test_responses_keep_using_the_original_lid_even_when_a_phone_identity_exists(): void
    {
        $service = new OpenWaService();
        $method = (new ReflectionClass(OpenWaService::class))->getMethod('openWaChatId');

        $chatId = $method->invoke($service, [
            'source_chat_name' => '238164660760703@lid',
            'whatsapp_phone' => '51943064236',
            'payload' => [
                'data' => [
                    'from' => '238164660760703@lid',
                    'chatId' => '238164660760703@lid',
                    'sender' => [
                        'id' => '51943064236@c.us',
                    ],
                ],
            ],
        ]);

        $this->assertSame('238164660760703@lid', $chatId);
    }

    public function test_it_ignores_stickers_instead_of_sending_them_to_ocr(): void
    {
        $result = $this->normalizeMediaMessage('sticker', 'image/webp');

        $this->assertFalse($result['should_process']);
        $this->assertSame('sticker', $result['message_kind']);
        $this->assertSame('sticker_ignored', $result['ignored_reason']);
    }

    public function test_it_normalizes_interactive_list_selections(): void
    {
        $service = new OpenWaWebhookPayloadService();

        $result = $service->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'id' => ['_serialized' => 'LIST123'],
                    'from' => '51987654321@c.us',
                    'type' => 'list_response',
                    'body' => 'Reportar avería',
                    'selectedRowId' => 'support_outage',
                ],
            ],
        ]);

        $this->assertTrue($result['should_process']);
        $this->assertSame('interactive_selection', $result['type']);
        $this->assertSame('support_outage', $result['data']['interactive_selection_id']);
    }

    public function test_it_treats_ambiguous_webp_media_as_sticker(): void
    {
        $result = $this->normalizeMediaMessage('', 'image/webp');

        $this->assertFalse($result['should_process']);
        $this->assertSame('sticker_ignored', $result['ignored_reason']);
    }

    public function test_it_ignores_explicit_webp_images_as_sticker_safe_default(): void
    {
        $result = $this->normalizeMediaMessage('image', 'image/webp');

        $this->assertFalse($result['should_process']);
        $this->assertSame('sticker', $result['message_kind']);
        $this->assertSame('sticker_ignored', $result['ignored_reason']);
    }

    /**
     * @dataProvider unsupportedMessageKinds
     */
    public function test_it_ignores_unsupported_message_kinds(string $type, string $mime, string $expectedKind): void
    {
        $result = $this->normalizeMediaMessage($type, $mime);

        $this->assertFalse($result['should_process']);
        $this->assertSame($expectedKind, $result['message_kind']);
        $this->assertSame($expectedKind . '_ignored', $result['ignored_reason']);
    }

    public static function unsupportedMessageKinds(): array
    {
        return [
            'audio' => ['audio', 'audio/ogg', 'audio'],
            'voice note' => ['ptt', 'audio/ogg', 'audio'],
            'video' => ['video', 'video/mp4', 'video'],
            'document' => ['document', 'application/pdf', 'document'],
            'reaction' => ['reaction', '', 'reaction'],
            'location' => ['location', '', 'location'],
            'contact' => ['vcard', 'text/vcard', 'contact'],
            'unknown' => ['ciphertext', '', 'unknown'],
        ];
    }

    /**
     * @dataProvider dniTextMessages
     */
    public function test_it_extracts_dni_from_natural_text(string $text): void
    {
        $result = $this->normalizeTextMessage($text);

        $this->assertTrue($result['should_process']);
        $this->assertSame('dni_resolution', $result['type']);
        $this->assertSame('76734548', $result['data']['dni']);
    }

    public static function dniTextMessages(): array
    {
        return [
            'solo numeros' => ['76734548'],
            'dni prefijo' => ['dni 76734548'],
            'dni del cliente' => ['DNI del cliente 76734548'],
            'mi cliente' => ['mi cliente es 76734548'],
            'frase completa' => ['el dni del cliente es 76734548'],
            'documento' => ['documento 76734548'],
            'espacios internos' => ['76 734 548'],
            'guiones internos' => ['76-734-548'],
        ];
    }

    /**
     * @dataProvider invalidDniTextMessages
     */
    public function test_it_reports_dni_input_errors(string $text, string $expectedError): void
    {
        $result = $this->normalizeTextMessage($text);

        $this->assertTrue($result['should_process']);
        $this->assertSame('dni_resolution_error', $result['type']);
        $this->assertSame($expectedError, $result['data']['dni_error']);
    }

    public static function invalidDniTextMessages(): array
    {
        return [
            'menos de ocho digitos' => ['7673454', OpenWaWebhookPayloadService::DNI_ERROR_TOO_SHORT],
            'mas de ocho digitos' => ['767345489', OpenWaWebhookPayloadService::DNI_ERROR_TOO_LONG],
            'varios dni posibles' => ['76734548 o 12345678', OpenWaWebhookPayloadService::DNI_ERROR_MULTIPLE],
            'texto sin dni' => ['ya pagué', OpenWaWebhookPayloadService::DNI_ERROR_MISSING],
        ];
    }

    public function test_it_uses_dni_del_cliente_messages(): void
    {
        $controller = new ReflectionClass(YapeValidationController::class);

        $this->assertSame(
            'Recibimos tu captura Yape. Para terminar de validarla y emitir tu boleta, envíanos el DNI del titular.',
            $controller->getConstant('YAPE_DNI_PROMPT_MESSAGE')
        );

        $messages = $controller->getConstant('YAPE_DNI_ERROR_MESSAGES');

        $this->assertSame(
            'Verifica el DNI. Debe tener 8 dígitos.',
            $messages[OpenWaWebhookPayloadService::DNI_ERROR_TOO_SHORT]
        );
        $this->assertSame(
            'Verifica el DNI. Debe tener 8 dígitos.',
            $messages[OpenWaWebhookPayloadService::DNI_ERROR_TOO_LONG]
        );
        $this->assertSame(
            'Para emitir tu boleta y continuar, envíanos el DNI del titular.',
            $messages[OpenWaWebhookPayloadService::DNI_ERROR_MISSING]
        );
        $this->assertSame(
            'Encontramos más de un número de 8 dígitos. Por seguridad, envía solo el DNI del cliente correcto.',
            $messages[OpenWaWebhookPayloadService::DNI_ERROR_MULTIPLE]
        );

        $resolutionMessages = $controller->getConstant('YAPE_DNI_RESOLUTION_MESSAGES');

        $this->assertSame(
            'No encontramos un cliente registrado con ese DNI. Verifica el número y envíalo nuevamente.',
            $resolutionMessages[YapeReconciliationService::DNI_RESOLUTION_CUSTOMER_NOT_FOUND]
        );
        $this->assertSame(
            'No pudimos asociar el pago automáticamente con el DNI enviado. Tu captura quedó registrada para revisión por soporte.',
            $resolutionMessages[YapeReconciliationService::DNI_RESOLUTION_CUSTOMER_NOT_FOUND_MANUAL_REVIEW]
        );
        $this->assertSame(
            'Encontramos más de una captura Yape pendiente. Por seguridad, envía nuevamente la captura correcta y luego el DNI del cliente.',
            $resolutionMessages[YapeReconciliationService::DNI_RESOLUTION_MULTIPLE_PENDING_EVIDENCES]
        );
    }

    public function test_it_uses_payment_validation_messages_without_capture_association_text(): void
    {
        $controller = new ReflectionClass(YapeValidationController::class);

        $this->assertSame(
            'Gracias. Estamos validando tu pago.',
            $controller->getConstant('YAPE_VALIDATION_IN_PROGRESS_MESSAGE')
        );

        $this->assertSame(
            'Tu pago fue validado correctamente. Gracias.',
            $controller->getConstant('YAPE_PAYMENT_VALIDATED_MESSAGE')
        );

        $this->assertStringNotContainsString(
            'Asociamos tu captura Yape',
            $controller->getConstant('YAPE_VALIDATION_IN_PROGRESS_MESSAGE')
        );
    }

    public function test_it_ignores_outgoing_messages(): void
    {
        $service = new OpenWaWebhookPayloadService();

        $result = $service->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'fromMe' => true,
                    'from' => '51987654321@c.us',
                    'type' => 'image',
                    'mimetype' => 'image/png',
                ],
            ],
        ]);

        $this->assertFalse($result['should_process']);
        $this->assertSame('from_me_ignored', $result['ignored_reason']);
    }

    public function test_it_ignores_group_messages_from_any_identifier(): void
    {
        $service = new OpenWaWebhookPayloadService();

        $result = $service->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'from' => '51987654321@c.us',
                    'remoteJid' => '120363123456789@g.us',
                    'type' => 'image',
                    'mimetype' => 'image/png',
                ],
            ],
        ]);

        $this->assertFalse($result['should_process']);
        $this->assertSame('group_ignored', $result['ignored_reason']);
        $this->assertTrue($result['is_group']);
    }

    public function test_it_requires_private_chat(): void
    {
        $service = new OpenWaWebhookPayloadService();

        $result = $service->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'from' => 'status@broadcast',
                    'type' => 'image',
                    'mimetype' => 'image/png',
                ],
            ],
        ]);

        $this->assertFalse($result['should_process']);
        $this->assertSame('non_private_ignored', $result['ignored_reason']);
    }

    public function test_service_selection_only_accepts_a_short_numeric_option(): void
    {
        $controller = new ReflectionClass(YapeValidationController::class);
        $method = $controller->getMethod('isPotentialServiceSelectionText');
        $instance = $controller->newInstanceWithoutConstructor();

        $this->assertTrue($method->invoke($instance, '1'));
        $this->assertTrue($method->invoke($instance, ' 12 '));
        $this->assertFalse($method->invoke($instance, 'hola'));
        $this->assertFalse($method->invoke($instance, 'opción 1'));
        $this->assertFalse($method->invoke($instance, '12345678'));
    }

    public function test_dni_attempt_detection_ignores_amounts_and_unrelated_numbers(): void
    {
        $controller = new ReflectionClass(YapeValidationController::class);
        $method = $controller->getMethod('isPotentialDniAttemptText');
        $instance = $controller->newInstanceWithoutConstructor();

        $this->assertTrue($method->invoke($instance, '7673454'));
        $this->assertTrue($method->invoke($instance, 'DNI 1234'));
        $this->assertTrue($method->invoke($instance, 'documento 767345489'));
        $this->assertFalse($method->invoke($instance, 'hola'));
        $this->assertFalse($method->invoke($instance, 'pagué 60'));
        $this->assertFalse($method->invoke($instance, 'tengo 2 servicios'));
    }

    public function test_customer_menu_request_detection_is_contextual(): void
    {
        $controller = new ReflectionClass(YapeValidationController::class);
        $method = $controller->getMethod('isCustomerMenuRequest');
        $instance = $controller->newInstanceWithoutConstructor();

        $this->assertTrue($method->invoke($instance, 'No tengo internet'));
        $this->assertTrue($method->invoke($instance, 'Quiero ver el menú'));
        $this->assertTrue($method->invoke($instance, 'Necesito mi recibo'));
        $this->assertFalse($method->invoke($instance, '76734548'));
        $this->assertFalse($method->invoke($instance, 'gracias'));
    }

    private function normalizeTextMessage(string $text): array
    {
        $service = new OpenWaWebhookPayloadService();

        return $service->normalize([
            'event' => 'message.received',
            'data' => [
                'message' => [
                    'from' => '51987654321@c.us',
                    'type' => 'chat',
                    'body' => $text,
                ],
            ],
        ]);
    }

    private function normalizeMediaMessage(string $type, string $mime): array
    {
        $message = [
            'id' => ['_serialized' => 'MEDIA123'],
            'from' => '51987654321@c.us',
            'hasMedia' => true,
            'media' => ['url' => '/media/MEDIA123'],
        ];

        if ($type !== '') {
            $message['type'] = $type;
        }

        if ($mime !== '') {
            $message['mimetype'] = $mime;
        }

        return (new OpenWaWebhookPayloadService())->normalize([
            'event' => 'message.received',
            'data' => ['message' => $message],
        ]);
    }
}
