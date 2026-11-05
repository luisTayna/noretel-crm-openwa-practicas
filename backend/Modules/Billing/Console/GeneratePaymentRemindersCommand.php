<?php

namespace Modules\Billing\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Billing\Services\PaymentReminderService;

class GeneratePaymentRemindersCommand extends Command
{
    protected $signature = 'billing:generate-payment-reminders
        {--date= : Fecha base YYYY-MM-DD para evaluar avisos}
        {--type=all : Tipo de recordatorio a generar: all, preventive u overdue}
        {--overdue-day= : Día de mora para pruebas de recordatorio vencido}
        {--test-customer-id= : Genera un recordatorio seguro solo para el cliente indicado}
        {--scheduled-now : Programa el recordatorio de prueba para envío inmediato}
        {--dry-run : Muestra qué se generaría sin insertar en base de datos}';

    protected $description = 'Genera recordatorios de pago OpenWA según el ciclo de cobranza.';

    public function handle(PaymentReminderService $paymentReminderService): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        if ($this->option('test-customer-id')) {
            $result = $paymentReminderService->generateTestReminder(
                (int) $this->option('test-customer-id'),
                $date,
                (bool) $this->option('scheduled-now'),
                (bool) $this->option('dry-run'),
                (string) $this->option('type'),
                $this->option('overdue-day') !== null ? (int) $this->option('overdue-day') : null
            );
        } elseif ($this->option('type') === 'overdue') {
            $result = $paymentReminderService->generateOverdueReminders($date);
        } elseif ($this->option('type') === 'preventive') {
            $result = $paymentReminderService->generateReminders($date);
        } else {
            $result = $paymentReminderService->generateAllReminders($date);
        }

        $this->info("Recordatorios generados: {$result['generated']}. Omitidos: {$result['skipped']}. Fallidos: {$result['failed']}.");

        if (! empty($result['message'])) {
            $this->line($result['message']);
        }

        foreach ($result['details'] ?? [] as $detail) {
            $this->line(json_encode($detail, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
