<?php

namespace Modules\Contract\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Contract\Entities\Node;

class NodeRepository extends BaseRepository
{
    /**
     * NodeRepository constructor.
     *
     * @param Node $model
     */
    public function __construct(Node $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all active nodes.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveNodes()
    {
        return $this->model->where('status', 'active')->get();
    }
}