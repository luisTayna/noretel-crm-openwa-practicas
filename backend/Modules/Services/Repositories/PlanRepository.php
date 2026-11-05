<?php

namespace Modules\Services\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Services\Entities\Plan;

class PlanRepository extends BaseRepository
{
    /**
     * PlanRepository constructor.
     *
     * @param Plan $model
     */
    public function __construct(Plan $model)
    {
        parent::__construct($model);
    }

    /**
     * Obtener planes activos.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActivePlans()
    {
        return $this->model
            ->active()
            ->with(['service', 'promotions' => function ($query) {
                $query->currentlyActive();
            }])
            ->get();
    }

    /**
     * Obtener planes para listados administrativos con relaciones mínimas precargadas.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPlansForIndex()
    {
        return $this->model
            ->with(['service', 'promotions' => function ($query) {
                $query->currentlyActive();
            }])
            ->get();
    }

    /**
     * Obtener planes por servicio.
     *
     * @param int $serviceId
     * @param bool $onlyActive
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPlansByService($serviceId, $onlyActive = true)
    {
        $query = $this->model
            ->where('service_id', $serviceId)
            ->with(['service', 'promotions' => function ($query) {
                $query->currentlyActive();
            }]);

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get();
    }

    /**
     * Obtener planes con sus promociones activas.
     *
     * @param bool $onlyActive
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPlansWithPromotions($onlyActive = true)
    {
        $query = $this->model->with(['promotions' => function($query) {
            $query->currentlyActive();
        }, 'service']);

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get();
    }

    /**
     * Actualizar o asignar promociones a un plan.
     *
     * @param int $planId
     * @param array $promotionIds
     * @return bool
     */
    public function syncPromotions($planId, array $promotionIds)
    {
        $plan = $this->find($planId);
        $plan->promotions()->sync($promotionIds);
        return true;
    }
}
