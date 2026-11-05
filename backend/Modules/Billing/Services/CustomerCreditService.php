<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\CustomerCreditMovement;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Customer\Entities\Customer;

class CustomerCreditService
{
    public function getBalance(int $customerId, ?int $contractId = null): float
    {
        $balance = $this->currentBalance($customerId, $contractId);

        \Log::info('customer_credit_balance_checked', [
            'customer_id' => $customerId,
            'contract_id' => $contractId,
            'balance' => $balance,
        ]);

        return $balance;
    }

    public function addCredit(array $data): CustomerCreditMovement
    {
        return DB::transaction(function () use ($data) {
            $amount = $this->normalizeAmount($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new \InvalidArgumentException('El crédito debe ser mayor a cero.');
            }

            $existing = $this->findExistingMovement($data, CustomerCreditMovement::TYPE_CREDIT);

            if ($existing) {
                \Log::info('customer_credit_created', [
                    'customer_id' => $existing->customer_id,
                    'contract_id' => $existing->contract_id,
                    'credit_movement_id' => $existing->id,
                    'idempotent_reuse' => true,
                    'balance_after' => (float) $existing->balance_after,
                ]);

                return $existing;
            }

            $customerId = (int) $data['customer_id'];
            $contractId = isset($data['contract_id']) ? (int) $data['contract_id'] : null;
            $balanceAfter = round($this->currentBalanceForUpdate($customerId, $contractId) + $amount, 2);

            $movement = CustomerCreditMovement::create([
                'customer_id' => $customerId,
                'contract_id' => $contractId,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'type' => CustomerCreditMovement::TYPE_CREDIT,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'status' => CustomerCreditMovement::STATUS_ACTIVE,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            \Log::info('customer_credit_created', [
                'customer_id' => $customerId,
                'contract_id' => $contractId,
                'credit_movement_id' => $movement->id,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'metadata' => $movement->metadata,
            ]);

            return $movement;
        });
    }

    public function consumeCredit(array $data): CustomerCreditMovement
    {
        return DB::transaction(function () use ($data) {
            $amount = $this->normalizeAmount($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw new \InvalidArgumentException('El consumo de crédito debe ser mayor a cero.');
            }

            $customerId = (int) $data['customer_id'];
            $contractId = isset($data['contract_id']) ? (int) $data['contract_id'] : null;
            $currentBalance = $this->currentBalanceForUpdate($customerId, $contractId);

            if ($currentBalance + 0.009 < $amount) {
                throw new \RuntimeException('Saldo a favor insuficiente.');
            }

            $balanceAfter = round($currentBalance - $amount, 2);

            $movement = CustomerCreditMovement::create([
                'customer_id' => $customerId,
                'contract_id' => $contractId,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'type' => CustomerCreditMovement::TYPE_DEBIT,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'status' => CustomerCreditMovement::STATUS_ACTIVE,
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            \Log::info('customer_credit_consumed', [
                'customer_id' => $customerId,
                'contract_id' => $contractId,
                'credit_movement_id' => $movement->id,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'metadata' => $movement->metadata,
            ]);

            return $movement;
        });
    }

    public function applyAvailableCreditToInvoice(Invoice $invoice, bool $allowPartial = false): ?Payment
    {
        return DB::transaction(function () use ($invoice, $allowPartial) {
            $invoice = Invoice::query()
                ->with(['contract.customer', 'payments', 'creditNotes'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            $customer = $invoice->contract?->customer;
            $contract = $invoice->contract;

            if (!$customer || !$contract) {
                return null;
            }

            \Log::info('customer_credit_apply_started', [
                'customer_id' => $customer->id,
                'contract_id' => $contract->id,
                'invoice_id' => $invoice->id,
            ]);

            $balance = $this->currentBalanceForUpdate($customer->id, $contract->id);
            $pendingAmount = $this->normalizeAmount($invoice->pending_amount);

            if ($balance <= 0 || $pendingAmount <= 0) {
                return null;
            }

            if (!$allowPartial && $balance + 0.009 < $pendingAmount) {
                return null;
            }

            $amountToApply = round(min($balance, $pendingAmount), 2);

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $amountToApply,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'customer_credit',
                'status' => 'completed',
                'reference' => 'SALDO-FAVOR-' . now()->format('YmdHis'),
                'notes' => 'Aplicación de saldo a favor del cliente.',
                'user_id' => auth()->id(),
                'payment_details' => [
                    'source' => 'customer_credit',
                    'customer_id' => $customer->id,
                    'contract_id' => $contract->id,
                ],
            ]);

            $movement = $this->consumeCredit([
                'customer_id' => $customer->id,
                'contract_id' => $contract->id,
                'source_type' => 'invoice',
                'source_id' => $invoice->id,
                'amount' => $amountToApply,
                'description' => 'Aplicación de saldo a favor al recibo ' . $invoice->invoice_number,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                    'allow_partial' => $allowPartial,
                ],
            ]);

            $payment->payment_details = array_merge($payment->payment_details ?? [], [
                'credit_movement_id' => $movement->id,
                'balance_after' => (float) $movement->balance_after,
            ]);
            $payment->save();

            $this->refreshInvoiceStatus($invoice->refresh());

            \Log::info('customer_credit_apply_completed', [
                'customer_id' => $customer->id,
                'contract_id' => $contract->id,
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'credit_movement_id' => $movement->id,
                'amount' => $amountToApply,
                'balance_after' => (float) $movement->balance_after,
            ]);

            return $payment;
        });
    }

    public function applyAvailableCreditToPendingInvoices(Customer $customer, $contract = null, bool $allowPartial = false): array
    {
        $query = Invoice::query()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->whereHas('contract', function ($contractQuery) use ($customer, $contract) {
                $contractQuery->where('customer_id', $customer->id);

                if ($contract) {
                    $contractQuery->where('id', $contract->id);
                }
            })
            ->orderBy('due_date')
            ->orderBy('issue_date')
            ->orderBy('billing_period')
            ->orderBy('id');

        $payments = [];

        foreach ($query->get() as $invoice) {
            $payment = $this->applyAvailableCreditToInvoice($invoice, $allowPartial);

            if (!$payment) {
                break;
            }

            $payments[] = $payment;
        }

        return $payments;
    }

    protected function currentBalance(int $customerId, ?int $contractId = null): float
    {
        if ($contractId === null) {
            return round((float) CustomerCreditMovement::query()
                ->where('customer_id', $customerId)
                ->where('status', CustomerCreditMovement::STATUS_ACTIVE)
                ->orderBy('contract_id')
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn (CustomerCreditMovement $movement) => $movement->contract_id ?: 'global')
                ->sum(fn ($movements) => (float) $movements->first()->balance_after), 2);
        }

        $movement = CustomerCreditMovement::query()
            ->where('customer_id', $customerId)
            ->where('contract_id', $contractId)
            ->where('status', CustomerCreditMovement::STATUS_ACTIVE)
            ->latest('id')
            ->first();

        return round((float) ($movement?->balance_after ?? 0), 2);
    }

    protected function currentBalanceForUpdate(int $customerId, ?int $contractId = null): float
    {
        $movement = CustomerCreditMovement::query()
            ->where('customer_id', $customerId)
            ->when($contractId, fn ($query) => $query->where('contract_id', $contractId), fn ($query) => $query->whereNull('contract_id'))
            ->where('status', CustomerCreditMovement::STATUS_ACTIVE)
            ->lockForUpdate()
            ->latest('id')
            ->first();

        return round((float) ($movement?->balance_after ?? 0), 2);
    }

    protected function findExistingMovement(array $data, string $type): ?CustomerCreditMovement
    {
        if (!empty($data['source_type']) && !empty($data['source_id'])) {
            $existing = CustomerCreditMovement::query()
                ->where('source_type', $data['source_type'])
                ->where('source_id', $data['source_id'])
                ->where('type', $type)
                ->where('status', CustomerCreditMovement::STATUS_ACTIVE)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $metadata = $data['metadata'] ?? [];

        foreach (['yape_intent_id', 'operation_code', 'payment_group_id'] as $key) {
            if (empty($metadata[$key])) {
                continue;
            }

            $existing = CustomerCreditMovement::query()
                ->where('type', $type)
                ->where('status', CustomerCreditMovement::STATUS_ACTIVE)
                ->where("metadata->{$key}", $metadata[$key])
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    protected function refreshInvoiceStatus(Invoice $invoice): void
    {
        $totalPaid = $invoice->payments()->where('status', 'completed')->sum('amount');
        $creditNoteAmount = $invoice->creditNotes()->where('status', 'applied')->sum('amount');

        if ($totalPaid + $creditNoteAmount >= $invoice->total_amount) {
            $invoice->update(['status' => 'paid']);
        } elseif ($totalPaid + $creditNoteAmount > 0) {
            $invoice->update(['status' => 'partial']);
        } else {
            $invoice->update(['status' => 'pending']);
        }
    }

    protected function normalizeAmount(mixed $amount): float
    {
        return round((float) $amount, 2);
    }
}
