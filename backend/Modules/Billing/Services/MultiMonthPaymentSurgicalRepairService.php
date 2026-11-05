<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\AuditLog;
use RuntimeException;

class MultiMonthPaymentSurgicalRepairService
{
    public const JOSE_379 = 'jose-379-move-296-to-july';

    public const VICTORIA_400 = 'victoria-400-move-316-to-august';

    public function __construct(
        protected PaymentService $paymentService,
        protected InvoiceService $invoiceService
    ) {
    }

    public function previewCases(): array
    {
        return [
            $this->previewCase(self::JOSE_379),
            $this->previewCase(self::VICTORIA_400),
        ];
    }

    public function repair(string $caseKey, ?int $userId, ?string $ip): array
    {
        $definition = $this->definition($caseKey);

        return DB::transaction(function () use ($definition, $userId, $ip) {
            return $this->repairLocked($definition, $userId, $ip);
        }, 3);
    }

    private function repairLocked(array $definition, ?int $userId, ?string $ip): array
    {
        $contract = Contract::query()
            ->whereKey($definition['contract_id'])
            ->lockForUpdate()
            ->first();

        if (! $contract || $contract->status !== 'active') {
            throw new RuntimeException('El contrato esperado no existe o ya no está activo.');
        }

        $invoices = Invoice::query()
            ->where('contract_id', $contract->id)
            ->whereIn('invoice_number', $definition['invoice_numbers'])
            ->lockForUpdate()
            ->get()
            ->keyBy('invoice_number');

        $source = $invoices->get($definition['source_invoice']);
        if (! $source) {
            throw new RuntimeException("No se encontró la factura origen {$definition['source_invoice']}.");
        }

        $payments = Payment::query()
            ->whereIn('id', array_keys($definition['payment_mappings']))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($payments->count() !== count($definition['payment_mappings'])) {
            throw new RuntimeException('No se encontraron todos los pagos esperados del grupo multimes.');
        }

        $targetPayment = $payments->get($definition['target_payment_id']);
        $existingDestination = $this->findCanonicalInvoiceLocked($contract->id, $definition['destination_period']);

        if ($this->alreadyRepaired($definition, $source, $targetPayment, $existingDestination)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'La reparación ya había sido aplicada; no se repitió ninguna operación.',
                'destination_invoice_id' => $existingDestination->id,
            ];
        }

        $this->assertInitialPayments($definition, $payments, $invoices);
        $this->assertGroupMetadata($definition, $payments);
        $this->assertSourceInvoice($definition, $source, $targetPayment);

        if ($definition['destination_invoice']) {
            $destination = $invoices->get($definition['destination_invoice']);
            $this->assertExistingDestination($definition, $destination);
        } else {
            if ($existingDestination) {
                throw new RuntimeException('Ya existe una factura canónica para agosto 2026; se detuvo para evitar duplicados.');
            }

            $contract->loadMissing(['customer', 'plan', 'contractedServices.additionalService']);
            $destination = $this->paymentService->createNextFutureInvoice($contract);
            $destination = Invoice::query()->whereKey($destination->id)->lockForUpdate()->firstOrFail();

            if ($this->paymentService->canonicalInvoicePeriodKey($destination) !== $definition['destination_period']) {
                throw new RuntimeException('La lógica normal no generó agosto 2026; toda la operación fue revertida.');
            }

            if (round((float) $destination->total_amount, 2) !== $definition['amount']) {
                throw new RuntimeException('La factura nueva no tiene el importe exacto esperado.');
            }
        }

        Payment::query()
            ->where('invoice_id', $destination->id)
            ->lockForUpdate()
            ->get();

        if ($destination->status !== 'pending' || $destination->payments()->exists()) {
            throw new RuntimeException('La factura destino debe estar pendiente y sin pagos antes de reasignar.');
        }

        $before = [
            'target_payment' => $targetPayment->toArray(),
            'source_invoice' => $source->toArray(),
            'destination_invoice' => $destination->toArray(),
            'group_payments' => $payments->map->toArray()->values()->all(),
        ];
        $updatedAppliedInvoices = $this->updatedAppliedInvoices(
            $targetPayment,
            $source,
            $destination,
            $definition['amount']
        );

        foreach ($payments as $payment) {
            $details = $payment->payment_details ?: [];
            $details['applied_invoices'] = $updatedAppliedInvoices;

            $updates = ['payment_details' => $details];
            if ($payment->id === $targetPayment->id) {
                $updates['invoice_id'] = $destination->id;
                $updates['notes'] = $this->appendNote(
                    $payment->notes,
                    "Pago multimes reasignado de {$source->invoice_number} a {$destination->invoice_number}; el dinero real se conserva como completed."
                );
            }

            $payment->update($updates);
        }

        $destination->update([
            'notes' => $this->appendNote(
                $destination->notes,
                "Recibió el pago #{$targetPayment->id} por reparación quirúrgica del grupo multimes."
            ),
        ]);

        $this->paymentService->refreshInvoiceStatus($source->refresh());
        $this->paymentService->refreshInvoiceStatus($destination->refresh());

        $source = $source->fresh();
        $destination = $destination->fresh();
        $targetPayment = $targetPayment->fresh();

        if ($source->status !== 'pending') {
            throw new RuntimeException('La factura origen no quedó pendiente después de mover el único pago; se revirtió la operación.');
        }

        if ($destination->status !== 'paid' || $targetPayment->status !== 'completed') {
            throw new RuntimeException('La factura destino o el pago no quedaron en el estado esperado; se revirtió la operación.');
        }

        $voidResult = $this->invoiceService->voidInvoice(
            $source->id,
            "Factura de período duplicado; el pago #{$targetPayment->id} fue reasignado a {$destination->invoice_number}."
        );

        if (! ($voidResult['success'] ?? false)) {
            throw new RuntimeException($voidResult['message'] ?? 'No se pudo anular la factura origen.');
        }

        $afterPayments = Payment::query()
            ->whereIn('id', array_keys($definition['payment_mappings']))
            ->get()
            ->keyBy('id');
        $after = [
            'target_payment' => $afterPayments->get($definition['target_payment_id'])->toArray(),
            'source_invoice' => $source->fresh()->toArray(),
            'destination_invoice' => $destination->fresh()->toArray(),
            'group_payments' => $afterPayments->map->toArray()->values()->all(),
        ];

        AuditLog::register(
            $userId,
            'multimonth_payment_reassigned',
            'payments',
            "Pago #{$targetPayment->id} reasignado de {$source->invoice_number} a {$destination->invoice_number} para corregir distribución multimes.",
            $ip ?: 'unknown',
            $before,
            $after
        );

        return [
            'success' => true,
            'skipped' => false,
            'message' => "Pago #{$targetPayment->id} reasignado correctamente a {$destination->invoice_number}.",
            'destination_invoice_id' => $destination->id,
        ];
    }

    private function assertInitialPayments(array $definition, Collection $payments, Collection $invoices): void
    {
        foreach ($definition['payment_mappings'] as $paymentId => $invoiceNumber) {
            $payment = $payments->get($paymentId);
            $invoice = $invoices->get($invoiceNumber);

            if (! $invoice || (int) $payment->invoice_id !== (int) $invoice->id) {
                throw new RuntimeException("El pago #{$paymentId} ya no está asociado a {$invoiceNumber}.");
            }

            if ($payment->status !== 'completed' || round((float) $payment->amount, 2) !== $definition['amount']) {
                throw new RuntimeException("El pago #{$paymentId} no conserva estado completed o monto exacto.");
            }
        }
    }

    private function assertGroupMetadata(array $definition, Collection $payments): void
    {
        $groupIds = $payments
            ->map(fn (Payment $payment) => data_get($payment->payment_details, 'payment_group_id'))
            ->filter()
            ->unique();

        if ($groupIds->count() !== 1) {
            throw new RuntimeException('Los tres pagos no pertenecen a un único grupo multimes.');
        }

        foreach ($payments as $payment) {
            if ((int) data_get($payment->payment_details, 'months_requested') !== 3) {
                throw new RuntimeException("El pago #{$payment->id} no registra months_requested = 3.");
            }

            $sourceAssignments = collect(data_get($payment->payment_details, 'applied_invoices', []))
                ->filter(fn ($entry) => data_get($entry, 'invoice_number') === $definition['source_invoice'])
                ->count();

            if ($sourceAssignments !== 1) {
                throw new RuntimeException("El pago #{$payment->id} no conserva exactamente una asignación al recibo origen.");
            }
        }
    }

    private function assertSourceInvoice(array $definition, Invoice $source, Payment $targetPayment): void
    {
        if ((int) $source->contract_id !== $definition['contract_id']
            || $source->status !== 'paid'
            || $source->generation_type !== 'advance_payment'
            || round((float) $source->total_amount, 2) !== $definition['amount']) {
            throw new RuntimeException('La factura origen no coincide con contrato, generación o monto esperados.');
        }

        $sourcePaymentIds = $source->payments()
            ->lockForUpdate()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($sourcePaymentIds !== [(int) $targetPayment->id]) {
            throw new RuntimeException('La factura origen contiene pagos adicionales; se detuvo la reparación.');
        }
    }

    private function assertExistingDestination(array $definition, ?Invoice $destination): void
    {
        if (! $destination
            || (int) $destination->contract_id !== $definition['contract_id']
            || $destination->status !== 'pending'
            || round((float) $destination->total_amount, 2) !== $definition['amount']
            || $this->paymentService->canonicalInvoicePeriodKey($destination) !== $definition['destination_period']) {
            throw new RuntimeException('La factura destino existente ya no cumple período, estado, contrato o monto esperados.');
        }
    }

    private function updatedAppliedInvoices(
        Payment $targetPayment,
        Invoice $source,
        Invoice $destination,
        float $amount
    ): array {
        $applied = data_get($targetPayment->payment_details, 'applied_invoices');
        if (! is_array($applied)) {
            throw new RuntimeException('El grupo no contiene applied_invoices auditable.');
        }

        $replaced = 0;
        foreach ($applied as &$entry) {
            if ((int) data_get($entry, 'invoice_id') === (int) $source->id) {
                $entry = array_merge($entry, [
                    'invoice_id' => $destination->id,
                    'invoice_number' => $destination->invoice_number,
                    'billing_period' => $destination->billing_period,
                    'amount' => $amount,
                ]);
                $replaced++;
            }
        }
        unset($entry);

        if ($replaced !== 1) {
            throw new RuntimeException('applied_invoices no contiene exactamente una asignación a la factura origen.');
        }

        return $applied;
    }

    private function findCanonicalInvoiceLocked(int $contractId, string $periodKey): ?Invoice
    {
        return Invoice::query()
            ->where('contract_id', $contractId)
            ->lockForUpdate()
            ->get()
            ->first(fn (Invoice $invoice) => $this->paymentService->canonicalInvoicePeriodKey($invoice) === $periodKey);
    }

    private function alreadyRepaired(
        array $definition,
        Invoice $source,
        Payment $targetPayment,
        ?Invoice $destination
    ): bool {
        return $destination
            && $source->status === 'void'
            && $targetPayment->status === 'completed'
            && (int) $targetPayment->invoice_id === (int) $destination->id
            && $destination->status === 'paid';
    }

    private function previewCase(string $caseKey): array
    {
        $definition = $this->definition($caseKey);

        try {
            $source = Invoice::query()
                ->where('contract_id', $definition['contract_id'])
                ->where('invoice_number', $definition['source_invoice'])
                ->first();
            $payment = Payment::query()->find($definition['target_payment_id']);
            $destination = $this->findCanonicalInvoice($definition['contract_id'], $definition['destination_period']);

            $repaired = $source && $payment && $this->alreadyRepaired($definition, $source, $payment, $destination);
            $blockedReason = null;

            if (! $repaired) {
                if (! $source || ! $payment) {
                    $blockedReason = 'No se encontró la factura origen o el pago esperado.';
                } elseif ($payment->status !== 'completed'
                    || round((float) $payment->amount, 2) !== $definition['amount']
                    || (int) $payment->invoice_id !== (int) $source->id) {
                    $blockedReason = 'El pago ya no conserva el estado, monto o factura origen esperados.';
                } elseif ($definition['destination_invoice'] && ! $destination) {
                    $blockedReason = 'No se encontró la factura destino canónica esperada.';
                } elseif (! $definition['destination_invoice'] && $destination) {
                    $blockedReason = 'Ya existe una factura canónica para agosto; requiere revisión antes de ejecutar.';
                }
            }

            return array_merge($definition, [
                'status' => $repaired ? 'repaired' : ($blockedReason ? 'blocked' : 'pending_review'),
                'status_label' => $repaired ? 'Ya reparado' : ($blockedReason ? 'Bloqueado: '.$blockedReason : 'Listo para validación transaccional'),
                'source_status' => optional($source)->status,
                'payment_status' => optional($payment)->status,
                'payment_invoice_id' => optional($payment)->invoice_id,
                'destination_invoice' => optional($destination)->invoice_number,
                'destination_status' => optional($destination)->status,
            ]);
        } catch (\Throwable $exception) {
            return array_merge($definition, [
                'status' => 'blocked',
                'status_label' => 'Bloqueado: '.$exception->getMessage(),
            ]);
        }
    }

    private function findCanonicalInvoice(int $contractId, string $periodKey): ?Invoice
    {
        return Invoice::query()
            ->where('contract_id', $contractId)
            ->get()
            ->first(fn (Invoice $invoice) => $this->paymentService->canonicalInvoicePeriodKey($invoice) === $periodKey);
    }

    private function appendNote(?string $notes, string $message): string
    {
        return trim((string) $notes)."\n[".now()."] {$message}";
    }

    private function definition(string $caseKey): array
    {
        $definitions = [
            self::JOSE_379 => [
                'key' => self::JOSE_379,
                'label' => 'José Luis — contrato #379',
                'contract_id' => 379,
                'amount' => 65.00,
                'payment_mappings' => [294 => 'INV-000826', 295 => 'INV-000827', 296 => 'INV-000828'],
                'target_payment_id' => 296,
                'source_invoice' => 'INV-000828',
                'destination_invoice' => 'INV-001254',
                'destination_period' => '2026-07',
                'invoice_numbers' => ['INV-000826', 'INV-000827', 'INV-000828', 'INV-001254'],
                'summary' => 'Mover pago #296 de junio duplicado hacia julio existente INV-001254.',
            ],
            self::VICTORIA_400 => [
                'key' => self::VICTORIA_400,
                'label' => 'Victoria — contrato #400',
                'contract_id' => 400,
                'amount' => 100.00,
                'payment_mappings' => [314 => 'INV-000859', 315 => 'INV-000860', 316 => 'INV-000861'],
                'target_payment_id' => 316,
                'source_invoice' => 'INV-000861',
                'destination_invoice' => null,
                'destination_period' => '2026-08',
                'invoice_numbers' => ['INV-000859', 'INV-000860', 'INV-000861'],
                'summary' => 'Crear agosto con lógica adelantada y mover pago #316 desde julio duplicado.',
            ],
        ];

        if (! isset($definitions[$caseKey])) {
            throw new RuntimeException('Caso de reparación no permitido.');
        }

        return $definitions[$caseKey];
    }
}
