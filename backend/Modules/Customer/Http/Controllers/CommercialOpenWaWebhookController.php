<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Customer\Services\CommercialLeadService;
use Modules\Customer\Services\CommercialOpenWaService;

class CommercialOpenWaWebhookController extends Controller
{
    public function __construct(
        protected CommercialLeadService $commercialLeadService,
        protected CommercialOpenWaService $commercialOpenWaService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $botEnabled = (bool) config('customer.commercial_openwa.enabled', false);

        Log::channel('openwa_commercial')->info('commercial_webhook_received', [
            'bot_enabled' => $botEnabled,
            'expected_session_id' => config('customer.commercial_openwa.session'),
            'payload_session_id' => $this->payloadSessionId($request->all()),
            'raw_chat_id' => data_get($request->all(), 'data.chatId'),
            'raw_from' => data_get($request->all(), 'data.from'),
            'raw_to' => data_get($request->all(), 'data.to'),
            'body' => data_get($request->all(), 'data.body'),
            'has_secret_header' => $request->headers->has('X-OpenWA-Webhook-Secret')
                || $request->headers->has('X-Commercial-OpenWA-Secret'),
        ]);

        if (! (bool) config('customer.commercial_openwa.enabled', false)) {
            Log::channel('openwa_commercial')->info('commercial_webhook_ignored', [
                'ignored_reason' => 'commercial_openwa_disabled',
            ]);

            return response()->json([
                'success' => true,
                'processed' => false,
                'reason' => 'commercial_openwa_disabled',
            ]);
        }

        $this->guardWebhook($request);
        $this->guardIsolatedCommercialSession();

        $normalized = $this->normalizePayload($request->all());

        if (! $normalized['should_process']) {
            Log::channel('openwa_commercial')->info('commercial_webhook_ignored', [
                'expected_session_id' => config('customer.commercial_openwa.session'),
                'payload_session_id' => $this->payloadSessionId($request->all()),
                'message_id' => data_get($normalized, 'message.message_id'),
                'chat_id_normalized' => data_get($normalized, 'message.chat_id'),
                'ignored_reason' => $normalized['reason'],
            ]);

            return response()->json([
                'success' => true,
                'processed' => false,
                'reason' => $normalized['reason'],
            ]);
        }

        $messageId = (string) ($normalized['message']['message_id'] ?? '');
        if ($messageId !== '' && ! Cache::add('openwa_commercial_message:' . sha1($messageId), true, now()->addDay())) {
            Log::channel('openwa_commercial')->info('commercial_webhook_ignored', [
                'message_id' => $messageId,
                'chat_id_normalized' => $normalized['message']['chat_id'] ?? null,
                'ignored_reason' => 'duplicate_message_ignored',
                'dedup_status' => 'hit',
            ]);

            return response()->json([
                'success' => true,
                'processed' => false,
                'reason' => 'duplicate_message_ignored',
            ]);
        }

        $rateLimitKey = 'openwa_commercial_chat_rate:' . sha1((string) ($normalized['message']['chat_id'] ?? ''));
        if (! Cache::add($rateLimitKey, true, now()->addSeconds(2))) {
            Log::channel('openwa_commercial')->info('commercial_webhook_ignored', [
                'message_id' => $messageId ?: null,
                'chat_id_normalized' => $normalized['message']['chat_id'] ?? null,
                'ignored_reason' => 'chat_rate_limited',
                'rate_limit_status' => 'hit',
            ]);

            return response()->json([
                'success' => true,
                'processed' => false,
                'reason' => 'chat_rate_limited',
            ]);
        }

        $result = $this->commercialLeadService->captureFromWhatsApp($normalized['message']);

        if (($result['processed'] ?? false) && filled($normalized['message']['chat_id'] ?? null)) {
            $sendResult = $this->commercialOpenWaService->sendTextMessage(
                $normalized['message']['chat_id'],
                $result['reply']
            );
        }

        Log::channel('openwa_commercial')->info('commercial_webhook_processed', [
            'expected_session_id' => config('customer.commercial_openwa.session'),
            'payload_session_id' => $this->payloadSessionId($request->all()),
            'message_id' => $normalized['message']['message_id'] ?? null,
            'raw_chat_id' => $normalized['message']['raw_chat_id'] ?? null,
            'raw_from' => $normalized['message']['raw_from'] ?? null,
            'raw_to' => $normalized['message']['raw_to'] ?? null,
            'chat_id_normalized' => $normalized['message']['chat_id'] ?? null,
            'suffix_detected' => $normalized['message']['chat_suffix'] ?? null,
            'is_lid' => $normalized['message']['is_lid'] ?? false,
            'is_cus' => $normalized['message']['is_cus'] ?? false,
            'is_group' => $normalized['message']['is_group'] ?? false,
            'from_me' => $normalized['message']['from_me'] ?? false,
            'text' => $normalized['message']['text'] ?? null,
            'intent_detected' => $result['interest'] ?? null,
            'ignored_reason' => $result['processed'] ?? false ? null : ($result['reason'] ?? null),
            'response_sent' => $sendResult['ok'] ?? false,
            'dedup_status' => $messageId !== '' ? 'reserved' : 'no_message_id',
            'rate_limit_status' => 'reserved',
        ]);

        return response()->json([
            'success' => true,
            'processed' => (bool) ($result['processed'] ?? false),
            'reason' => $result['reason'] ?? null,
            'lead_id' => $result['lead']->id ?? null,
            'interest' => $result['interest'] ?? null,
            'openwa_sent' => $sendResult['ok'] ?? null,
        ]);
    }

    protected function guardWebhook(Request $request): void
    {
        $configuredSecret = (string) config('customer.commercial_openwa.webhook_secret');

        if ($configuredSecret === '') {
            abort(500, 'Secreto webhook comercial OpenWA no configurado.');
        }

        $providedSecret = $request->header('X-OpenWA-Webhook-Secret')
            ?: $request->header('X-Commercial-OpenWA-Secret')
            ?: $request->query('secret');

        if (! hash_equals($configuredSecret, (string) $providedSecret)) {
            Log::channel('openwa_commercial')->warning('commercial_webhook_rejected', [
                'ignored_reason' => 'invalid_webhook_secret',
                'has_provided_secret' => filled($providedSecret),
            ]);

            abort(403, 'Secreto webhook comercial OpenWA inválido.');
        }
    }

    protected function guardIsolatedCommercialSession(): void
    {
        if ($this->commercialOpenWaService->isConfigured()) {
            return;
        }

        abort(503, $this->commercialOpenWaService->configurationError());
    }

    protected function normalizePayload(array $payload): array
    {
        $message = $this->messagePayload($payload);
        $expectedSessionId = trim((string) config('customer.commercial_openwa.session'));
        $payloadSessionId = $this->payloadSessionId($payload);

        if ($payloadSessionId && $expectedSessionId !== '' && $payloadSessionId !== $expectedSessionId) {
            return [
                'should_process' => false,
                'reason' => 'session_mismatch',
            ];
        }

        if (! $message) {
            return [
                'should_process' => false,
                'reason' => 'missing_message',
            ];
        }

        if ($this->isOutgoing($message)) {
            return [
                'should_process' => false,
                'reason' => 'outgoing_message',
                'message' => $this->diagnosticMessageContext($message, null),
            ];
        }

        if ($this->messageIsGroup($message)) {
            return [
                'should_process' => false,
                'reason' => 'group_message',
                'message' => $this->diagnosticMessageContext($message, null),
            ];
        }

        $chatId = $this->firstString($message, [
            'chatId',
            'remoteJid',
            'id.remote',
            'id.remoteJid',
            'key.remoteJid',
            'from',
            'sender.id',
            'author',
            'participant',
        ]);
        $normalizedChatId = $this->normalizeChatId($chatId);
        $suffix = $this->chatSuffix($normalizedChatId);

        if (! $normalizedChatId || $suffix === '@g.us') {
            return [
                'should_process' => false,
                'reason' => 'group_or_invalid_chat',
                'message' => $this->diagnosticMessageContext($message, $normalizedChatId),
            ];
        }

        if (! in_array($suffix, ['@c.us', '@lid'], true)) {
            return [
                'should_process' => false,
                'reason' => 'non_private_chat',
                'message' => $this->diagnosticMessageContext($message, $normalizedChatId),
            ];
        }

        if ($this->hasMedia($message)) {
            return [
                'should_process' => false,
                'reason' => 'media_ignored',
                'message' => $this->diagnosticMessageContext($message, $normalizedChatId),
            ];
        }

        return [
            'should_process' => true,
            'message' => [
                'phone' => $normalizedChatId,
                'chat_id' => $normalizedChatId,
                'text' => $this->firstString($message, ['body', 'text', 'caption', 'message.body', 'message.text', 'message.caption']),
                'display_name' => $this->firstString($message, ['chat.name', 'chatName', 'sender.name', 'notifyName', 'pushname', 'from']),
                'message_id' => $this->messageDedupKey($payload, $message, $normalizedChatId),
                'raw_chat_id' => $chatId,
                'raw_from' => $this->firstString($message, ['from']),
                'raw_to' => $this->firstString($message, ['to']),
                'chat_suffix' => $suffix,
                'is_lid' => $suffix === '@lid',
                'is_cus' => $suffix === '@c.us',
                'is_group' => false,
                'from_me' => false,
                'payload' => $payload,
            ],
        ];
    }

    protected function messagePayload(array $payload): ?array
    {
        foreach (['data.message', 'payload.message', 'message', 'data', 'payload'] as $path) {
            $value = $this->get($payload, $path);
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function isOutgoing(array $message): bool
    {
        $fromMe = $this->firstValue($message, ['fromMe', 'id.fromMe', 'isFromMe', 'key.fromMe', 'message.fromMe']);

        return $fromMe === true || $fromMe === 'true' || $fromMe === 1 || $fromMe === '1';
    }

    protected function messageIsGroup(array $message): bool
    {
        $isGroup = $this->firstValue($message, ['isGroup', 'group', 'chat.isGroup']);

        return $isGroup === true || $isGroup === 'true' || $isGroup === 1 || $isGroup === '1';
    }

    protected function hasMedia(array $message): bool
    {
        $type = strtolower((string) $this->firstString($message, ['type', 'media.type', 'message.type']));
        $mime = strtolower((string) $this->firstString($message, ['mimetype', 'mimeType', 'media.mimetype', 'media.mimeType']));
        $hasMedia = $this->firstValue($message, ['hasMedia', 'media', 'mediaUrl', 'downloadUrl', 'image_base64', 'imageBase64']);

        return $type === 'image'
            || str_starts_with($mime, 'image/')
            || ($hasMedia && in_array($type, ['', 'image', 'media'], true));
    }

    protected function payloadSessionId(array $payload): ?string
    {
        return $this->firstString($payload, [
            'sessionId',
            'session_id',
            'session.id',
            'session.name',
            'data.sessionId',
            'data.session_id',
            'data.session.id',
            'payload.sessionId',
            'payload.session_id',
            'payload.session.id',
        ]);
    }

    protected function normalizeChatId(?string $chatId): ?string
    {
        $chatId = is_string($chatId) ? trim($chatId) : '';

        if ($chatId === '') {
            return null;
        }

        if (preg_match('/@(c|g)\.us$/i', $chatId)) {
            $digits = preg_replace('/\D+/', '', preg_replace('/@(c|g)\.us$/i', '', $chatId));
            $suffix = strtolower(substr($chatId, -5));

            return $digits ? $digits . $suffix : null;
        }

        if (str_ends_with(strtolower($chatId), '@lid')) {
            $lid = trim(substr($chatId, 0, -4));
            $safeLid = preg_replace('/[^A-Za-z0-9._-]+/', '', $lid);

            return $safeLid ? $safeLid . '@lid' : null;
        }

        $digits = preg_replace('/\D+/', '', $chatId);

        if (strlen((string) $digits) === 9 && str_starts_with((string) $digits, '9')) {
            $digits = '51' . $digits;
        }

        return $digits ? $digits . '@c.us' : null;
    }

    protected function chatSuffix(?string $chatId): ?string
    {
        $chatId = strtolower((string) $chatId);

        foreach (['@c.us', '@g.us', '@lid'] as $suffix) {
            if (str_ends_with($chatId, $suffix)) {
                return $suffix;
            }
        }

        return null;
    }

    protected function diagnosticMessageContext(array $message, ?string $normalizedChatId): array
    {
        $suffix = $this->chatSuffix($normalizedChatId);

        return [
            'message_id' => $this->messageDedupKey([], $message, $normalizedChatId),
            'raw_chat_id' => $this->firstString($message, ['chatId', 'remoteJid', 'id.remote', 'id.remoteJid', 'key.remoteJid']),
            'raw_from' => $this->firstString($message, ['from']),
            'raw_to' => $this->firstString($message, ['to']),
            'chat_id' => $normalizedChatId,
            'chat_suffix' => $suffix,
            'is_lid' => $suffix === '@lid',
            'is_cus' => $suffix === '@c.us',
            'is_group' => $this->messageIsGroup($message) || $suffix === '@g.us',
            'from_me' => $this->isOutgoing($message),
            'text' => $this->firstString($message, ['body', 'text', 'caption', 'message.body', 'message.text', 'message.caption']),
        ];
    }

    protected function messageDedupKey(array $payload, array $message, ?string $normalizedChatId): ?string
    {
        foreach ([
            'id._serialized',
            'id.id',
            'messageId',
            'data.id',
            'id',
        ] as $path) {
            $value = str_starts_with($path, 'data.')
                ? $this->get($payload, $path)
                : $this->get($message, $path);

            if (is_string($value) && trim($value) !== '' && trim($value) !== 'msg_unknown') {
                return trim($value);
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        $deliveryId = $this->firstString($payload, ['deliveryId', 'delivery_id']);
        if ($deliveryId && $deliveryId !== 'msg_unknown') {
            return $deliveryId;
        }

        $body = $this->firstString($message, ['body', 'text', 'caption', 'message.body', 'message.text', 'message.caption']) ?? '';
        $timestamp = $this->firstString($message, ['timestamp', 't', 'createdAt', 'date']) ?? '';
        $sessionId = $this->payloadSessionId($payload) ?? '';

        if ($normalizedChatId || $body || $timestamp || $sessionId) {
            return 'hash_' . sha1($sessionId . '|' . $normalizedChatId . '|' . $body . '|' . $timestamp);
        }

        return null;
    }

    protected function firstString(array $payload, array $paths): ?string
    {
        $value = $this->firstValue($payload, $paths);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    protected function firstValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->get($payload, $path);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function get(array $payload, string $path): mixed
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
}
