<?php

namespace Modules\Services\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Services\Entities\Service;

class ServiceRepository extends BaseRepository
{
    /**
     * ServiceRepository constructor.
     *
     * @param Service $model
     */
    public function __construct(Service $model)
    {
        parent::__construct($model);
    }

    /**
     * Obtener servicios activos.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveServices()
    {
        return $this->model
            ->active()
            ->withCount(['plans', 'additionalServices'])
            ->get();
    }

    /**
     * Obtener servicios para listados administrativos sin provocar N+1 en conteos.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getServicesForIndex()
    {
        return $this->model
            ->withCount(['plans', 'additionalServices'])
            ->get();
    }

    /**
     * Buscar servicios por tipo.
     *
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByType($type)
    {
        return $this->model->where('service_type', $type)->get();
    }

    /**
     * Buscar servicios por tecnología.
     *
     * @param string $technology
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findByTechnology($technology)
    {
        return $this->model->where('technology', $technology)->get();
    }

    /**
     * Obtener servicios con sus planes asociados.
     *
     * @param bool $onlyActive
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getServicesWithPlans($onlyActive = true)
    {
        $query = $this->model->with(['plans' => function($query) use ($onlyActive) {
            if ($onlyActive) {
                $query->where('active', true);
            }
        }])->withCount(['plans', 'additionalServices']);

        if ($onlyActive) {
            $query->where('active', true);
        }

        return $query->get();
    }
}
