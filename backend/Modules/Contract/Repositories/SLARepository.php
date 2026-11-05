<?php

namespace Modules\Contract\Repositories;

use Modules\Contract\Entities\SLA;
use Modules\Core\Repositories\BaseRepository;

class SLARepository extends BaseRepository
{
    /**
     * SLARepository constructor.
     *
     * @param SLA $model
     */
    
    public function __construct(SLA $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all active SLAs.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveSLAs()
    {
        return $this->model->where('active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get SLAs by service level.
     *
     * @param string $serviceLevel
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByServiceLevel($serviceLevel)
    {
        return $this->model->where('service_level', $serviceLevel)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get SLAs suitable for a specific plan type.
     *
     * @param string $planType
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSuitableForPlanType($planType)
    {
        if ($planType === 'business') {
            return $this->model->whereIn('service_level', ['premium', 'standard'])
                ->orderBy('name')
                ->get();
        }
        
        return $this->model->where('service_level', 'basic')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get SLA usage statistics.
     *
     * @return array
     */
    public function getSLAUsageStats()
    {
        $result = [];
        
        $slas = $this->all();
        
        foreach ($slas as $sla) {
            $result[] = [
                'name' => $sla->name,
                'service_level' => $sla->service_level,
                'contracts_count' => $sla->contracts->count(),
            ];
        }
        
        return $result;
    }

    /**
     * Find SLA by name.
     *
     * @param string $name
     * @return SLA|null
     */
    public function findByName($name)
    {
        return $this->model->where('name', $name)->first();
    }
}