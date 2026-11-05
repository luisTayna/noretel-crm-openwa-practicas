<?php

namespace Modules\Customer\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Customer\Interfaces\InteractionRepositoryInterface;
use Modules\Customer\Entities\Interaction;

class InteractionRepository extends BaseRepository implements InteractionRepositoryInterface
{
    /**
     * InteractionRepository constructor.
     *
     * @param Interaction $model
     */
    public function __construct(Interaction $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function getByCustomer($customerId, $perPage = 15)
    {
        return $this->model->where('customer_id', $customerId)
                          ->orderBy('date', 'desc')
                          ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getByType($customerId, $type, $perPage = 15)
    {
        return $this->model->where('customer_id', $customerId)
                          ->where('interaction_type', $type)
                          ->orderBy('date', 'desc')
                          ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiringFollowUp($customerId, $perPage = 15)
    {
        return $this->model->where('customer_id', $customerId)
                          ->where('follow_up_required', true)
                          ->orderBy('date', 'desc')
                          ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getInDateRange($customerId, $startDate, $endDate, $perPage = 15)
    {
        return $this->model->where('customer_id', $customerId)
                          ->whereBetween('date', [$startDate, $endDate])
                          ->orderBy('date', 'desc')
                          ->paginate($perPage);
    }
}