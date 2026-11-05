<?php

namespace Modules\Customer\Repositories;

use Modules\Core\Repositories\BaseRepository;
use Modules\Customer\Interfaces\LeadRepositoryInterface;
use Modules\Customer\Entities\Lead;
use Modules\Customer\Entities\Customer;
use Illuminate\Support\Facades\DB;

class LeadRepository extends BaseRepository implements LeadRepositoryInterface
{
    /**
     * LeadRepository constructor.
     *
     * @param Lead $model
     */
    public function __construct(Lead $model)
    {
        parent::__construct($model);
    }

    /**
     * {@inheritdoc}
     */
    public function getByStatus($status, $perPage = 15)
    {
        return $this->model->where('status', $status)
                          ->orderBy('capture_date', 'desc')
                          ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getBySource($source, $perPage = 15)
    {
        return $this->model->where('source', $source)
                          ->orderBy('capture_date', 'desc')
                          ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function getUnconverted($perPage = 15)
    {
        return $this->model->whereDoesntHave('customers')
                          ->orderBy('capture_date', 'desc')
                          ->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function search($search, $perPage = 15)
    {
        return $this->model->where(function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('contact', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('interest', 'like', "%{$search}%");
        })->paginate($perPage);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToCustomer($leadId, array $customerData, $notes = null)
    {
        $lead = $this->find($leadId);
        
        if (!$lead) {
            throw new \Exception('Lead not found');
        }
        
        DB::beginTransaction();
        
        try {
            $addresses = $customerData['addresses'] ?? [];
            unset($customerData['addresses']);

            // Create customer
            $customer = Customer::create($customerData);

            foreach ($addresses as $address) {
                $customer->addresses()->create($address);
            }
            
            // Associate lead with customer
            $lead->customers()->attach($customer->id, [
                'conversion_date' => now(),
                'notes' => $notes
            ]);
            
            // Update lead status
            $lead->update(['status' => 'converted']);
            
            DB::commit();
            return $customer;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
