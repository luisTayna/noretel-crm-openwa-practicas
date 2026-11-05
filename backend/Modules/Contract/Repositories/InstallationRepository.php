<?php

namespace Modules\Contract\Repositories;

use Modules\Contract\Entities\Installation;
use Modules\Core\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

class InstallationRepository extends BaseRepository
{
    /**
     * InstallationRepository constructor.
     *
     * @param Installation $model
     */
    public function __construct(Installation $model)
    {
        parent::__construct($model);
    }

    /**
     * Get installation with related entities.
     *
     * @param int $id
     * @return Installation
     */
    public function getWithRelations($id)
    {
        return $this->model->with([
            'onboardingOrder.customer',
            'onboardingOrder.plan',
            'contract.customer', 
            'contract.plan',
            'technician',
            'route',
            'installedEquipment.equipment',
            'usedMaterials.material',
            'photos'
        ])->findOrFail($id);
    }

    /**
     * Find installations by contract id.
     *
     * @param int $contractId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByContract($contractId)
    {
        return $this->model->where('contract_id', $contractId)
            ->with(['technician', 'route'])
            ->orderBy('scheduled_date', 'desc')
            ->get();
    }

    /**
     * Find installations by technician id.
     *
     * @param int $technicianId
     * @param string|null $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByTechnician($technicianId, $status = null)
    {
        $query = $this->model->where('technician_id', $technicianId)
            ->with(['contract.customer', 'onboardingOrder.customer', 'route']);
            
        if ($status) {
            $query->where('status', $status);
        }
        
        return $query->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Find installations by route id.
     *
     * @param int $routeId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByRoute($routeId)
    {
        return $this->model->where('route_id', $routeId)
            ->with(['contract.customer', 'onboardingOrder.customer', 'technician'])
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Create installation with equipment and materials.
     *
     * @param array $installationData
     * @param array $equipment
     * @param array $materials
     * @return Installation
     */
    public function createWithDetails(array $installationData, array $equipment = [], array $materials = [])
    {
        return DB::transaction(function () use ($installationData, $equipment, $materials) {
            // Create the installation
            $installation = $this->create($installationData);
            
            // Add equipment if any
            if (!empty($equipment)) {
                foreach ($equipment as $item) {
                    $installation->installedEquipment()->create($item);
                }
            }
            
            // Add materials if any
            if (!empty($materials)) {
                foreach ($materials as $item) {
                    $installation->usedMaterials()->create($item);
                }
            }
            
            return $installation;
        });
    }

    /**
     * Update installation with equipment and materials.
     *
     * @param int $id
     * @param array $installationData
     * @param array $equipment
     * @param array $materials
     * @return Installation
     */
    public function updateWithDetails($id, array $installationData, array $equipment = [], array $materials = [])
    {
        return DB::transaction(function () use ($id, $installationData, $equipment, $materials) {
            // Update the installation
            $installation = $this->find($id);
            $installation->update($installationData);
            
            // Update equipment if provided
            if (!empty($equipment)) {
                // Remove existing equipment
                $installation->installedEquipment()->delete();
                
                // Add new equipment
                foreach ($equipment as $item) {
                    $installation->installedEquipment()->create($item);
                }
            }
            
            // Update materials if provided
            if (!empty($materials)) {
                // Remove existing materials
                $installation->usedMaterials()->delete();
                
                // Add new materials
                foreach ($materials as $item) {
                    $installation->usedMaterials()->create($item);
                }
            }
            
            return $installation->fresh([
                'installedEquipment.equipment', 
                'usedMaterials.material'
            ]);
        });
    }

    /**
     * Complete an installation.
     *
     * @param int $id
     * @param array $completionData
     * @param array $photos
     * @return Installation
     */
    public function completeInstallation($id, array $completionData, array $photos = [])
    {
        return DB::transaction(function () use ($id, $completionData, $photos) {
            $installation = $this->find($id);
            
            // Update installation with completion data
            $installation->update([
                'completed_date' => $completionData['completed_date'] ?? now(),
                'status' => 'completed',
                'notes' => $completionData['notes'] ?? $installation->notes,
                'customer_signature' => $completionData['customer_signature'] ?? null,
            ]);
            
            // Add photos if any
            if (!empty($photos)) {
                foreach ($photos as $photo) {
                    $installation->photos()->create($photo);
                }
            }
            
            if ($installation->onboardingOrder && in_array($installation->onboardingOrder->status, ['installation_scheduled', 'created', 'installed'], true)) {
                $installation->onboardingOrder->update(['status' => 'contract_pending_30']);
            }

            // Update contract status only for the legacy contract-first flow.
            if ($installation->contract && $installation->contract->status === 'pending_installation') {
                $installation->contract->update(['status' => 'active']);
            }
            
            return $installation->fresh(['photos']);
        });
    }

    /**
     * Get installations scheduled for a date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getScheduledBetweenDates($startDate, $endDate)
    {
        return $this->model->whereBetween('scheduled_date', [$startDate, $endDate])
            ->with(['contract.customer', 'onboardingOrder.customer', 'technician', 'route'])
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Get installations for today.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getScheduledForToday()
    {
        $today = now()->format('Y-m-d');
        
        return $this->model->whereDate('scheduled_date', $today)
            ->with(['contract.customer', 'onboardingOrder.customer', 'technician', 'route'])
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Count installations by status.
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
     * Get pending installations.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingInstallations()
    {
        return $this->model->whereIn('status', ['scheduled', 'in_progress'])
            ->with(['contract.customer', 'onboardingOrder.customer', 'technician'])
            ->orderBy('scheduled_date')
            ->get();
    }

    /**
     * Get late installations.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLateInstallations()
    {
        $today = now()->format('Y-m-d');
        
        return $this->model->whereIn('status', ['scheduled', 'in_progress'])
            ->whereDate('scheduled_date', '<', $today)
            ->with(['contract.customer', 'onboardingOrder.customer', 'technician'])
            ->orderBy('scheduled_date')
            ->get();
    }
}
