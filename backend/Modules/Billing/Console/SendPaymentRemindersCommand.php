<?php

namespace Modules\Billing\Console;

use Illuminate\Console\Command;
use Modules\Billing\Services\PaymentReminderService;

class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'billing:send-payment-reminders
        {--limit= : Cantidad máxima de mensajes a enviar en esta corrida}
        {--ignore-hours : Permite enviar en una corrida manual aunque esté fuera del horario permitido}
        {--ignore-interval : Permite ignorar el intervalo aleatorio solo para pruebas controladas}';

    protected $description = 'Envía recordatorios de pago pendientes por OpenWA en bloques.';

    public function handle(PaymentReminderService $paymentReminderService): int
    {
        $limit = (int) ($this->option('limit') ?: config('billing.payment_reminders.batch_size', 1));
        $result = $paymentReminderService->sendPendingReminders(
            $limit,
            (bool) $this->option('ignore-hours'),
            (bool) $this->option('ignore-interval')
        );

        $this->info("Batch {$result['batch_id']}: enviados {$result['sent']}, fallidos {$result['failed']}, omitidos {$result['skipped']}, total {$result['total']}.");

        if (! empty($result['message'])) {
            $this->line($result['message']);
        }

        foreach ($result['details'] ?? [] as $detail) {
            $this->line(json_encode($detail, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
