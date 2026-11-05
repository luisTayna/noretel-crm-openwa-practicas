<?php

namespace Modules\Contract\Repositories;

use Modules\Contract\Entities\Route;
use Modules\Core\Repositories\BaseRepository;

class RouteRepository extends BaseRepository
{
    /**
     * RouteRepository constructor.
     *
     * @param Route $model
     */
    public function __construct(Route $model)
    {
        parent::__construct($model);
    }

    /**
     * Get route with its installations.
     *
     * @param int $id
     * @return Route
     */
    public function getWithInstallations($id)
    {
        return $this->model->with([
            'installations.contract.customer',
            'installations.technician'
        ])->findOrFail($id);
    }

    /**
     * Get routes by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByDateRange($startDate, $endDate)
    {
        return $this->model->whereBetween('date', [$startDate, $endDate])
            ->with(['installations'])
            ->orderBy('date')
            ->orderBy('zone')
            ->get();
    }

    /**
     * Get routes for today.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForToday()
    {
        $today = now()->format('Y-m-d');
        
        return $this->model->whereDate('date', $today)
            ->with(['installations.contract.customer', 'installations.technician'])
            ->orderBy('zone')
            ->orderBy('order')
            ->get();
    }

    /**
     * Get routes by status.
     *
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByStatus($status)
    {
        return $this->model->where('status', $status)
            ->with(['installations'])
            ->orderBy('date')
            ->get();
    }

    /**
     * Get routes by zone.
     *
     * @param string $zone
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByZone($zone)
    {
        return $this->model->where('zone', $zone)
            ->with(['installations'])
            ->orderBy('date')
            ->get();
    }

    /**
     * Get active routes with pending installations.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveRoutesWithInstallations()
    {
        return $this->model->whereIn('status', ['scheduled', 'in_progress'])
            ->whereHas('installations', function ($query) {
                $query->whereIn('status', ['scheduled', 'in_progress']);
            })
            ->with(['installations.contract.customer', 'installations.technician'])
            ->orderBy('date')
            ->get();
    }

    /**
     * Change route status.
     *
     * @param int $id
     * @param string $status
     * @return Route
     */
    public function changeStatus($id, $status)
    {
        $route = $this->find($id);
        $route->update(['status' => $status]);
        
        return $route;
    }

    /**
     * Get available zones.
     *
     * @return array
     */
    public function getAvailableZones()
    {
        return $this->model->select('zone')
            ->distinct()
            ->pluck('zone')
            ->toArray();
    }
}