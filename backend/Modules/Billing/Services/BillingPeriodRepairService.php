<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\Invoice;
use Modules\Core\Entities\AuditLog;
use RuntimeException;

class BillingPeriodRepairService
{
    public function duplicateGroups(): Collection
    {
        return Invoice::query()
            ->with(['contract.customer', 'payments', 'creditNotes'])
            ->whereNotIn('status', ['cancelled', 'void'])
            ->orderBy('contract_id')
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Invoice $invoice) => $invoice->contract_id.'|'.$this->periodKey($invoice))
            ->filter(fn (Collection $invoices) => $invoices->count() > 1)
            ->map(fn (Collection $invoices) => $this->classifyGroup($invoices))
            ->values();
    }

    public function classifyGroup(Collection $invoices): array
    {
        /** @var Invoice $first */
        $first = $invoices->first();
        $details = $invoices->map(function (Invoice $invoice) {
            $paid = round((float) $invoice->payments->where('status', 'completed')->sum('amount'), 2);
            $credits = round((float) $invoice->creditNotes->where('status', 'applied')->sum('amount'), 2);
            $total = round((float) $invoice->total_amount, 2);

            return [
                'invoice' => $invoice,
                'paid' => $paid,
                'credits' => $credits,
                'pending' => max(round($total - $paid - $credits, 2), 0),
                'covered' => $paid + $credits >= $total && $total > 0,
                'safe_duplicate' => in_array($invoice->status, ['pending', 'overdue'], true)
                    && $paid === 0.0
                    && $credits === 0.0
                    && $invoice->generation_type === 'automatic',
            ];
        })->values();

        $covered = $details->where('covered', true);
        $duplicates = $details->where('safe_duplicate', true);
        $safe = $covered->count() === 1
            && $duplicates->isNotEmpty()
            && $covered->count() + $duplicates->count() === $details->count();

        return [
            'contract_id' => $first->contract_id,
            'contract' => $first->contract,
            'customer' => optional($first->contract)->customer,
            'period_key' => $this->periodKey($first),
            'period_label' => Carbon::createFromFormat('Y-m', $this->periodKey($first))
                ->locale('es')
                ->translatedFormat('F Y'),
            'invoices' => $details,
            'safe' => $safe,
            'duplicate_ids' => $safe ? $duplicates->pluck('invoice.id')->all() : [],
            'reason' => $safe
                ? 'Existe un recibo cubierto y uno o más recibos automáticos sin pagos.'
                : 'Requiere revisión manual porque no hay una única factura cubierta o existen movimientos ambiguos.',
        ];
    }

    public function repairAllSafeDuplicates(?int $userId, ?string $ip): array
    {
        $groups = $this->duplicateGroups()->where('safe', true);
        $voided = [];

        foreach ($groups as $group) {
            $voided = array_merge($voided, $this->repairGroup(
                (int) $group['contract_id'],
                (string) $group['period_key'],
                $userId,
                $ip
            ));
        }

        return [
            'groups' => $groups->count(),
            'invoices' => count($voided),
            'invoice_ids' => $voided,
        ];
    }

    private function repairGroup(int $contractId, string $periodKey, ?int $userId, ?string $ip): array
    {
        return DB::transaction(function () use ($contractId, $periodKey, $userId, $ip) {
            $invoices = Invoice::query()
                ->with(['contract.customer', 'payments', 'creditNotes'])
                ->where('contract_id', $contractId)
                ->whereNotIn('status', ['cancelled', 'void'])
                ->lockForUpdate()
                ->get()
                ->filter(fn (Invoice $invoice) => $this->periodKey($invoice) === $periodKey)
                ->values();

            $group = $this->classifyGroup($invoices);
            if (! $group['safe']) {
                throw new RuntimeException("El contrato {$contractId}, período {$periodKey}, dejó de ser un caso seguro.");
            }

            $voided = [];
            foreach ($group['duplicate_ids'] as $invoiceId) {
                /** @var Invoice $invoice */
                $invoice = $invoices->firstWhere('id', $invoiceId);
                $oldData = $invoice->toArray();
                $reason = 'Duplicado automático de un período cubierto mediante pago adelantado.';

                $invoice->update([
                    'status' => 'void',
                    'notes' => trim((string) $invoice->notes)."\n[".now()."] Recibo anulado. Motivo: {$reason}",
                ]);

                AuditLog::register(
                    $userId,
                    'advance_payment_duplicate_voided',
                    'invoices',
                    "Recibo duplicado anulado: {$invoice->invoice_number}. Contrato #{$contractId}, período {$periodKey}",
                    $ip,
                    $oldData,
                    $invoice->fresh()->toArray()
                );

                $voided[] = $invoice->id;
            }

            return $voided;
        });
    }

    private function periodKey(Invoice $invoice): string
    {
        $billingPeriod = trim((string) $invoice->billing_period);
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

        return Carbon::parse($invoice->issue_date)->format('Y-m');
    }
}
