<?php

namespace Modules\Core\Repositories;

use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\DB;

class AuditRepository extends BaseRepository
{
    /**
     * AuditRepository constructor.
     *
     * @param AuditLog $model
     */
    public function __construct(AuditLog $model)
    {
        parent::__construct($model);
    }

    /**
     * Get filtered audit logs
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getFilteredLogs(array $filters, $perPage = 15)
    {
        $query = $this->model->with('user');

        // Filtrar por usuario
        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filtrar por módulo
        if (isset($filters['module']) && $filters['module']) {
            $query->where('module', $filters['module']);
        }

        // Filtrar por tipo de acción
        if (isset($filters['action_type']) && $filters['action_type']) {
            $query->where('action_type', $filters['action_type']);
        }

        // Filtrar por fecha desde
        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->whereDate('action_date', '>=', $filters['date_from']);
        }

        // Filtrar por fecha hasta
        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->whereDate('action_date', '<=', $filters['date_to']);
        }

        // Ordenar según criterio
        $query->orderBy('action_date', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get all filtered logs without pagination
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllFilteredLogs(array $filters)
    {
        $query = $this->model->with('user');

        // Aplicar los mismos filtros que getFilteredLogs
        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['module']) && $filters['module']) {
            $query->where('module', $filters['module']);
        }

        if (isset($filters['action_type']) && $filters['action_type']) {
            $query->where('action_type', $filters['action_type']);
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->whereDate('action_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->whereDate('action_date', '<=', $filters['date_to']);
        }

        $query->orderBy('action_date', 'desc');

        return $query->get();
    }

    /**
     * Get statistics by module
     *
     * @param \DateTime $startDate
     * @return \Illuminate\Support\Collection
     */
    public function getModuleStats(\DateTime $startDate)
    {
        return $this->model->select('module', DB::raw('count(*) as total'))
            ->where('action_date', '>=', $startDate)
            ->groupBy('module')
            ->orderBy('total', 'desc')
            ->get();
    }

    /**
     * Get statistics by action type
     *
     * @param \DateTime $startDate
     * @return \Illuminate\Support\Collection
     */
    public function getActionStats(\DateTime $startDate)
    {
        return $this->model->select('action_type', DB::raw('count(*) as total'))
            ->where('action_date', '>=', $startDate)
            ->groupBy('action_type')
            ->orderBy('total', 'desc')
            ->get();
    }

    /**
     * Get statistics by user (top users)
     *
     * @param \DateTime $startDate
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getUserStats(\DateTime $startDate, $limit = 10)
    {
        return $this->model->select('user_id', DB::raw('count(*) as total'))
            ->with('user:id,username')
            ->where('action_date', '>=', $startDate)
            ->groupBy('user_id')
            ->orderBy('total', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics by day
     *
     * @param \DateTime $startDate
     * @return \Illuminate\Support\Collection
     */
    public function getDailyStats(\DateTime $startDate)
    {
        return $this->model->select(DB::raw('DATE(action_date) as date'), DB::raw('count(*) as total'))
            ->where('action_date', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Register a new audit log
     *
     * @param int $userId
     * @param string $actionType
     * @param string $module
     * @param string $actionDetail
     * @param string $sourceIp
     * @param array|null $previousData
     * @param array|null $newData
     * @return AuditLog
     */
    public function register($userId, $actionType, $module, $actionDetail, $sourceIp, $previousData = null, $newData = null)
    {
        return AuditLog::register($userId, $actionType, $module, $actionDetail, $sourceIp, $previousData, $newData);
    }
}
