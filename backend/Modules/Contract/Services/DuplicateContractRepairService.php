<?php

namespace Modules\Contract\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Billing\Entities\Invoice;
use Modules\Billing\Entities\Payment;
use Modules\Billing\Services\PaymentService;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\Installation;
use Modules\Core\Entities\AuditLog;
use RuntimeException;

class DuplicateContractRepairService
{
    public const GREGORIO_122 = 'gregorio-122-contract-253-254';
    public const GREGORIO_253_254_PHYSICAL = 'gregorio-contract-253-254-physical-delete';
    public const LEONIDAS_37_38 = 'leonidas-contract-37-38-physical-delete';
    public const DELIA_159_160 = 'delia-contract-159-160-physical-delete';

    private const NOTE_MARKER = '[REPAIR-DUPLICATE-CONTRACT-122]';

    public function __construct(private PaymentService $paymentService)
    {
    }

    public function previewGregorio(): array
    {
        try {
            $contracts = Contract::query()->whereIn('id', [253, 254])->get()->keyBy('id');
            $installations = Installation::query()->whereIn('id', [253, 254])->get()->keyBy('id');
            $invoices = Invoice::query()->whereIn('id', [505, 506, 1190, 1191])->get()->keyBy('id');

            if ($contracts->has(253)
                && ! $contracts->has(254)
                && $invoices->has(505)
                && $invoices->has(1190)
                && ! $invoices->has(506)
                && ! $invoices->has(1191)) {
                return $this->gregorioPreview('deleted', 'El contrato duplicado #254 ya fue eliminado físicamente.');
            }

            if ($contracts->count() !== 2 || $installations->count() !== 2 || $invoices->count() !== 4) {
                return $this->gregorioPreview('blocked', 'Faltan contratos, instalaciones o facturas esperadas.');
            }

            if ($this->gregorioAlreadyRepaired($contracts, $installations, $invoices)) {
                return $this->gregorioPreview('repaired', 'Ya reparado; una repetición será omitida.');
            }

            $this->validateGregorioRecords($contracts, $installations, $invoices, false);
            $this->assertNoUnexpectedRelations([253, 254], [505, 506, 1190, 1191], false);

            return $this->gregorioPreview('ready', 'Listo para validación transaccional.');
        } catch (\Throwable $exception) {
            return $this->gregorioPreview('blocked', $exception->getMessage());
        }
    }

    public function repairGregorio(?int $userId, ?string $ip): array
    {
        return DB::transaction(function () use ($userId, $ip) {
            $contracts = Contract::query()
                ->whereIn('id', [253, 254])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $installations = Installation::query()
                ->whereIn('id', [253, 254])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $invoices = Invoice::query()
                ->whereIn('id', [505, 506, 1190, 1191])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($contracts->count() !== 2 || $installations->count() !== 2 || $invoices->count() !== 4) {
                throw new RuntimeException('Faltan registros esperados; no se modificó nada.');
            }

            $this->assertNoUnexpectedRelations([253, 254], [505, 506, 1190, 1191], true);

            if ($this->gregorioAlreadyRepaired($contracts, $installations, $invoices)) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => 'El caso Gregorio ya había sido reparado; no se repitió ninguna acción.',
                ];
            }

            $this->validateGregorioRecords($contracts, $installations, $invoices, true);

            $before = [
                'contracts' => $contracts->map->toArray()->values()->all(),
                'installations' => $installations->map->toArray()->values()->all(),
                'invoices' => $invoices->map->toArray()->values()->all(),
            ];

            $contracts[254]->update([
                'status' => 'cancelled',
                'end_date' => now(),
                'notes' => $this->appendRepairNote(
                    $contracts[254]->notes,
                    'Contrato duplicado de migración. Se conserva el contrato #253 como servicio real.'
                ),
            ]);

            $installations[254]->update([
                'notes' => $this->appendRepairNote(
                    $installations[254]->notes,
                    'Instalación histórica duplicada. Se conserva completed porque el flujo del sistema no permite cancelar instalaciones finalizadas.'
                ),
            ]);

            $invoices[505]->update([
                'status' => 'pending',
                'notes' => $this->appendRepairNote(
                    $invoices[505]->notes,
                    'Recibo restaurado como deuda real de junio; la factura paralela INV-000502 fue anulada.'
                ),
            ]);
            $invoices[506]->update([
                'status' => 'void',
                'notes' => $this->appendRepairNote(
                    $invoices[506]->notes,
                    'Recibo anulado por pertenecer al contrato duplicado #254.'
                ),
            ]);
            $invoices[1190]->update([
                'notes' => $this->appendRepairNote(
                    $invoices[1190]->notes,
                    'Recibo de julio conservado en el contrato principal #253.'
                ),
            ]);
            $invoices[1191]->update([
                'status' => 'void',
                'notes' => $this->appendRepairNote(
                    $invoices[1191]->notes,
                    'Recibo anulado por pertenecer al contrato duplicado #254.'
                ),
            ]);

            $afterContracts = Contract::query()->whereIn('id', [253, 254])->get()->keyBy('id');
            $afterInstallations = Installation::query()->whereIn('id', [253, 254])->get()->keyBy('id');
            $afterInvoices = Invoice::query()->whereIn('id', [505, 506, 1190, 1191])->get()->keyBy('id');

            $this->assertGregorioFinalState($afterContracts, $afterInstallations, $afterInvoices);

            $after = [
                'contracts' => $afterContracts->map->toArray()->values()->all(),
                'installations' => $afterInstallations->map->toArray()->values()->all(),
                'invoices' => $afterInvoices->map->toArray()->values()->all(),
            ];

            AuditLog::register(
                $userId,
                'duplicate_contract_repaired',
                'contracts',
                'Reparación individual de contratos duplicados #253/#254 para customer #122.',
                $ip ?: 'unknown',
                $before,
                $after
            );

            return [
                'success' => true,
                'skipped' => false,
                'message' => 'Gregorio quedó con el contrato #253 activo y S/ 130.00 pendientes entre junio y julio.',
            ];
        }, 3);
    }

    public function previewConfirmedPhysicalDeletionCases(): Collection
    {
        return collect($this->confirmedPhysicalDeletionCases())->map(function (array $case, string $key) {
            try {
                $state = $this->loadConfirmedPairState($case, false);
                if ($state['already_deleted']) {
                    return array_merge($case, [
                        'key' => $key,
                        'status' => 'deleted',
                        'message' => 'El contrato duplicado ya fue eliminado; repetir la acción será omitido.',
                    ]);
                }

                $this->validateConfirmedPairState($case, $state, false);

                return array_merge($case, [
                    'key' => $key,
                    'status' => 'ready',
                    'message' => 'Listo para eliminación física transaccional.',
                ]);
            } catch (\Throwable $exception) {
                return array_merge($case, [
                    'key' => $key,
                    'status' => 'blocked',
                    'message' => $exception->getMessage(),
                ]);
            }
        })->values();
    }

    public function physicallyDeleteConfirmedDuplicate(string $caseKey, ?int $userId, ?string $ip): array
    {
        $case = $this->confirmedPhysicalDeletionCases()[$caseKey] ?? null;
        if (! $case) {
            throw new RuntimeException('Caso de eliminación física no permitido.');
        }

        return DB::transaction(function () use ($case, $caseKey, $userId, $ip) {
            $state = $this->loadConfirmedPairState($case, true);
            if ($state['already_deleted']) {
                return [
                    'success' => true,
                    'skipped' => true,
                    'message' => "El contrato duplicado #{$case['duplicate_contract_id']} ya no existe; no se repitió ninguna eliminación.",
                ];
            }

            $this->validateConfirmedPairState($case, $state, true);

            $duplicateInvoices = $state['invoices']->where('contract_id', $case['duplicate_contract_id']);
            $duplicateInvoiceIds = $duplicateInvoices->pluck('id')->map(fn ($id) => (int) $id)->all();
            $keeperInvoiceNumbers = array_keys($case['keeper_invoices']);

            $before = [
                'case' => $case,
                'contracts' => $state['contracts']->map->toArray()->values()->all(),
                'installations' => $state['installations']->map->toArray()->values()->all(),
                'invoices' => $state['invoices']->map->toArray()->values()->all(),
                'invoice_items' => DB::table('invoice_items')->whereIn('invoice_id', $duplicateInvoiceIds)->get()->map(fn ($row) => (array) $row)->all(),
                'payment_reminders' => Schema::hasTable('payment_reminders')
                    ? DB::table('payment_reminders')->whereIn('invoice_id', $duplicateInvoiceIds)->get()->map(fn ($row) => (array) $row)->all()
                    : [],
            ];

            Invoice::query()->where('contract_id', $case['duplicate_contract_id'])->delete();
            Installation::query()->where('contract_id', $case['duplicate_contract_id'])->delete();
            Contract::query()->whereKey($case['duplicate_contract_id'])->delete();

            $keeperInvoices = Invoice::query()
                ->where('contract_id', $case['keeper_contract_id'])
                ->whereIn('invoice_number', $keeperInvoiceNumbers)
                ->get();
            if (Contract::query()->whereKey($case['duplicate_contract_id'])->exists()
                || Invoice::query()->where('contract_id', $case['duplicate_contract_id'])->exists()
                || Installation::query()->where('contract_id', $case['duplicate_contract_id'])->exists()
                || Contract::query()->whereKey($case['keeper_contract_id'])->where('status', 'active')->doesntExist()
                || $keeperInvoices->count() !== count($keeperInvoiceNumbers)
                || $keeperInvoices->contains(fn (Invoice $invoice) => $invoice->status !== 'pending')) {
                throw new RuntimeException('La verificación final falló; se revirtió toda la eliminación.');
            }

            $preservedDebt = round($keeperInvoices->sum(fn (Invoice $invoice) => (float) $invoice->pending_amount), 2);
            AuditLog::register(
                $userId,
                'duplicate_contract_physically_deleted',
                'contracts',
                "Eliminación física confirmada del contrato duplicado #{$case['duplicate_contract_id']}; se conserva #{$case['keeper_contract_id']} con deuda de junio y julio.",
                $ip ?: 'unknown',
                $before,
                [
                    'keeper_contract_id' => $case['keeper_contract_id'],
                    'deleted_contract_id' => $case['duplicate_contract_id'],
                    'preserved_invoice_ids' => $keeperInvoices->pluck('id')->all(),
                    'deleted_invoice_ids' => $duplicateInvoiceIds,
                    'preserved_pending_debt' => $preservedDebt,
                ]
            );

            return [
                'success' => true,
                'skipped' => false,
                'message' => "Contrato duplicado #{$case['duplicate_contract_id']} eliminado físicamente. Se conserva #{$case['keeper_contract_id']} con S/ ".number_format($preservedDebt, 2).' pendientes.',
            ];
        }, 3);
    }

    public function auditCandidates(): Collection
    {
        $activeContracts = Contract::query()
            ->where('status', 'active')
            ->with('customer:id,first_name,last_name,identity_document')
            ->orderBy('customer_id')
            ->orderBy('created_at')
            ->get();

        $groups = $activeContracts->groupBy(fn (Contract $contract) => implode('|', [
            $contract->customer_id,
            $contract->plan_id,
            optional($contract->start_date)->format('Y-m-d'),
            number_format((float) $contract->final_price, 2, '.', ''),
        ]));

        $pairs = collect();
        foreach ($groups->filter(fn (Collection $group) => $group->count() > 1) as $group) {
            $keeper = $group->sortBy(fn (Contract $contract) => sprintf('%s|%010d', $contract->created_at, $contract->id))->first();

            foreach ($group->where('id', '!=', $keeper->id) as $duplicate) {
                $secondsApart = abs(optional($keeper->created_at)->diffInSeconds($duplicate->created_at, false) ?? PHP_INT_MAX);
                if ($secondsApart > 60) {
                    continue;
                }

                $pairs->push($this->buildCandidate($keeper, $duplicate, $secondsApart));
            }
        }

        return $pairs->sortBy([
            ['risk_order', 'asc'],
            ['customer_name', 'asc'],
        ])->values();
    }

    private function buildCandidate(Contract $keeper, Contract $duplicate, int $secondsApart): array
    {
        $contractIds = [$keeper->id, $duplicate->id];
        $installations = Installation::query()->whereIn('contract_id', $contractIds)->get()->groupBy('contract_id');
        $invoices = Invoice::query()
            ->whereIn('contract_id', $contractIds)
            ->with(['payments:id,invoice_id,amount,status', 'creditNotes:id,invoice_id,amount,status'])
            ->get()
            ->groupBy('contract_id');
        $keeperInvoices = $invoices->get($keeper->id, collect());
        $duplicateInvoices = $invoices->get($duplicate->id, collect());
        $keeperPeriods = $keeperInvoices->map(fn (Invoice $invoice) => $this->paymentService->canonicalInvoicePeriodKey($invoice))->filter();
        $duplicatePeriods = $duplicateInvoices->map(fn (Invoice $invoice) => $this->paymentService->canonicalInvoicePeriodKey($invoice))->filter();
        $overlap = $keeperPeriods->intersect($duplicatePeriods)->unique()->values();
        $relations = $this->relationCounts($contractIds);
        $relationDetails = $this->relationDetails($contractIds);
        $payments = $keeperInvoices->merge($duplicateInvoices)->sum(fn (Invoice $invoice) => $invoice->payments->count());
        $creditNotes = $keeperInvoices->merge($duplicateInvoices)->sum(fn (Invoice $invoice) => $invoice->creditNotes->count());
        $equivalentInstallations = $this->installationsEquivalent(
            $installations->get($keeper->id, collect()),
            $installations->get($duplicate->id, collect())
        );
        $unexpectedRelations = collect($relations)->except(['installations', 'invoices', 'payments', 'credit_notes'])->sum() > 0;
        $strictlySafe = $equivalentInstallations
            && $overlap->isNotEmpty()
            && $payments === 0
            && $creditNotes === 0
            && ! $unexpectedRelations;

        return [
            'customer_id' => $keeper->customer_id,
            'customer_name' => trim(optional($keeper->customer)->first_name.' '.optional($keeper->customer)->last_name),
            'identity_document' => optional($keeper->customer)->identity_document,
            'keeper_contract_id' => $keeper->id,
            'duplicate_contract_id' => $duplicate->id,
            'seconds_apart' => $secondsApart,
            'installations' => $installations->map(fn (Collection $group) => $group->map->toArray()->all())->all(),
            'invoices' => $invoices->map(fn (Collection $group) => $group->map->toArray()->all())->all(),
            'relations' => $relations,
            'relation_details' => $relationDetails,
            'overlap_periods' => $overlap->all(),
            'risk' => $strictlySafe ? 'low' : ($payments > 0 || $creditNotes > 0 || $unexpectedRelations ? 'high' : 'medium'),
            'risk_order' => $strictlySafe ? 1 : ($payments > 0 || $creditNotes > 0 || $unexpectedRelations ? 3 : 2),
            'reason' => $strictlySafe
                ? 'Contratos creados con pocos segundos de diferencia, instalaciones equivalentes y facturas solapadas sin movimientos.'
                : 'Requiere revisión individual por movimientos, relaciones o diferencias operativas.',
            'automatic_available' => $strictlySafe && $keeper->id === 253 && $duplicate->id === 254,
        ];
    }

    private function validateGregorioRecords(Collection $contracts, Collection $installations, Collection $invoices, bool $locked): void
    {
        foreach ([253, 254] as $contractId) {
            $contract = $contracts[$contractId];
            if ((int) $contract->customer_id !== 122
                || (int) $contract->plan_id !== 1
                || optional($contract->start_date)->format('Y-m-d') !== '2026-01-16'
                || round((float) $contract->final_price, 2) !== 65.00
                || $contract->status !== 'active') {
                throw new RuntimeException("El contrato #{$contractId} ya no coincide con los datos confirmados.");
            }
        }

        foreach ([253, 254] as $installationId) {
            $installation = $installations[$installationId];
            if ((int) $installation->contract_id !== $installationId
                || $installation->status !== 'completed'
                || optional($installation->scheduled_date)->format('Y-m-d') !== '2026-01-16'
                || optional($installation->completed_date)->format('Y-m-d') !== '2026-01-16') {
                throw new RuntimeException("La instalación #{$installationId} ya no coincide con el registro histórico confirmado.");
            }
        }

        $expectedInvoices = [
            505 => [253, 'INV-000501', 'void', 'manual', '2026-06'],
            506 => [254, 'INV-000502', 'pending', 'manual', '2026-06'],
            1190 => [253, 'INV-001150', 'pending', 'automatic', '2026-07'],
            1191 => [254, 'INV-001151', 'pending', 'automatic', '2026-07'],
        ];

        foreach ($expectedInvoices as $invoiceId => [$contractId, $number, $status, $generation, $period]) {
            $invoice = $invoices[$invoiceId];
            if ((int) $invoice->contract_id !== $contractId
                || $invoice->invoice_number !== $number
                || $invoice->status !== $status
                || $invoice->generation_type !== $generation
                || round((float) $invoice->total_amount, 2) !== 65.00
                || $this->paymentService->canonicalInvoicePeriodKey($invoice) !== $period) {
                throw new RuntimeException("La factura {$number} ya no coincide con periodo, estado, contrato, generación o monto esperado.");
            }
        }

        if (! preg_match('/doble|duplic/ui', (string) $invoices[505]->notes)) {
            throw new RuntimeException('INV-000501 no conserva una nota que confirme que fue anulada por duplicidad.');
        }
    }

    private function assertNoUnexpectedRelations(array $contractIds, array $invoiceIds, bool $lock): void
    {
        $paymentQuery = Payment::query()->whereIn('invoice_id', $invoiceIds);
        $creditQuery = DB::table('credit_notes')->whereIn('invoice_id', $invoiceIds);
        if ($lock) {
            $paymentQuery->lockForUpdate();
            $creditQuery->lockForUpdate();
        }
        if ($paymentQuery->get()->isNotEmpty()) {
            throw new RuntimeException('Aparecieron pagos en las cuatro facturas; reparación bloqueada.');
        }
        if ($creditQuery->get()->isNotEmpty()) {
            throw new RuntimeException('Aparecieron notas de crédito en las cuatro facturas; reparación bloqueada.');
        }

        foreach (['contracted_services', 'contract_sla', 'customer_credit_movements', 'onboarding_orders', 'router_customer', 'service_requests', 'yape_payment_intents'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'contract_id')) {
                continue;
            }

            $query = DB::table($table)->whereIn('contract_id', $contractIds);
            if ($lock) {
                $query->lockForUpdate();
            }
            if ($query->get()->isNotEmpty()) {
                throw new RuntimeException("Se encontraron relaciones no previstas en {$table}; reparación bloqueada.");
            }
        }
    }

    private function assertGregorioFinalState(Collection $contracts, Collection $installations, Collection $invoices): void
    {
        $pendingDebt = $invoices->where('contract_id', 253)
            ->where('status', 'pending')
            ->sum(fn (Invoice $invoice) => (float) $invoice->pending_amount);

        if ($contracts[253]->status !== 'active'
            || $contracts[254]->status !== 'cancelled'
            || $installations[253]->status !== 'completed'
            || $installations[254]->status !== 'completed'
            || $invoices[505]->status !== 'pending'
            || $invoices[506]->status !== 'void'
            || $invoices[1190]->status !== 'pending'
            || $invoices[1191]->status !== 'void'
            || round($pendingDebt, 2) !== 130.00) {
            throw new RuntimeException('El estado final no coincide con un contrato activo y S/ 130.00 de deuda; se revirtió todo.');
        }
    }

    private function gregorioAlreadyRepaired(Collection $contracts, Collection $installations, Collection $invoices): bool
    {
        return $contracts[253]->status === 'active'
            && $contracts[254]->status === 'cancelled'
            && $installations[253]->status === 'completed'
            && $installations[254]->status === 'completed'
            && $invoices[505]->status === 'pending'
            && $invoices[506]->status === 'void'
            && $invoices[1190]->status === 'pending'
            && $invoices[1191]->status === 'void'
            && str_contains((string) $contracts[254]->notes, self::NOTE_MARKER);
    }

    private function gregorioPreview(string $status, string $message): array
    {
        return [
            'key' => self::GREGORIO_122,
            'status' => $status,
            'message' => $message,
            'customer_id' => 122,
            'keeper_contract_id' => 253,
            'duplicate_contract_id' => 254,
        ];
    }

    private function confirmedPhysicalDeletionCases(): array
    {
        return [
            self::GREGORIO_253_254_PHYSICAL => [
                'customer_name' => 'Gregorio Casilla Oquinaya',
                'keeper_contract_id' => 253,
                'duplicate_contract_id' => 254,
                'duplicate_status' => 'cancelled',
                'keeper_invoices' => [
                    'INV-000501' => ['2026-06', 'manual'],
                    'INV-001150' => ['2026-07', 'automatic'],
                ],
                'duplicate_invoices' => [
                    'INV-000502' => ['2026-06', 'manual'],
                    'INV-001151' => ['2026-07', 'automatic'],
                ],
            ],
            self::LEONIDAS_37_38 => [
                'customer_name' => 'Leónidas Cárdenas Peña',
                'keeper_contract_id' => 37,
                'duplicate_contract_id' => 38,
                'duplicate_status' => 'active',
                'keeper_invoices' => [
                    'INV-000063' => ['2026-06', 'manual'],
                    'INV-000976' => ['2026-07', 'automatic'],
                ],
                'duplicate_invoices' => [
                    'INV-000064' => ['2026-06', 'manual'],
                    'INV-000977' => ['2026-07', 'automatic'],
                ],
            ],
            self::DELIA_159_160 => [
                'customer_name' => 'Delia Paucar Suca',
                'keeper_contract_id' => 159,
                'duplicate_contract_id' => 160,
                'duplicate_status' => 'active',
                'keeper_invoices' => [
                    'INV-000304' => ['2026-06', 'manual'],
                    'INV-001081' => ['2026-07', 'automatic'],
                ],
                'duplicate_invoices' => [
                    'INV-000305' => ['2026-06', 'manual'],
                    'INV-001082' => ['2026-07', 'automatic'],
                ],
            ],
        ];
    }

    private function loadConfirmedPairState(array $case, bool $lock): array
    {
        $contractQuery = Contract::query()->whereIn('id', [
            $case['keeper_contract_id'],
            $case['duplicate_contract_id'],
        ]);
        $installationQuery = Installation::query()->whereIn('contract_id', [
            $case['keeper_contract_id'],
            $case['duplicate_contract_id'],
        ]);
        $invoiceQuery = Invoice::query()->whereIn('contract_id', [
            $case['keeper_contract_id'],
            $case['duplicate_contract_id'],
        ]);

        if ($lock) {
            $contractQuery->lockForUpdate();
            $installationQuery->lockForUpdate();
            $invoiceQuery->lockForUpdate();
        }

        $contracts = $contractQuery->get()->keyBy('id');
        $installations = $installationQuery->get()->groupBy('contract_id');
        $invoices = $invoiceQuery->get()->keyBy('id');
        $keeperExists = $contracts->has($case['keeper_contract_id']);
        $duplicateExists = $contracts->has($case['duplicate_contract_id']);
        $duplicateRelationsExist = $invoices->where('contract_id', $case['duplicate_contract_id'])->isNotEmpty()
            || $installations->get($case['duplicate_contract_id'], collect())->isNotEmpty();

        if ($keeperExists && ! $duplicateExists && ! $duplicateRelationsExist) {
            $preservedInvoices = $invoices->whereIn('invoice_number', array_keys($case['keeper_invoices']));
            if ($preservedInvoices->count() !== count($case['keeper_invoices'])
                || $preservedInvoices->contains(fn (Invoice $invoice) => $invoice->status !== 'pending')) {
                throw new RuntimeException('El duplicado no existe, pero la deuda conservada ya no coincide con el estado final esperado.');
            }

            return compact('contracts', 'installations', 'invoices') + ['already_deleted' => true];
        }

        if (! $keeperExists || ! $duplicateExists) {
            throw new RuntimeException('El par está incompleto pero no coincide con una eliminación final válida.');
        }

        return compact('contracts', 'installations', 'invoices') + ['already_deleted' => false];
    }

    private function validateConfirmedPairState(array $case, array $state, bool $lock): void
    {
        /** @var Collection $contracts */
        $contracts = $state['contracts'];
        /** @var Collection $installations */
        $installations = $state['installations'];
        /** @var Collection $invoices */
        $invoices = $state['invoices'];
        $keeper = $contracts[$case['keeper_contract_id']];
        $duplicate = $contracts[$case['duplicate_contract_id']];

        if ($keeper->status !== 'active'
            || $duplicate->status !== $case['duplicate_status']
            || (int) $keeper->customer_id !== (int) $duplicate->customer_id) {
            throw new RuntimeException('Los contratos ya no conservan los estados confirmados o no pertenecen al mismo cliente.');
        }

        $customerName = trim(optional($keeper->customer)->full_name ?? '');
        if ($customerName !== $case['customer_name']) {
            throw new RuntimeException('Los IDs ya no pertenecen al cliente confirmado.');
        }

        $invoicesByNumber = $invoices->keyBy('invoice_number');
        foreach ($case['keeper_invoices'] as $number => [$expectedPeriod]) {
            $invoice = $invoicesByNumber->get($number);
            if (! $invoice
                || (int) $invoice->contract_id !== $case['keeper_contract_id']
                || $invoice->status !== 'pending'
                || $this->paymentService->canonicalInvoicePeriodKey($invoice) !== $expectedPeriod) {
                throw new RuntimeException("No se encontró la deuda pendiente confirmada {$number} en el contrato que se conservará.");
            }
        }

        $duplicateInvoiceIds = $invoices->where('contract_id', $case['duplicate_contract_id'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $paymentQuery = Payment::query()->whereIn('invoice_id', $duplicateInvoiceIds);
        $creditQuery = DB::table('credit_notes')->whereIn('invoice_id', $duplicateInvoiceIds);
        if ($lock) {
            $paymentQuery->lockForUpdate();
            $creditQuery->lockForUpdate();
        }
        if ($paymentQuery->exists() || $creditQuery->exists()) {
            throw new RuntimeException('Existen pagos o notas de crédito; eliminación física bloqueada.');
        }

    }

    private function relationCounts(array $contractIds): array
    {
        $counts = [];
        foreach (['installations', 'invoices', 'contracted_services', 'contract_sla', 'customer_credit_movements', 'onboarding_orders', 'router_customer', 'service_requests', 'yape_payment_intents'] as $table) {
            $counts[$table] = Schema::hasTable($table) && Schema::hasColumn($table, 'contract_id')
                ? DB::table($table)->whereIn('contract_id', $contractIds)->count()
                : 0;
        }

        $invoiceIds = DB::table('invoices')->whereIn('contract_id', $contractIds)->pluck('id');
        $counts['payments'] = $invoiceIds->isEmpty() ? 0 : DB::table('payments')->whereIn('invoice_id', $invoiceIds)->count();
        $counts['credit_notes'] = $invoiceIds->isEmpty() ? 0 : DB::table('credit_notes')->whereIn('invoice_id', $invoiceIds)->count();

        return $counts;
    }

    private function relationDetails(array $contractIds): array
    {
        $definitions = [
            'contracted_services' => ['id', 'contract_id', 'additional_service_id', 'price'],
            'contract_sla' => ['contract_id', 'sla_id'],
            'customer_credit_movements' => ['id', 'contract_id', 'source_type', 'source_id', 'type', 'amount', 'status'],
            'onboarding_orders' => ['id', 'contract_id', 'installation_id', 'invoice_id', 'status'],
            'router_customer' => ['id', 'contract_id', 'router_id', 'connection_status', 'assigned_ip'],
            'service_requests' => ['id', 'contract_id', 'ticket_number', 'type', 'priority', 'status'],
            'yape_payment_intents' => ['id', 'contract_id', 'invoice_id', 'payment_id', 'expected_amount', 'status'],
        ];

        return collect($definitions)->map(function (array $columns, string $table) use ($contractIds) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'contract_id')) {
                return [];
            }

            $safeColumns = collect($columns)->filter(fn (string $column) => Schema::hasColumn($table, $column))->all();

            return DB::table($table)->select($safeColumns)->whereIn('contract_id', $contractIds)->get()->map(fn ($row) => (array) $row)->all();
        })->all();
    }

    private function installationsEquivalent(Collection $keeper, Collection $duplicate): bool
    {
        if ($keeper->count() !== 1 || $duplicate->count() !== 1) {
            return false;
        }

        $signature = fn (Installation $installation) => [
            optional($installation->scheduled_date)->format('Y-m-d H:i:s'),
            optional($installation->completed_date)->format('Y-m-d H:i:s'),
            $installation->status,
            trim((string) $installation->notes),
        ];

        return $signature($keeper->first()) === $signature($duplicate->first());
    }

    private function appendRepairNote(?string $notes, string $message): string
    {
        if (str_contains((string) $notes, self::NOTE_MARKER)) {
            return (string) $notes;
        }

        return trim((string) $notes)."\n".self::NOTE_MARKER.' ['.now().'] '.$message;
    }
}
