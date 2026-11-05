<?php

namespace Modules\Customer\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Entities\AuditLog;
use Modules\Customer\Entities\Customer;
use RuntimeException;

class CustomerMergeService
{
    private const CUSTOMER_REFERENCE_TABLES = [
        'addresses',
        'contracts',
        'customer_credit_movements',
        'customer_lead',
        'documents',
        'emergency_contacts',
        'interactions',
        'onboarding_orders',
        'payment_reminders',
        'router_customer',
        'service_requests',
        'yape_client_evidences',
        'yape_payment_intents',
    ];

    public function candidates(Customer $target): Collection
    {
        $document = trim((string) $target->identity_document);
        $phone = $this->normalizePhone($target->phone);
        $name = $this->normalizeName($target->full_name);

        return Customer::query()
            ->withCount('contracts')
            ->whereKeyNot($target->id)
            ->where(function ($query) use ($document, $phone, $name) {
                if ($document !== '') {
                    $query->orWhere('identity_document', $document);
                }
                if ($phone !== '') {
                    $query->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(phone), ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') = ?",
                        [$phone]
                    );
                }
                if ($name !== '') {
                    $query->orWhereRaw("LOWER(TRIM(CONCAT_WS(' ', first_name, last_name))) = ?", [$name]);
                }
            })
            ->orderBy('id')
            ->get();
    }

    public function preview(Customer $target, array $sourceIds): array
    {
        $sourceIds = collect($sourceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $target->id)
            ->unique()
            ->values();
        $sources = Customer::query()->whereIn('id', $sourceIds)->orderBy('id')->get();
        $blockers = [];
        $warnings = [];

        if ($sourceIds->isEmpty()) {
            $blockers[] = 'Seleccione al menos un cliente duplicado.';
        }
        if ($sources->count() !== $sourceIds->count()) {
            $blockers[] = 'Uno o más clientes seleccionados ya no existen.';
        }

        $documents = $sources->concat([$target])
            ->pluck('identity_document')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        if ($documents->count() > 1) {
            $blockers[] = 'Los registros tienen DNI/RUC diferentes. Corrija los documentos antes de consolidar.';
        }

        $userIds = $sources->concat([$target])->pluck('user_id')->filter()->unique()->values();
        if ($userIds->count() > 1) {
            $blockers[] = 'Los registros tienen cuentas de acceso diferentes. Debe resolverlas manualmente antes de consolidar.';
        }

        if (Schema::hasTable('customer_credit_movements')) {
            $globalCredits = DB::table('customer_credit_movements')
                ->whereIn('customer_id', $sourceIds)
                ->whereNull('contract_id')
                ->count();
            if ($globalCredits > 0) {
                $blockers[] = "Existen {$globalCredits} movimientos de saldo general. Deben conciliarse antes de consolidar.";
            }
        }

        if (Schema::hasTable('router_customer') && Schema::hasColumn('router_customer', 'contract_id')) {
            $routerRowsWithoutContract = DB::table('router_customer')
                ->whereIn('customer_id', $sourceIds)
                ->whereNull('contract_id')
                ->count();
            if ($routerRowsWithoutContract > 0) {
                $blockers[] = "Existen {$routerRowsWithoutContract} asignaciones de router sin contrato. Asócielas al servicio correcto antes de consolidar.";
            }
        }

        $counts = collect(self::CUSTOMER_REFERENCE_TABLES)
            ->filter(fn ($table) => Schema::hasTable($table) && Schema::hasColumn($table, 'customer_id'))
            ->mapWithKeys(fn ($table) => [
                $table => DB::table($table)->whereIn('customer_id', $sourceIds)->count(),
            ])
            ->all();

        if (($counts['contracts'] ?? 0) === 0) {
            $warnings[] = 'Los registros seleccionados no tienen contratos; revise que realmente sean duplicados.';
        }
        $warnings[] = 'La consolidación conservará contratos, facturas, pagos y relaciones; solamente eliminará las fichas duplicadas después de reasignarlas.';

        return compact('target', 'sources', 'sourceIds', 'counts', 'blockers', 'warnings') + [
            'can_merge' => empty($blockers),
        ];
    }

    public function merge(Customer $target, array $sourceIds, int $userId, string $ip): array
    {
        return DB::transaction(function () use ($target, $sourceIds, $userId, $ip) {
            $target = Customer::query()->lockForUpdate()->findOrFail($target->id);
            $preview = $this->preview($target, $sourceIds);

            if (! $preview['can_merge']) {
                throw new RuntimeException(implode(' ', $preview['blockers']));
            }

            /** @var Collection<int, Customer> $sources */
            $sources = Customer::query()
                ->whereIn('id', $preview['sourceIds'])
                ->lockForUpdate()
                ->orderBy('id')
                ->get();
            $before = [
                'target' => $target->toArray(),
                'sources' => $sources->map->toArray()->all(),
                'relation_counts' => $preview['counts'],
            ];

            $this->completeCanonicalData($target, $sources);
            $this->normalizePrimaryAddresses($target, $sources->pluck('id'));

            foreach (self::CUSTOMER_REFERENCE_TABLES as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'customer_id')) {
                    continue;
                }

                DB::table($table)
                    ->whereIn('customer_id', $sources->pluck('id'))
                    ->update(['customer_id' => $target->id]);
            }

            foreach ($sources as $source) {
                $source->delete();
            }

            $contractCount = DB::table('contracts')->where('customer_id', $target->id)->count();
            $hasActiveContract = DB::table('contracts')
                ->where('customer_id', $target->id)
                ->where('status', 'active')
                ->exists();
            $target->update([
                'contract_count' => $contractCount,
                'active' => (bool) ($target->active || $sources->contains(fn (Customer $source) => $source->active)),
                'customer_status' => $hasActiveContract ? 'active' : $target->customer_status,
            ]);

            AuditLog::register(
                $userId,
                'customers_merged',
                'customer',
                'Clientes '.implode(', ', $sources->pluck('id')->all())." consolidados en cliente #{$target->id}",
                $ip,
                $before,
                $target->fresh()->toArray()
            );

            return [
                'target' => $target->fresh(),
                'merged_customer_ids' => $sources->pluck('id')->all(),
                'relation_counts' => $preview['counts'],
            ];
        });
    }

    private function completeCanonicalData(Customer $target, Collection $sources): void
    {
        $attributes = [];
        foreach (['identity_document', 'phone', 'email', 'user_id'] as $field) {
            if (blank($target->{$field})) {
                $value = $sources->pluck($field)->first(fn ($candidate) => filled($candidate));
                if (filled($value)) {
                    $attributes[$field] = $value;
                }
            }
        }

        if ($attributes !== []) {
            $target->update($attributes);
        }
    }

    private function normalizePrimaryAddresses(Customer $target, Collection $sourceIds): void
    {
        if (! Schema::hasTable('addresses') || ! Schema::hasColumn('addresses', 'is_primary')) {
            return;
        }

        $targetHasPrimary = DB::table('addresses')
            ->where('customer_id', $target->id)
            ->where('is_primary', 1)
            ->exists();

        if ($targetHasPrimary) {
            DB::table('addresses')->whereIn('customer_id', $sourceIds)->update(['is_primary' => 0]);
        }
    }

    private function normalizeName(?string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

    private function normalizePhone(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value);
    }
}
