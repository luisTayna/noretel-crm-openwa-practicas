<?php

namespace Modules\Customer\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;

interface InteractionRepositoryInterface extends RepositoryInterface
{
    /**
     * Get interactions by customer
     *
     * @param int $customerId
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getByCustomer($customerId, $perPage = 15);

    /**
     * Get interactions by type
     *
     * @param int $customerId
     * @param string $type
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getByType($customerId, $type, $perPage = 15);

    /**
     * Get interactions requiring follow-up
     *
     * @param int $customerId
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getRequiringFollowUp($customerId, $perPage = 15);

    /**
     * Get interactions in date range
     *
     * @param int $customerId
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getInDateRange($customerId, $startDate, $endDate, $perPage = 15);
}