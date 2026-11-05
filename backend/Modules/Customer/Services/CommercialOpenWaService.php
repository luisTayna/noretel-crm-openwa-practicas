<?php

namespace Modules\Customer\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CommercialOpenWaService
{
    public function sendTextMessage(string $chatId, string $message): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'error' => $this->configurationError(),
            ];
        }

        $session = trim((string) config('customer.commercial_openwa.session'));
        $apiUrl = rtrim((string) config('customer.commercial_openwa.api_url'), '/');
        $apiKey = config('customer.commercial_openwa.api_key');

        try {
            $response = Http::withHeaders(array_filter([
                'x-api-key' => $apiKey,
            ]))
                ->timeout((int) config('customer.commercial_openwa.http_timeout', 20))
                ->post("{$apiUrl}/sessions/" . rawurlencode($session) . "/messages/send-text", [
                    'chatId' => $chatId,
                    'text' => $message,
                ]);

            if (! $response->successful()) {
                Log::channel('openwa_commercial')->warning('commercial_openwa_send_failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'chat_id' => $chatId,
                    'session' => $session,
                ]);
            }

            Log::channel('openwa_commercial')->info('commercial_openwa_send_result', [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'chat_id' => $chatId,
                'session' => $session,
            ]);

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'json' => $response->json(),
            ];
        } catch (\Throwable $e) {
            Log::channel('openwa_commercial')->warning('commercial_openwa_send_exception', [
                'chat_id' => $chatId,
                'session' => $session,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('customer.commercial_openwa.enabled', false)) {
            return false;
        }

        $apiUrl = trim((string) config('customer.commercial_openwa.api_url'));
        $apiKey = trim((string) config('customer.commercial_openwa.api_key'));
        $session = trim((string) config('customer.commercial_openwa.session'));
        $webhookSecret = trim((string) config('customer.commercial_openwa.webhook_secret'));
        $yapeSession = trim((string) config('billing.yape.openwa.session'));

        return $apiUrl !== ''
            && $apiKey !== ''
            && $session !== ''
            && $webhookSecret !== ''
            && ($yapeSession === '' || $session !== $yapeSession);
    }

    public function configurationError(): string
    {
        if (! (bool) config('customer.commercial_openwa.enabled', false)) {
            return 'OpenWA comercial deshabilitado.';
        }

        $missing = [];

        foreach ([
            'api_url' => 'COMMERCIAL_OPENWA_API_URL',
            'api_key' => 'COMMERCIAL_OPENWA_API_KEY',
            'session' => 'COMMERCIAL_OPENWA_SESSION',
            'webhook_secret' => 'COMMERCIAL_OPENWA_WEBHOOK_SECRET',
        ] as $configKey => $envName) {
            if (trim((string) config("customer.commercial_openwa.{$configKey}")) === '') {
                $missing[] = $envName;
            }
        }

        $commercialSession = trim((string) config('customer.commercial_openwa.session'));
        $yapeSession = trim((string) config('billing.yape.openwa.session'));

        if ($commercialSession !== '' && $yapeSession !== '' && $commercialSession === $yapeSession) {
            $missing[] = 'COMMERCIAL_OPENWA_SESSION debe ser distinta de YAPE_OPENWA_SESSION';
        }

        return $missing
            ? 'OpenWA comercial no configurado correctamente: ' . implode(', ', $missing) . '.'
            : 'OpenWA comercial no configurado correctamente.';
    }
}
