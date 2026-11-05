<?php

namespace Modules\Billing\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;

class OpenWaService
{
    public function sendTextMessage(array $data, string $message, array $context = []): array
    {
        $chatId = $this->openWaChatId($data);

        if (! $chatId) {
            return [
                'success' => false,
                'error' => 'missing_chat_id',
                'chat_id' => null,
            ];
        }

        return $this->sendTextToChatId($chatId, $message, array_merge([
            'source' => 'yape_flow',
        ], $context));
    }

    public function sendEvidenceTextMessageOnce(
        ?YapeClientEvidence $evidence,
        array $data,
        string $message,
        string $context
    ): bool {
        $evidence = $evidence?->fresh();

        if (! $evidence) {
            $this->sendTextMessage($data, $message);

            return true;
        }

        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $messages = is_array($payload['openwa_messages'] ?? null)
            ? $payload['openwa_messages']
            : [];
        $chatId = $this->openWaChatId($data);
        $existingContext = is_array($messages[$context] ?? null)
            ? $messages[$context]
            : [];
        $existingContextEvidenceId = isset($existingContext['evidence_id'])
            ? (int) $existingContext['evidence_id']
            : $evidence->id;
        $existingContextChatId = $existingContext['chat_id'] ?? null;
        $existingContextSuccessful = ! empty($existingContext['sent_at'])
            && (($existingContext['send_success'] ?? true) === true);
        $sameEvidenceContext = $existingContextEvidenceId === (int) $evidence->id;
        $sameChatContext = ! $existingContextChatId || ! $chatId || $existingContextChatId === $chatId;

        $this->appendYapeDirectDebug('yape_message_context_state', [
            'message_context' => $context,
            'current_evidence_id' => $evidence->id,
            'current_chat_id' => $chatId,
            'existing_context_sent_at' => $existingContext['sent_at'] ?? null,
            'existing_context_evidence_id' => $existingContextEvidenceId,
            'existing_context_chat_id' => $existingContextChatId,
            'existing_context_send_success' => $existingContext['send_success'] ?? null,
            'same_evidence_context' => $sameEvidenceContext,
            'same_chat_context' => $sameChatContext,
            'will_block' => $existingContextSuccessful && $sameEvidenceContext && $sameChatContext,
        ]);

        if ($existingContextSuccessful && $sameEvidenceContext && $sameChatContext) {
            $this->appendYapeDirectDebug('yape_dni_prompt_attempt', [
                'chat_id' => $chatId,
                'message_text' => $message,
                'api_url' => config('billing.yape.openwa.api_url'),
                'session' => config('billing.yape.openwa.session'),
                'send_result' => false,
                'send_error' => 'message_context_already_sent',
                'message_context' => $context,
                'evidence_id' => $evidence->id,
                'existing_context_evidence_id' => $existingContextEvidenceId,
                'existing_context_chat_id' => $existingContextChatId,
                'existing_context_sent_at' => $existingContext['sent_at'] ?? null,
                'existing_context_send_success' => $existingContext['send_success'] ?? null,
            ]);

            return false;
        }

        if (! $chatId) {
            $this->appendYapeDirectDebug('yape_dni_prompt_attempt', [
                'chat_id' => null,
                'message_text' => $message,
                'api_url' => config('billing.yape.openwa.api_url'),
                'session' => config('billing.yape.openwa.session'),
                'send_result' => false,
                'send_error' => 'missing_chat_id',
                'message_context' => $context,
                'evidence_id' => $evidence->id,
            ]);

            return false;
        }

        $response = $this->sendTextToChatId($chatId, $message, [
            'source' => 'yape_flow',
            'message_context' => $context,
            'evidence_id' => $evidence->id,
        ]);

        if (! ($response['success'] ?? false)) {
            $this->appendYapeDirectDebug('yape_dni_prompt_attempt', [
                'chat_id' => $chatId,
                'message_text' => $message,
                'api_url' => config('billing.yape.openwa.api_url'),
                'session' => config('billing.yape.openwa.session'),
                'send_result' => false,
                'send_error' => $response['error'] ?? ($response['body'] ?? 'openwa_send_failed'),
                'message_context' => $context,
                'evidence_id' => $evidence->id,
                'http_status' => $response['status'] ?? null,
            ]);

            return false;
        }

        $messagePayload = [
            'sent_at' => now()->toDateTimeString(),
            'message' => $message,
            'context' => $context,
            'evidence_id' => $evidence->id,
            'chat_id' => $chatId,
            'send_success' => true,
            'http_status' => $response['status'] ?? null,
            'session' => $response['session'] ?? null,
            'openwa_response' => $response['json'] ?? null,
        ];

        $messages[$context] = $messagePayload;
        $payload['openwa_messages'] = $messages;

        if (str_starts_with($context, 'payment_')) {
            $payload['validation_message'] = $messagePayload;
        }

        $evidence->update(['payload' => $payload]);

        $this->appendYapeDirectDebug('yape_dni_prompt_attempt', [
            'chat_id' => $chatId,
            'message_text' => $message,
            'api_url' => config('billing.yape.openwa.api_url'),
            'session' => config('billing.yape.openwa.session'),
            'send_result' => true,
            'send_error' => null,
            'message_context' => $context,
            'evidence_id' => $evidence->id,
            'http_status' => $response['status'] ?? null,
        ]);

        return true;
    }

    public function sendTextToPhone(?string $phone, string $message, array $context = []): array
    {
        $chatId = $this->chatIdFromPhone($phone);

        if (! $chatId) {
            return [
                'success' => false,
                'error' => 'Teléfono inválido o vacío para OpenWA.',
                'chat_id' => null,
            ];
        }

        return $this->sendTextToChatId($chatId, $message, $context);
    }

    public function sendTextToChatId(string $chatId, string $message, array $context = []): array
    {
        $session = $this->openWaSessionIdentifier();
        $apiKey = config('billing.yape.openwa.api_key');

        if (! $session) {
            $this->appendYapeDirectDebug('yape_openwa_send_text', [
                'chat_id' => $chatId,
                'message_context' => $context['message_context'] ?? null,
                'evidence_id' => $context['evidence_id'] ?? null,
                'api_url' => config('billing.yape.openwa.api_url'),
                'session' => null,
                'send_result' => false,
                'send_error' => 'missing_session',
                'http_status' => null,
                'context' => $context,
            ]);

            return [
                'success' => false,
                'error' => 'Sesión OpenWA no configurada.',
                'chat_id' => $chatId,
            ];
        }

        $url = rtrim((string) config('billing.yape.openwa.api_url'), '/')
            .'/sessions/'.rawurlencode($session).'/messages/send-text';

        try {
            $response = Http::withHeaders(array_filter([
                'X-API-Key' => $apiKey,
            ]))
                ->timeout((int) config('billing.yape.openwa.http_timeout', 20))
                ->post($url, [
                    'chatId' => $chatId,
                    'text' => $message,
                ]);

            if (! $response->successful()) {
                \Log::warning('OpenWA no aceptó mensaje de texto.', [
                    'chat_id' => $chatId,
                    'session' => $session,
                    'context' => $context,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 1000),
                ]);
            }

            $this->appendYapeDirectDebug('yape_openwa_send_text', [
                'chat_id' => $chatId,
                'message_context' => $context['message_context'] ?? null,
                'evidence_id' => $context['evidence_id'] ?? null,
                'api_url' => config('billing.yape.openwa.api_url'),
                'session' => $session,
                'send_result' => $response->successful(),
                'send_error' => $response->successful() ? null : mb_substr($response->body(), 0, 500),
                'http_status' => $response->status(),
                'context' => $context,
            ]);

            return [
                'success' => $response->successful(),
                'chat_id' => $chatId,
                'session' => $session,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
                'json' => $response->json(),
            ];
        } catch (\Throwable $exception) {
            \Log::warning('No se pudo enviar mensaje OpenWA.', [
                'chat_id' => $chatId,
                'context' => $context,
                'error' => $exception->getMessage(),
            ]);

            $this->appendYapeDirectDebug('yape_openwa_send_text', [
                'chat_id' => $chatId,
                'message_context' => $context['message_context'] ?? null,
                'evidence_id' => $context['evidence_id'] ?? null,
                'api_url' => config('billing.yape.openwa.api_url'),
                'session' => $session,
                'send_result' => false,
                'send_error' => $exception->getMessage(),
                'http_status' => null,
                'context' => $context,
            ]);

            return [
                'success' => false,
                'chat_id' => $chatId,
                'session' => $session,
                'error' => $exception->getMessage(),
            ];
        }
    }

    public function sendCustomerMenu(array $data): array
    {
        $chatId = $this->openWaChatId($data);

        if (! $chatId) {
            return ['success' => false, 'error' => 'missing_chat_id'];
        }

        return $this->sendListToChatId($chatId, [
            'title' => 'Noretel',
            'body' => 'Selecciona una opción para continuar.',
            'buttonText' => 'Ver opciones',
            'footer' => 'Atención automática Noretel',
            'sections' => [[
                'title' => '¿Cómo podemos ayudarte?',
                'rows' => [
                    ['id' => 'support_outage', 'title' => 'Reportar avería', 'description' => 'Sin internet o conexión lenta'],
                    ['id' => 'billing_payment', 'title' => 'Consultar pago', 'description' => 'Validar un pago Yape'],
                    ['id' => 'billing_receipt', 'title' => 'Solicitar recibo', 'description' => 'Constancia de un pago reciente'],
                ],
            ]],
        ]);
    }

    public function sendListToChatId(string $chatId, array $menu): array
    {
        $session = $this->openWaSessionIdentifier();
        $apiKey = config('billing.yape.openwa.api_key');

        if (! $session) {
            return ['success' => false, 'error' => 'missing_session', 'chat_id' => $chatId];
        }

        $url = rtrim((string) config('billing.yape.openwa.api_url'), '/')
            .'/sessions/'.rawurlencode($session).'/messages/send-list';

        try {
            $response = Http::withHeaders(array_filter(['X-API-Key' => $apiKey]))
                ->timeout((int) config('billing.yape.openwa.http_timeout', 20))
                ->post($url, array_merge(['chatId' => $chatId], $menu));

            return [
                'success' => $response->successful(),
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
                'json' => $response->json(),
            ];
        } catch (\Throwable $exception) {
            \Log::warning('No se pudo enviar menú interactivo OpenWA.', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);

            return ['success' => false, 'chat_id' => $chatId, 'error' => $exception->getMessage()];
        }
    }

    public function sendPaymentReceipt(array $data, ?YapePaymentIntent $intent, bool $force = false): array
    {
        if (! (bool) config('billing.yape.openwa.send_receipt_on_validation', true)) {
            return [
                'success' => false,
                'error' => 'El envío automático de constancias está desactivado.',
            ];
        }

        $intent = $intent?->fresh([
            'payment.invoice.contract.customer',
            'payment.invoice.contract.plan.service',
            'payment.invoice.items',
            'payment.invoice.payments',
            'payment.invoice.creditNotes',
            'clientEvidence',
        ]);

        $payment = $intent?->payment;
        $invoice = $payment?->invoice;

        if (! $intent || ! $payment || ! $invoice) {
            return [
                'success' => false,
                'error' => 'No se encontró el pago o el recibo asociado a la validación Yape.',
            ];
        }

        $receiptPayload = is_array($intent->clientEvidence?->payload)
            ? ($intent->clientEvidence->payload['payment_receipt'] ?? [])
            : [];

        if (! $force && ! empty($receiptPayload['sent_at'])) {
            return [
                'success' => true,
                'skipped' => true,
                'reason' => 'already_sent',
                'sent_at' => $receiptPayload['sent_at'],
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ];
        }

        $data['whatsapp_phone'] = $data['whatsapp_phone']
            ?? $intent->whatsapp_phone
            ?? $invoice->contract?->customer?->phone;

        $chatId = $this->openWaChatId($data);
        $session = $this->openWaSessionIdentifier();
        $apiKey = config('billing.yape.openwa.api_key');

        if (! $chatId) {
            return [
                'success' => false,
                'error' => 'No se pudo resolver el WhatsApp del cliente para enviar la constancia.',
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ];
        }

        if (! $session) {
            return [
                'success' => false,
                'error' => 'No hay una sesión OpenWA activa para enviar la constancia.',
                'chat_id' => $chatId,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ];
        }

        $fileNumber = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $invoice->invoice_number);
        $filename = "constancia-pago-{$fileNumber}.pdf";

        try {
            $receipt = app(PaymentReceiptService::class)->build($payment, $intent);

            $pdfBinary = Pdf::loadView('billing::payments.receipt-pdf', [
                'receipt' => $receipt,
                'payment' => $receipt['payment'],
                'invoice' => $receipt['invoice'],
                'intent' => $intent,
            ])->setPaper([0, 0, 226.77, 1000], 'portrait')->output();

            $disk = config('billing.yape.storage_disk', 'local');
            $receiptPath = trim((string) config('billing.yape.payment_receipt_dir', 'yape/payment-receipts'), '/')
                .'/'.now()->format('YmdHis').'-'.$filename;
            Storage::disk($disk)->put($receiptPath, $pdfBinary);

            $url = rtrim((string) config('billing.yape.openwa.api_url'), '/')
                .'/sessions/'.rawurlencode($session).'/messages/send-document';

            $response = Http::withHeaders(array_filter([
                'X-API-Key' => $apiKey,
            ]))
                ->timeout((int) config('billing.yape.openwa.http_timeout', 20))
                ->post($url, [
                    'chatId' => $chatId,
                    'base64' => base64_encode($pdfBinary),
                    'mimetype' => 'application/pdf',
                    'filename' => $filename,
                    'caption' => 'Te enviamos tu constancia de pago. Gracias por pagar en Noretel.',
                ]);

            if (! $response->successful()) {
                \Log::warning('OpenWA no aceptó constancia PDF Yape.', [
                    'chat_id' => $chatId,
                    'session' => $session,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'receipt_path' => $receiptPath,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 1000),
                ]);

                return [
                    'success' => false,
                    'error' => 'OpenWA rechazó la constancia PDF.',
                    'status' => $response->status(),
                    'chat_id' => $chatId,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                ];
            }

            if ($intent->clientEvidence) {
                $payload = is_array($intent->clientEvidence->payload) ? $intent->clientEvidence->payload : [];
                $payload['payment_receipt'] = [
                    'sent_at' => now()->toDateTimeString(),
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'filename' => $filename,
                    'path' => $receiptPath,
                    'chat_id' => $chatId,
                    'openwa_response' => $response->json(),
                ];
                $intent->clientEvidence->update(['payload' => $payload]);
            }

            return [
                'success' => true,
                'chat_id' => $chatId,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'filename' => $filename,
                'path' => $receiptPath,
            ];
        } catch (\Throwable $exception) {
            \Log::warning('No se pudo generar o enviar constancia PDF Yape por OpenWA.', [
                'chat_id' => $chatId,
                'intent_id' => $intent->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'No se pudo generar o enviar la constancia PDF: '.$exception->getMessage(),
                'chat_id' => $chatId,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ];
        }
    }

    public function sendPaymentReceiptDirect(\Modules\Billing\Entities\Payment $payment, string $phone): array
    {
        if (! (bool) config('billing.yape.openwa.send_receipt_on_validation', true)) {
            return [
                'success' => false,
                'error' => 'El envío automático de recibos está desactivado en la configuración.',
            ];
        }

        $payment->loadMissing([
            'invoice.contract.customer',
            'invoice.contract.plan.service',
            'invoice.items',
            'invoice.payments',
            'invoice.creditNotes',
        ]);

        $invoice = $payment->invoice;

        if (! $payment || ! $invoice) {
            return [
                'success' => false,
                'error' => 'No se encontró el pago o su recibo asociado.',
            ];
        }

        $chatId = $this->chatIdFromPhone($phone);
        $session = $this->openWaSessionIdentifier();
        $apiKey = config('billing.yape.openwa.api_key');

        if (! $chatId) {
            return [
                'success' => false,
                'error' => 'Número de teléfono inválido para WhatsApp.',
            ];
        }

        if (! $session) {
            return [
                'success' => false,
                'error' => 'No hay una sesión activa de OpenWA configurada.',
            ];
        }

        $fileNumber = preg_replace('/[^A-Za-z0-9\-]+/', '-', (string) $invoice->invoice_number);
        $filename = "constancia-pago-{$fileNumber}.pdf";

        try {
            $receipt = app(PaymentReceiptService::class)->build($payment, null);

            $pdfBinary = Pdf::loadView('billing::payments.receipt-pdf', [
                'receipt' => $receipt,
                'payment' => $receipt['payment'],
                'invoice' => $receipt['invoice'],
                'intent' => null,
            ])->setPaper([0, 0, 226.77, 1000], 'portrait')->output();

            $disk = config('billing.yape.storage_disk', 'local');
            $receiptPath = trim((string) config('billing.yape.payment_receipt_dir', 'yape/payment-receipts'), '/')
                .'/'.now()->format('YmdHis').'-'.$filename;
            Storage::disk($disk)->put($receiptPath, $pdfBinary);

            $url = rtrim((string) config('billing.yape.openwa.api_url'), '/')
                .'/sessions/'.rawurlencode($session).'/messages/send-document';

            $response = Http::withHeaders(array_filter([
                'X-API-Key' => $apiKey,
            ]))
                ->timeout((int) config('billing.yape.openwa.http_timeout', 20))
                ->post($url, [
                    'chatId' => $chatId,
                    'base64' => base64_encode($pdfBinary),
                    'mimetype' => 'application/pdf',
                    'filename' => $filename,
                    'caption' => 'Te enviamos tu constancia de pago. Gracias por confiar en Noretel.',
                ]);

            if (! $response->successful()) {
                \Log::warning('OpenWA no aceptó constancia PDF Yape directa.', [
                    'chat_id' => $chatId,
                    'session' => $session,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'receipt_path' => $receiptPath,
                    'status' => $response->status(),
                ]);

                return [
                    'success' => false,
                    'error' => 'El servidor OpenWA rechazó el documento (Código HTTP: '.$response->status().').',
                ];
            }

            return [
                'success' => true,
            ];
        } catch (\Throwable $exception) {
            \Log::warning('No se pudo generar o enviar constancia PDF Yape directa por OpenWA.', [
                'chat_id' => $chatId,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Error al generar o enviar el PDF: '.$exception->getMessage(),
            ];
        }
    }

    public function openWaDataFromClientEvidence(?YapeClientEvidence $evidence): array
    {
        if (! $evidence) {
            return [];
        }

        $payload = is_array($evidence->payload) ? $evidence->payload : [];
        $openWaPayload = data_get($payload, 'payload.openwa');

        if (! is_array($openWaPayload)) {
            $openWaPayload = data_get($payload, 'payload');
        }

        if (! is_array($openWaPayload)) {
            $openWaPayload = $payload;
        }

        return [
            'whatsapp_phone' => $evidence->whatsapp_phone,
            'source_message_id' => $evidence->source_message_id,
            'source_chat_name' => $evidence->source_chat_name,
            'payload' => $openWaPayload,
        ];
    }

    protected function openWaSessionIdentifier(): ?string
    {
        $configured = trim((string) config('billing.yape.openwa.session'));

        if ($configured === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('billing.yape.openwa.api_url'), '/');
        $apiKey = config('billing.yape.openwa.api_key');
        $headers = array_filter([
            'X-API-Key' => $apiKey,
        ]);
        $timeout = (int) config('billing.yape.openwa.http_timeout', 20);

        try {
            $direct = Http::withHeaders($headers)
                ->timeout($timeout)
                ->get($baseUrl.'/sessions/'.rawurlencode($configured));

            if ($direct->successful()) {
                return $configured;
            }

            $sessions = Http::withHeaders($headers)
                ->timeout($timeout)
                ->get($baseUrl.'/sessions');

            if (! $sessions->successful()) {
                \Log::warning('No se pudo resolver sesión OpenWA para flujo Yape DNI.', [
                    'configured_session' => $configured,
                    'status' => $sessions->status(),
                    'body' => mb_substr($sessions->body(), 0, 1000),
                ]);

                return $configured;
            }

            $items = $sessions->json();

            if (! is_array($items)) {
                return $configured;
            }

            foreach ($items as $session) {
                if (! is_array($session)) {
                    continue;
                }

                if (($session['id'] ?? null) === $configured || ($session['name'] ?? null) === $configured) {
                    return $session['id'] ?? $configured;
                }
            }
        } catch (\Throwable $exception) {
            \Log::warning('Error resolviendo sesión OpenWA para flujo Yape DNI.', [
                'configured_session' => $configured,
                'error' => $exception->getMessage(),
            ]);
        }

        return $configured;
    }

    public function resolveConfirmedPhoneForChatId(?string $chatId): array
    {
        $chatId = strtolower(trim((string) $chatId));
        $unresolved = [
            'resolved' => false,
            'chat_id' => $chatId ?: null,
            'phone' => null,
            'source' => null,
            'confidence' => null,
            'session' => null,
            'confirmed_at' => null,
        ];

        if ($chatId === '') {
            return $unresolved;
        }

        if (str_ends_with($chatId, '@c.us')) {
            $phone = $this->confirmedPhoneFromCUsId($chatId);

            return $phone ? array_merge($unresolved, [
                'resolved' => true,
                'phone' => $phone,
                'source' => 'chat_id_c_us',
                'confidence' => 'high',
            ]) : $unresolved;
        }

        if (! str_ends_with($chatId, '@lid')) {
            return $unresolved;
        }

        $session = $this->openWaSessionIdentifier();

        if (! $session) {
            return $unresolved;
        }

        $url = rtrim((string) config('billing.yape.openwa.api_url'), '/')
            .'/sessions/'.rawurlencode($session)
            .'/contacts/resolve/'.rawurlencode($chatId);

        try {
            $response = Http::withHeaders(array_filter([
                'X-API-Key' => config('billing.yape.openwa.api_key'),
            ]))
                ->timeout(min(5, (int) config('billing.yape.openwa.http_timeout', 20)))
                ->get($url);

            if (! $response->successful()) {
                return array_merge($unresolved, ['session' => $session]);
            }

            $payload = $response->json();
            $identity = data_get($payload, 'identity', data_get($payload, 'data.identity'));
            $returnedChatId = strtolower(trim((string) data_get($payload, 'chatId', data_get($payload, 'data.chatId'))));
            $resolvedLid = strtolower(trim((string) data_get($identity, 'lid')));
            $phoneId = strtolower(trim((string) data_get($identity, 'id')));
            $phone = $this->confirmedPhoneFromCUsId($phoneId);
            $phoneNumber = preg_replace('/\D+/', '', (string) data_get($identity, 'phoneNumber'));
            $source = data_get($identity, 'source');
            $confidence = data_get($identity, 'confidence');

            $confirmed = data_get($payload, 'resolved', data_get($payload, 'data.resolved')) === true
                && $returnedChatId === $chatId
                && $resolvedLid === $chatId
                && $phone !== null
                && $phone === $phoneNumber
                && $source === 'whatsapp_web_js.getContactLidAndPhone'
                && $confidence === 'high';

            if (! $confirmed) {
                return array_merge($unresolved, ['session' => $session]);
            }

            return [
                'resolved' => true,
                'chat_id' => $chatId,
                'phone' => $phone,
                'source' => $source,
                'confidence' => $confidence,
                'session' => $session,
                'confirmed_at' => data_get($payload, 'confirmedAt', data_get($payload, 'data.confirmedAt')),
            ];
        } catch (\Throwable $exception) {
            \Log::notice('No se pudo resolver un chat LID mediante OpenWA.', [
                'chat_id' => $chatId,
                'session' => $session,
                'error' => $exception->getMessage(),
            ]);

            return array_merge($unresolved, ['session' => $session]);
        }
    }

    protected function openWaChatId(array $data): ?string
    {
        $payload = $data['payload'] ?? [];
        $message = is_array($payload)
            ? ($payload['message'] ?? $payload['data']['message'] ?? $payload['payload']['message'] ?? $payload['data'] ?? $payload['payload'] ?? null)
            : null;

        if (is_array($message)) {
            foreach (['from', 'chatId', 'sender.id', 'author', 'participant'] as $path) {
                $value = $this->arrayGet($message, $path);
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
        }

        $sourceChatName = trim((string) ($data['source_chat_name'] ?? ''));

        if ($sourceChatName !== '') {
            if (str_contains($sourceChatName, '@')) {
                return $sourceChatName;
            }

            $sourceChatId = $this->chatIdFromPhone($sourceChatName);

            if ($sourceChatId) {
                return $sourceChatId;
            }
        }

        foreach (['whatsapp_phone', 'phone', 'target_phone'] as $phoneKey) {
            $chatId = $this->chatIdFromPhone($data[$phoneKey] ?? null);

            if ($chatId) {
                return $chatId;
            }
        }

        return null;
    }

    public function chatIdFromPhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        if (str_contains($phone, '@')) {
            return $phone;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        $countryCode = preg_replace('/\D+/', '', (string) config('billing.yape.openwa.default_country_code', '51'));

        if (strlen($digits) === 9 && $countryCode !== '') {
            $digits = $countryCode.$digits;
        }

        if (strlen($digits) < 10) {
            return null;
        }

        return $digits.'@c.us';
    }

    protected function confirmedPhoneFromCUsId(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (! preg_match('/^(\d{10,15})@c\.us$/', $value, $matches)) {
            return null;
        }

        return $matches[1];
    }

    protected function arrayGet(array $payload, string $path): mixed
    {
        $current = $payload;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    protected function appendYapeDirectDebug(string $event, array $context = []): void
    {
        try {
            file_put_contents(
                storage_path('logs/openwa-yape-direct-debug.log'),
                json_encode(array_merge([
                    'event' => $event,
                    'date' => now()->toDateTimeString(),
                ], $context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
            // La traza directa nunca debe romper el envío.
        }
    }
}
