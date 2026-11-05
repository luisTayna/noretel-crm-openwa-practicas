<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\YapeClientEvidence;
use Modules\Billing\Entities\YapePaymentIntent;

class CreateTestYapeIntentCommand extends Command
{
    protected $signature = 'yape:create-test-intent
        {--amount= : Monto esperado del Yape, por ejemplo 6.80}
        {--operation= : Número de operación de la captura cliente}
        {--security= : Código de seguridad de la captura/notificación, por ejemplo 249}
        {--name=Cliente Prueba : Nombre del cliente/remitente esperado}
        {--phone= : WhatsApp del cliente en formato local o internacional}
        {--confidence=0.99 : Confianza OCR simulada}
        {--expires-minutes=1440 : Minutos de vigencia del intent}';

    protected $description = 'Crea un YapePaymentIntent con evidencia cliente asociada para probar el receptor por notificaciones.';

    public function handle(): int
    {
        $amount = $this->normalizeAmount($this->option('amount'));
        $operationCode = $this->normalizeDigits($this->option('operation'));
        $securityCode = $this->normalizeDigits($this->option('security'));
        $customerName = trim((string) $this->option('name')) ?: 'Cliente Prueba';
        $phone = $this->normalizePhone((string) $this->option('phone'));
        $confidence = max(0, min(1, (float) $this->option('confidence')));
        $expiresMinutes = max(1, (int) $this->option('expires-minutes'));

        if ($amount <= 0) {
            $this->error('Debe enviar --amount mayor a cero. Ejemplo: --amount=6.80');
            return self::FAILURE;
        }

        if (!$operationCode) {
            $this->error('Debe enviar --operation con el número de operación. Ejemplo: --operation=22155100');
            return self::FAILURE;
        }

        if (!$securityCode) {
            $this->error('Debe enviar --security con el código de seguridad. Ejemplo: --security=249');
            return self::FAILURE;
        }

        $intent = DB::transaction(function () use ($amount, $operationCode, $securityCode, $customerName, $phone, $confidence, $expiresMinutes) {
            $intent = YapePaymentIntent::create([
                'expected_amount' => $amount,
                'payment_mode' => 'single',
                'whatsapp_phone' => $phone,
                'customer_name' => $customerName,
                'status' => YapePaymentIntent::STATUS_WAITING_CLIENT,
                'operation_code' => $operationCode,
                'security_code' => $securityCode,
                'receiver_account_name' => config('billing.yape.receiver_name'),
                'receiver_account_phone' => $this->normalizePhone((string) config('billing.yape.receiver_phone')),
                'source_payload' => [
                    'testing' => true,
                    'created_by_command' => 'yape:create-test-intent',
                    'payment_method' => 'yape',
                    'amount' => $amount,
                ],
                'expires_at' => now()->addMinutes($expiresMinutes),
            ]);

            $rawText = implode(PHP_EOL, [
                'Yape de prueba',
                'Cliente: ' . $customerName,
                'Monto: S/ ' . number_format($amount, 2, '.', ''),
                'Código de seguridad: ' . $securityCode,
                'Número de operación: ' . $operationCode,
                'Fecha: ' . now()->format('d/m/Y H:i'),
            ]);

            $evidence = YapeClientEvidence::create([
                'yape_payment_intent_id' => $intent->id,
                'whatsapp_phone' => $phone,
                'source_message_id' => 'test-intent-' . $intent->id,
                'source_chat_name' => $customerName,
                'image_path' => '',
                'raw_text' => $rawText,
                'parsed_amount' => $amount,
                'operation_code' => $operationCode,
                'security_code' => $securityCode,
                'occurred_at' => now(),
                'receiver_name' => config('billing.yape.receiver_name'),
                'receiver_phone' => $this->normalizePhone((string) config('billing.yape.receiver_phone')),
                'confidence' => $confidence,
                'status' => 'attached',
                'payload' => [
                    'testing' => true,
                    'created_by_command' => 'yape:create-test-intent',
                ],
                'processed_at' => now(),
            ]);

            $intent->update([
                'client_evidence_id' => $evidence->id,
                'status' => YapePaymentIntent::STATUS_WAITING_RECEIVER,
            ]);

            return $intent->fresh(['clientEvidence']);
        });

        $this->info('Intent Yape de prueba creado y listo para el receptor por notificaciones.');
        $this->line('intent_id: ' . $intent->id);
        $this->line('intent_number: ' . $intent->intent_number);
        $this->line('status: ' . $intent->status);
        $this->line('expected_amount: ' . $intent->expected_amount);
        $this->line('operation_code: ' . $intent->operation_code);
        $this->line('security_code: ' . $intent->security_code);
        $this->line('client_evidence_id: ' . $intent->client_evidence_id);

        return self::SUCCESS;
    }

    protected function normalizeAmount(mixed $amount): float
    {
        return round((float) str_replace(',', '.', (string) $amount), 2);
    }

    protected function normalizeDigits(mixed $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits ?: null;
    }

    protected function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (!$digits) {
            return null;
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            return '51' . $digits;
        }

        return $digits;
    }
}
