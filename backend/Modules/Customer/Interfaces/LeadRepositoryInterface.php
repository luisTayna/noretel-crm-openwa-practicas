<?php

namespace Modules\Customer\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;

interface LeadRepositoryInterface extends RepositoryInterface
{
    /**
     * Get leads by status
     *
     * @param string $status
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getByStatus($status, $perPage = 15);

    /**
     * Get leads by source
     *
     * @param string $source
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getBySource($source, $perPage = 15);

    /**
     * Get unconverted leads
     *
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getUnconverted($perPage = 15);

    /**
     * Search leads by name or contact
     *
     * @param string $search
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function search($search, $perPage = 15);

    /**
     * Convert lead to customer
     *
     * @param int $leadId
     * @param array $customerData
     * @param string|null $notes
     * @return \Modules\Customer\Entities\Customer
     */
    public function convertToCustomer($leadId, array $customerData, $notes = null);
}