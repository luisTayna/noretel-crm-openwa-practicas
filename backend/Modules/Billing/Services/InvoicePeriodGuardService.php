<?php

namespace Modules\Billing\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;
use RuntimeException;

class InvoicePeriodGuardService
{
    public const BLOCKING_STATUSES = ['draft', 'pending', 'paid', 'partial', 'overdue'];

    public function canonicalKey(?string $billingPeriod, $fallbackDate = null): ?string
    {
        $period = trim((string) $billingPeriod);

        if (preg_match_all('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $period, $matches, PREG_SET_ORDER)) {
            $target = count($matches) > 1 ? $matches[count($matches) - 1] : $matches[0];

            return Carbon::create((int) $target[3], (int) $target[2], 1)->format('Y-m');
        }

        if (preg_match('/\b(\d{4})-(\d{1,2})\b/', $period, $match)) {
            return Carbon::create((int) $match[1], (int) $match[2], 1)->format('Y-m');
        }

        if (preg_match('/\b(\d{1,2})\/(\d{4})\b/', $period, $match)) {
            return Carbon::create((int) $match[2], (int) $match[1], 1)->format('Y-m');
        }

        $normalized = strtolower(Str::ascii($period));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: '';
        $months = [
            1 => ['enero', 'ene', 'january', 'jan'],
            2 => ['febrero', 'feb', 'february'],
            3 => ['marzo', 'mar', 'march'],
            4 => ['abril', 'abr', 'april', 'apr'],
            5 => ['mayo', 'may'],
            6 => ['junio', 'jun', 'june'],
            7 => ['julio', 'jul', 'july'],
            8 => ['agosto', 'ago', 'august', 'aug'],
            9 => ['septiembre', 'setiembre', 'sept', 'sep', 'september'],
            10 => ['octubre', 'oct', 'october'],
            11 => ['noviembre', 'nov', 'november'],
            12 => ['diciembre', 'dic', 'december', 'dec'],
        ];

        foreach ($months as $month => $names) {
            foreach ($names as $name) {
                if (preg_match('/\b'.preg_quote($name, '/').'\s+(\d{4})\b/', $normalized, $match)) {
                    return Carbon::create((int) $match[1], $month, 1)->format('Y-m');
                }
            }
        }

        if ($fallbackDate) {
            return Carbon::parse($fallbackDate)->startOfMonth()->format('Y-m');
        }

        return null;
    }

    public function canonicalKeyForInvoice(Invoice $invoice): ?string
    {
        return $this->canonicalKey($invoice->billing_period, $invoice->issue_date);
    }

    public function createForPeriod(
        int $contractId,
        ?string $billingPeriod,
        $fallbackDate,
        callable $creator,
        bool $requireActiveContract = true
    ): array {
        return DB::transaction(function () use ($contractId, $billingPeriod, $fallbackDate, $creator, $requireActiveContract) {
            $check = $this->checkInsideTransaction($contractId, $billingPeriod, $fallbackDate, $requireActiveContract);
            if ($check['skipped']) {
                return array_merge(['created' => false], $check);
            }

            $contract = $check['contract'];
            $targetKey = $check['period_key'];

            $invoice = $creator($contract, $targetKey);
            if (! $invoice instanceof Invoice
                || (int) $invoice->contract_id !== $contract->id
                || $this->canonicalKeyForInvoice($invoice) !== $targetKey) {
                throw new RuntimeException('El creador de facturas no respetó el contrato o mes canónico bloqueado.');
            }

            return [
                'created' => true,
                'skipped' => false,
                'reason' => null,
                'invoice' => $invoice,
                'period_key' => $targetKey,
            ];
        }, 3);
    }

    public function checkInsideTransaction(
        int $contractId,
        ?string $billingPeriod,
        $fallbackDate,
        bool $requireActiveContract = true
    ): array {
        if (DB::connection()->transactionLevel() < 1) {
            throw new RuntimeException('La validación canónica debe ejecutarse dentro de una transacción.');
        }

        $targetKey = $this->canonicalKey($billingPeriod, $fallbackDate);
        if (! $targetKey) {
            throw new RuntimeException('No se pudo reconocer el mes canónico de la factura.');
        }

        $contract = Contract::query()->whereKey($contractId)->lockForUpdate()->firstOrFail();
        if ($requireActiveContract && $contract->status !== 'active') {
            return [
                'skipped' => true,
                'reason' => "Contrato no facturable: status={$contract->status}",
                'invoice' => null,
                'period_key' => $targetKey,
                'contract' => $contract,
            ];
        }

        $existing = $this->blockingInvoicesLocked($contract->id)
            ->first(fn (Invoice $invoice) => $this->canonicalKeyForInvoice($invoice) === $targetKey);
        if ($existing) {
            Log::info('invoice_creation_skipped_duplicate_period', [
                'contract_id' => $contract->id,
                'period_key' => $targetKey,
                'existing_invoice_id' => $existing->id,
                'existing_invoice_number' => $existing->invoice_number,
                'existing_status' => $existing->status,
            ]);

            return [
                'skipped' => true,
                'reason' => 'Ya existe un recibo activo para el mismo contrato y mes canónico.',
                'invoice' => $existing,
                'period_key' => $targetKey,
                'contract' => $contract,
            ];
        }

        return [
            'skipped' => false,
            'reason' => null,
            'invoice' => null,
            'period_key' => $targetKey,
            'contract' => $contract,
        ];
    }

    public function createNextFuture(int $contractId, callable $creator): Invoice
    {
        return DB::transaction(function () use ($contractId, $creator) {
            $contract = Contract::query()->whereKey($contractId)->lockForUpdate()->firstOrFail();
            if ($contract->status !== 'active') {
                throw new RuntimeException('Solo se pueden crear recibos adelantados para contratos activos.');
            }

            $activeInvoices = $this->blockingInvoicesLocked($contract->id);
            $lastKey = $activeInvoices
                ->map(fn (Invoice $invoice) => $this->canonicalKeyForInvoice($invoice))
                ->filter()
                ->sortDesc()
                ->first();
            $issueDate = $lastKey
                ? Carbon::createFromFormat('Y-m', $lastKey)->startOfMonth()->addMonthNoOverflow()
                : now()->startOfMonth();

            while ($activeInvoices->contains(fn (Invoice $invoice) => $this->canonicalKeyForInvoice($invoice) === $issueDate->format('Y-m'))) {
                $issueDate->addMonthNoOverflow();
            }

            $invoice = $creator($contract, $issueDate->copy(), $issueDate->format('Y-m'));
            if (! $invoice instanceof Invoice || $this->canonicalKeyForInvoice($invoice) !== $issueDate->format('Y-m')) {
                throw new RuntimeException('El recibo adelantado no respetó el siguiente mes canónico disponible.');
            }

            return $invoice;
        }, 3);
    }

    private function blockingInvoicesLocked(int $contractId)
    {
        return Invoice::query()
            ->where('contract_id', $contractId)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->lockForUpdate()
            ->get();
    }
}
