<?php

namespace Modules\Customer\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Customer\Interfaces\AddressRepositoryInterface;
use Modules\Customer\Entities\Address;
use Illuminate\Support\Facades\DB;

class AddressRepository extends BaseRepository implements AddressRepositoryInterface
{
    /**
     * AddressRepository constructor.
     *
     * @param Address $model
     */
    public function __construct(Address $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function getByCustomer($customerId)
    {
        return $this->model->where('customer_id', $customerId)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getByType($customerId, $type)
    {
        return $this->model->where('customer_id', $customerId)
                          ->where('address_type', $type)
                          ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function getPrimaryAddress($customerId)
    {
        return $this->model->where('customer_id', $customerId)
                          ->where('is_primary', true)
                          ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function setAsPrimary($addressId)
    {
        $address = $this->find($addressId);
        
        if (!$address) {
            return false;
        }
        
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Remove primary flag from all customer addresses
            $this->model->where('customer_id', $address->customer_id)
                       ->where('id', '!=', $addressId)
                       ->update(['is_primary' => false]);
            
            // Set this address as primary
            $address->is_primary = true;
            $address->save();
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            return false;
        }
    }
}