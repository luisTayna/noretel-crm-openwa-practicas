<?php

namespace Modules\Billing\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Contract\Entities\Contract;

class AuditContractPeriodsCommand extends Command
{
    protected $signature = 'billing:audit-contract-periods {contract : ID del contrato}';

    protected $description = 'Audita recibos, pagos y períodos duplicados de un contrato sin modificar datos.';

    public function handle(): int
    {
        $contract = Contract::with([
            'customer',
            'invoices' => fn ($query) => $query
                ->with(['payments', 'creditNotes'])
                ->orderBy('issue_date')
                ->orderBy('id'),
        ])->find((int) $this->argument('contract'));

        if (! $contract) {
            $this->error('Contrato no encontrado.');

            return self::FAILURE;
        }

        $this->info("Contrato #{$contract->id} - ".optional($contract->customer)->full_name);
        $this->line("Estado: {$contract->status} | Precio final: S/ ".number_format((float) $contract->final_price, 2));

        $rows = $contract->invoices->map(function ($invoice) {
            $paid = (float) $invoice->payments->where('status', 'completed')->sum('amount');
            $credits = (float) $invoice->creditNotes->where('status', 'applied')->sum('amount');
            $periodKey = $this->periodKey($invoice->billing_period, $invoice->issue_date);
            $groupIds = $invoice->payments
                ->map(fn ($payment) => data_get($payment->payment_details, 'payment_group_id'))
                ->filter()
                ->unique()
                ->implode(', ');

            return [
                $invoice->id,
                $invoice->invoice_number,
                $periodKey,
                $invoice->billing_period,
                optional($invoice->issue_date)->format('Y-m-d'),
                $invoice->generation_type,
                $invoice->status,
                number_format((float) $invoice->total_amount, 2),
                number_format($paid, 2),
                number_format($credits, 2),
                number_format(max((float) $invoice->total_amount - $paid - $credits, 0), 2),
                $invoice->payments->pluck('id')->implode(', '),
                $groupIds,
            ];
        });

        $this->table([
            'ID', 'Recibo', 'Mes', 'Período original', 'Emisión', 'Generación',
            'Estado', 'Total', 'Pagado', 'NC', 'Pendiente', 'Pagos', 'Grupo adelanto',
        ], $rows->all());

        $duplicates = $contract->invoices
            ->whereNotIn('status', ['cancelled', 'void'])
            ->groupBy(fn ($invoice) => $this->periodKey($invoice->billing_period, $invoice->issue_date))
            ->filter(fn ($invoices) => $invoices->count() > 1);

        if ($duplicates->isEmpty()) {
            $this->info('No se encontraron períodos duplicados activos.');
        } else {
            $this->warn('PERÍODOS DUPLICADOS ACTIVOS:');
            foreach ($duplicates as $period => $invoices) {
                $this->warn("- {$period}: recibos ".$invoices->pluck('id')->implode(', '));
            }
        }

        $this->comment('Auditoría terminada. No se modificó ningún registro.');

        return self::SUCCESS;
    }

    private function periodKey(?string $billingPeriod, $fallbackDate): string
    {
        $billingPeriod = trim((string) $billingPeriod);
        $normalized = strtr(strtolower($billingPeriod), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12,
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
            'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
            'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];

        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $billingPeriod, $matches, PREG_SET_ORDER)) {
            $match = count($matches) > 1 ? $matches[1] : $matches[0];

            return sprintf('%04d-%02d', (int) $match[3], (int) $match[2]);
        }

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $billingPeriod, $match)) {
            return sprintf('%04d-%02d', (int) $match[2], (int) $match[1]);
        }

        foreach ($months as $name => $month) {
            if (preg_match('/\b'.preg_quote($name, '/').'\s+(\d{4})\b/', $normalized, $match)) {
                return sprintf('%04d-%02d', (int) $match[1], $month);
            }
        }

        return Carbon::parse($fallbackDate)->format('Y-m');
    }
}
