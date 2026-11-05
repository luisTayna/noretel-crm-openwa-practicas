<?php

namespace Modules\Customer\Interfaces;

use Modules\Core\Interfaces\RepositoryInterface;

interface AddressRepositoryInterface extends RepositoryInterface
{
    /**
     * Get addresses by customer
     *
     * @param int $customerId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByCustomer($customerId);

    /**
     * Get addresses by type
     *
     * @param int $customerId
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getByType($customerId, $type);

    /**
     * Get primary address for customer
     *
     * @param int $customerId
     * @return \Modules\Customer\Entities\Address|null
     */
    public function getPrimaryAddress($customerId);

    /**
     * Set address as primary
     *
     * @param int $addressId
     * @return bool
     */
    public function setAsPrimary($addressId);
}