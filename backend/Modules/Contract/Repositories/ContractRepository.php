<?php

namespace Modules\Contract\Repositories;

use Modules\Contract\Entities\Contract;
use Modules\Core\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class ContractRepository extends BaseRepository
{
    /**
     * ContractRepository constructor.
     *
     * @param Contract $model
     */
    public function __construct(Contract $model)
    {
        parent::__construct($model);
    }

    /**
     * Get contract with related entities.
     *
     * @param int $id
     * @return Contract
     */
    public function getWithRelations($id)
    {
        return $this->model->with([
            'customer',
            'plan',
            'node',
            'contractedServices.additionalService',
            'slas',
            'installations'
        ])->findOrFail($id);
    }

    /**
     * Find contracts by customer id.
     *
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByCustomer($customerId)
    {
        return $this->model->where('customer_id', $customerId)
            ->with(['plan', 'sla'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Find active contracts by customer id.
     *
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findActiveByCustomer($customerId)
    {
        return $this->model->where('customer_id', $customerId)
            ->where('status', 'active')
            ->with(['plan', 'sla'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a new contract with contracted services.
     *
     * @param array $contractData
     * @param array $contractedServices
     * @return Contract
     */
    public function createWithServices(array $contractData, array $contractedServices = [])
    {
        return DB::transaction(function () use ($contractData, $contractedServices) {
            // Create the contract
            $contract = $this->create($contractData);

            // Add contracted services if any
            if (!empty($contractedServices)) {
                foreach ($contractedServices as $service) {
                    $contract->contractedServices()->create($service);
                }
            }

            return $contract;
        });
    }

    /**
     * Update a contract with contracted services.
     *
     * @param int $id
     * @param array $contractData
     * @param array $contractedServices
     * @return Contract
     */
    public function updateWithServices($id, array $contractData, array $contractedServices = [])
    {
        return DB::transaction(function () use ($id, $contractData, $contractedServices) {
            // Update the contract
            $contract = $this->find($id);
            $contract->update($contractData);

            // Sync contracted services if provided
            if (!empty($contractedServices)) {
                // Remove existing services
                $contract->contractedServices()->delete();

                // Add new services
                foreach ($contractedServices as $service) {
                    $contract->contractedServices()->create($service);
                }
            }

            return $contract->fresh(['contractedServices.additionalService']);
        });
    }

    /**
     * Count contracts by status.
     *
     * @return array
     */
    public function countByStatus()
    {
        return $this->model->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }

    /**
     * Get contracts that are near expiration.
     *
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getNearExpiration($days = 30)
    {
        $expirationDate = now()->addDays($days);

        return $this->model->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', $expirationDate)
            ->where('end_date', '>', now())
            ->with(['customer', 'plan'])
            ->get();
    }

    /**
     * Get expired contracts.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExpired()
    {
        return $this->model->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->with(['customer', 'plan'])
            ->get();
    }

    /**
     * Get contracts created between dates.
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCreatedBetweenDates($startDate, $endDate)
    {
        return $this->model->whereBetween('created_at', [$startDate, $endDate])
            ->with(['customer', 'plan'])
            ->get();
    }

    /**
     * Renew a contract.
     *
     * @param int $id
     * @param array $renewalData
     * @return Contract
     */
    public function renew($id, array $renewalData)
    {
        return DB::transaction(function () use ($id, $renewalData) {
            $contract = $this->find($id);

            // Update contract with renewal data
            $contract->update([
                'start_date' => $renewalData['start_date'] ?? now(),
                'end_date' => $renewalData['end_date'] ?? null,
                'status' => 'active',
                'final_price' => $renewalData['final_price'] ?? $contract->final_price,
            ]);

            // Update plan if provided
            if (isset($renewalData['plan_id'])) {
                $contract->plan_id = $renewalData['plan_id'];
                $contract->save();
            }

            return $contract->fresh();
        });
    }

    /**
     * Cancel a contract.
     *
     * @param int $id
     * @param string $reason
     * @return Contract
     */
    public function cancel($id, $reason = null)
    {
        $contract = $this->find($id);

        $contract->update([
            'status' => 'cancelled',
            'end_date' => now(),
            'notes' => $reason,
        ]);

        return $contract;
    }

    /**
     * Get monthly contracts count for the last 12 months.
     *
     * @return array
     */
    public function getMonthlyContractsCount()
    {
        $result = $this->model->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $data = [];
        foreach ($result as $row) {
            $monthName = date('F', mktime(0, 0, 0, $row->month, 1));
            $data[$monthName] = $row->total;
        }

        return $data;
    }

    /**
     * Get contracts by node.
     *
     * @param int $nodeId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByNode($nodeId)
    {
        return $this->model->where('node_id', $nodeId)
            ->with(['customer', 'plan'])
            ->get();
    }

    /**
     * Get active contracs.
     * @return \Illuminate\Database\Eloquent\Collection
     *
     */
    public function getActiveContracts()
    {
        return $this->model->where('status', 'active')
            ->with(['customer', 'plan'])
            ->get();
    }

    /**
     * Search contracts for Select2 (AJAX).
     * Includes both 'active' and 'pending_installation' statuses to allow initial billing.
     *
     * @param string|null $term
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchForSelect2($term = null)
    {
        $query = $this->model->whereIn('status', ['active', 'pending_installation'])
            ->with(['customer', 'plan', 'contractedServices.additionalService']);

        if ($term) {
            $query->where(function($q) use ($term) {
                // Search by contract ID
                $q->where('id', 'like', "%{$term}%")
                  // Search by customer relations
                  ->orWhereHas('customer', function($cq) use ($term) {
                      $cq->where('first_name', 'like', "%{$term}%")
                         ->orWhere('last_name', 'like', "%{$term}%")
                         ->orWhere('identity_document', 'like', "%{$term}%");
                  });
            });
        }

        return $query->limit(20)->get();
    }

    /**
     * Get contracts ready for billing generation.
     * This considers active contracts and those recently installed that need initial billing.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getContractsForBilling()
    {
        // For a mass generation feature, we typically want 'active' contracts.
        // You might want to filter by next_billing_date or similar in a production env.
        return $this->model->where('status', 'active')
            ->whereHas('customer', fn ($query) => $query->where('active', true))
            ->with(['customer', 'plan', 'contractedServices'])
            ->get();
    }
}
