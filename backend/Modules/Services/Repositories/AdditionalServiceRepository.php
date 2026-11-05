<?php

namespace Modules\Services\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Services\Entities\AdditionalService;

class AdditionalServiceRepository extends BaseRepository
{
    /**
     * AdditionalServiceRepository constructor.
     *
     * @param AdditionalService $model
     */
    public function __construct(AdditionalService $model)
    {
        parent::__construct($model);
    }

    /**
     * Obtener servicios adicionales por servicio principal.
     *
     * @param int $serviceId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByService($serviceId)
    {
        return $this->model->where('service_id', $serviceId)->get();
    }

    /**
     * Obtener servicios adicionales configurables.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getConfigurableServices()
    {
        return $this->model->where('configurable', true)->get();
    }

    /**
     * Obtener todos los servicios adicionales activos.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveServices()
    {
        return $this->model->all();
    }
}
