<?php

namespace Modules\Customer\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;
use Modules\Customer\Entities\Customer;

interface CustomerRepositoryInterface extends RepositoryInterface
{
    /**
     * Find customer by email
     *
     * @param string $email
     * @return Customer|null
     */
    public function findByEmail($email);

    /**
     * Find customer by identity document
     *
     * @param string $document
     * @return Customer|null
     */
    public function findByIdentityDocument($document);

    /**
     * Get customers by type
     *
     * @param string $type
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getByType($type, $perPage = 15);

    /**
     * Get customers by segment
     *
     * @param string $segment
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getBySegment($segment, $perPage = 15);

    /**
     * Search customers by name, email, or document
     *
     * @param string $search
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function search($search, $perPage = 15);

    /**
     * Return lightweight customer matches for autocomplete.
     *
     * @return \Illuminate\Support\Collection
     */
    public function searchSuggestions(string $search, int $limit = 8);

    /**
     * Get customers with filters
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getWithFilters(array $filters, $perPage = 15);
}
