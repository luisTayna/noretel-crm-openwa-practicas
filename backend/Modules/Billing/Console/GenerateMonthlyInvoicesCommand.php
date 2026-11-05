<?php

namespace Modules\Billing\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Billing\Services\InvoiceService;

class GenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'billing:generate-monthly-invoices
        {--date= : Fecha base YYYY-MM-DD para generar el período}
        {--force : Generar aunque la fecha no sea el día oficial de generación}';

    protected $description = 'Genera recibos mensuales del ciclo fijo de cobranza.';

    public function handle(InvoiceService $invoiceService): int
    {
        $date = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $generationDay = (int) config('billing.invoice.monthly_generation_day', 15);

        if (! $this->option('force') && (int) $date->format('d') !== $generationDay) {
            $this->info("No se generan recibos: la fecha {$date->toDateString()} no es día {$generationDay}.");
            return self::SUCCESS;
        }

        $result = $invoiceService->generateScheduledInvoices($date);

        $this->info(
            "Recibos generados: {$result['generated']}. "
            ."Omitidos por existir: {$result['skipped']}. "
            ."Fallidos: {$result['failed']}."
        );

        foreach ($result['details'] ?? [] as $detail) {
            $status = ($detail['result']['skipped'] ?? false)
                ? 'OMITIDO'
                : (($detail['result']['success'] ?? false) ? 'OK' : 'ERROR');
            $message = $detail['result']['message'] ?? '';
            $this->line("[{$status}] Contrato {$detail['contract_id']} - {$detail['customer']} {$message}");
        }

        return ($result['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
