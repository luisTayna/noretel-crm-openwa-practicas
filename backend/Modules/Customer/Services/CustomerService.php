<?php

namespace Modules\Customer\Services;

use Modules\Customer\Interfaces\CustomerRepositoryInterface;
use Modules\Customer\Interfaces\AddressRepositoryInterface;
use Modules\Core\Entities\AuditLog;
use Modules\Billing\Entities\Invoice;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * CustomerService constructor.
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        AddressRepositoryInterface $addressRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->addressRepository = $addressRepository;
    }

    /**
     * Create a new customer with addresses
     *
     * @param array $customerData
     * @param array $addressesData
     * @param int $createdBy
     * @param string $ip
     * @return array
     */
    public function createCustomer(array $customerData, array $addressesData, $createdBy, $ip)
    {
        DB::beginTransaction();

        try {
            // Set registration date if not provided
            if (!isset($customerData['registration_date'])) {
                $customerData['registration_date'] = now();
            }
            
            // Create customer
            $customer = $this->customerRepository->create($customerData);
            
            // Create addresses
            $primarySet = false;
            foreach ($addressesData as $addressData) {
                $addressData['customer_id'] = $customer->id;
                
                // Ensure only one primary address
                if (isset($addressData['is_primary']) && $addressData['is_primary']) {
                    if ($primarySet) {
                        $addressData['is_primary'] = false;
                    } else {
                        $primarySet = true;
                    }
                }
                
                $this->addressRepository->create($addressData);
            }
            
            // Register audit
            AuditLog::register(
                $createdBy,
                'customer_created',
                'customer',
                "Cliente creado: {$customer->full_name}",
                $ip,
                null,
                $customer->toArray()
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al crear el cliente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update a customer and its addresses
     *
     * @param int $customerId
     * @param array $customerData
     * @param array $addressesData
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function updateCustomer($customerId, array $customerData, array $addressesData, $updatedBy, $ip)
    {
        DB::beginTransaction();

        try {
            // Get customer before update for audit
            $oldData = $this->customerRepository->find($customerId)->toArray();
            
            // Update customer
            $this->customerRepository->update($customerId, $customerData);
            $customer = $this->customerRepository->find($customerId);
            
            // Handle addresses (create, update, delete)
            if (!empty($addressesData)) {
                $this->handleAddressesUpdate($customerId, $addressesData);
            }
            
            // Register audit
            AuditLog::register(
                $updatedBy,
                'customer_updated',
                'customer',
                "Cliente actualizado: {$customer->full_name}",
                $ip,
                $oldData,
                $customer->toArray()
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al actualizar el cliente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change customer status
     *
     * @param int $customerId
     * @param bool $active
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function changeStatus($customerId, $active, $updatedBy, $ip)
    {
        $customer = $this->customerRepository->find($customerId);

        if (! $customer) {
            return ['success' => false, 'message' => 'Cliente no encontrado.'];
        }

        try {
            return DB::transaction(function () use ($customer, $customerId, $active, $updatedBy, $ip) {
                $customer->refresh();
                $oldStatus = (bool) $customer->active;

                if ($active) {
                    $customer->update(['active' => true, 'deactivated_at' => null]);

                    $reactivatedContracts = $customer->contracts()
                        ->where('status', 'suspended')
                        ->where('suspension_reason', 'customer_deactivated')
                        ->update(['status' => 'active', 'suspension_reason' => null]);

                    AuditLog::register(
                        $updatedBy,
                        'customer_activated',
                        'customer',
                        "Cliente activado: {$customer->full_name}",
                        $ip,
                        ['active' => $oldStatus],
                        [
                            'customer_id' => $customer->id,
                            'active' => true,
                            'reactivated_contracts' => $reactivatedContracts,
                        ]
                    );

                    return [
                        'success' => true,
                        'message' => 'Cliente activado correctamente.',
                        'reactivated_contracts' => $reactivatedContracts,
                    ];
                }

                $deactivatedAt = now();
                $cutoff = $deactivatedAt->copy()->startOfMonth();
                $customer->update(['active' => false, 'deactivated_at' => $deactivatedAt]);

                $suspendedContracts = $customer->contracts()
                    ->where('status', 'active')
                    ->update(['status' => 'suspended', 'suspension_reason' => 'customer_deactivated']);

                $futureInvoices = Invoice::query()
                    ->whereHas('contract', fn ($query) => $query->where('customer_id', $customerId))
                    ->whereDate('issue_date', '>=', $cutoff->toDateString())
                    ->whereIn('status', ['pending', 'partial', 'overdue'])
                    ->with(['payments', 'creditNotes'])
                    ->get();

                $voidedInvoiceIds = [];
                $reviewInvoiceIds = [];

                foreach ($futureInvoices as $invoice) {
                    $hasMoneyApplied = $invoice->payments->where('status', 'completed')->isNotEmpty()
                        || $invoice->creditNotes->where('status', 'applied')->isNotEmpty();

                    if ($hasMoneyApplied) {
                        $invoice->update(['deactivation_review_required' => true]);
                        $reviewInvoiceIds[] = $invoice->id;
                        continue;
                    }

                    $invoice->update([
                        'status' => 'void',
                        'notes' => trim((string) $invoice->notes . "\nAnulada por baja del cliente el {$deactivatedAt->toDateString()}"),
                    ]);
                    $voidedInvoiceIds[] = $invoice->id;
                }

                AuditLog::register(
                    $updatedBy,
                    'customer_deactivated',
                    'customer',
                    "Cliente desactivado: {$customer->full_name}",
                    $ip,
                    ['active' => $oldStatus],
                    [
                        'customer_id' => $customer->id,
                        'active' => false,
                        'deactivated_at' => $deactivatedAt->toDateTimeString(),
                        'suspended_contracts' => $suspendedContracts,
                        'voided_invoice_ids' => $voidedInvoiceIds,
                        'review_invoice_ids' => $reviewInvoiceIds,
                    ]
                );

                $message = 'Cliente desactivado correctamente.';
                if ($reviewInvoiceIds !== []) {
                    $message .= ' Hay facturas con pagos del mes de baja o posteriores que requieren revisión manual.';
                }

                return [
                    'success' => true,
                    'message' => $message,
                    'suspended_contracts' => $suspendedContracts,
                    'voided_invoice_ids' => $voidedInvoiceIds,
                    'review_invoice_ids' => $reviewInvoiceIds,
                ];
            });
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error al cambiar el estado del cliente: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a customer
     *
     * @param int $customerId
     * @param int $deletedBy
     * @param string $ip
     * @return array
     */
    public function deleteCustomer(
        $customerId,
        $deletedBy,
        $ip,
        bool $deleteDeletableContracts = false,
        bool $forceDeleteRelatedHistory = false
    )
    {
        // Get customer data for audit
        $customer = $this->customerRepository->find($customerId);
        
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Cliente no encontrado.'
            ];
        }
        
        $customerData = $customer->toArray();
        
        if ($customer->hasContracts()) {
            if (!$deleteDeletableContracts) {
                return [
                    'success' => false,
                    'message' => 'El cliente tiene contratos asociados. Confirme si desea eliminar primero los contratos eliminables.'
                ];
            }

            $nonDeletableContracts = $customer->contracts()
                ->withCount(['installations', 'invoices'])
                ->get()
                ->filter(function ($contract) {
                    return $contract->installations_count > 0 || $contract->invoices_count > 0;
                });

            if ($nonDeletableContracts->isNotEmpty() && !$forceDeleteRelatedHistory) {
                $contractIds = $nonDeletableContracts->pluck('id')->map(function ($id) {
                    return "#{$id}";
                })->implode(', ');

                return [
                    'success' => false,
                    'message' => "No se puede eliminar el cliente porque los contratos {$contractIds} tienen facturas o instalaciones. Anule esos contratos o desactive el cliente para conservar la trazabilidad."
                ];
            }
        }
        
        DB::beginTransaction();
        
        try {
            if ($deleteDeletableContracts) {
                $customer->contracts()->with(['customer'])->get()->each(function ($contract) use ($deletedBy, $ip, $forceDeleteRelatedHistory) {
                    $contractData = $contract->toArray();
                    $customerName = $contract->customer
                        ? "{$contract->customer->first_name} {$contract->customer->last_name}"
                        : 'Cliente eliminado';

                    if ($forceDeleteRelatedHistory) {
                        $contract->invoices()->delete();
                        $contract->installations()->delete();
                    }

                    $contract->delete();

                    AuditLog::register(
                        $deletedBy,
                        'contract_deleted',
                        'contracts',
                        "Contrato eliminado junto con cliente: {$customerName}",
                        $ip,
                        $contractData,
                        null
                    );
                });
            }

            // Delete customer (addresses, documents, etc. will be deleted by cascade)
            $this->customerRepository->delete($customerId);
            
            // Register audit
            AuditLog::register(
                $deletedBy,
                'customer_deleted',
                'customer',
                "Cliente eliminado: {$customer->full_name}",
                $ip,
                $customerData,
                null
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Cliente eliminado correctamente.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al eliminar el cliente: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Search customers
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function searchCustomers(array $filters, $perPage = 15)
    {
        return $this->customerRepository->getWithFilters($filters, $perPage);
    }

    public function searchSuggestions(string $search, int $limit = 8)
    {
        return $this->customerRepository->searchSuggestions($search, $limit);
    }

    /**
     * Get dashboard stats for customers
     *
     * @return array
     */
    public function getDashboardStats()
    {
        return $this->customerRepository->getDashboardStats();
    }

    /**
     * Handle addresses update
     *
     * @param int $customerId
     * @param array $addressesData
     * @return void
     */
    protected function handleAddressesUpdate($customerId, array $addressesData)
    {
        // Get existing addresses
        $existingAddresses = $this->addressRepository->getByCustomer($customerId);
        $existingIds = $existingAddresses->pluck('id')->toArray();
        
        // Track which addresses are primary
        $primaryFound = false;
        
        foreach ($addressesData as $addressData) {
            // If ID is provided, update existing address
            if (isset($addressData['id']) && in_array($addressData['id'], $existingIds)) {
                $addressId = $addressData['id'];
                unset($addressData['id']);
                
                // Handle primary flag
                if (isset($addressData['is_primary']) && $addressData['is_primary']) {
                    if ($primaryFound) {
                        $addressData['is_primary'] = false;
                    } else {
                        $primaryFound = true;
                    }
                }
                
                $this->addressRepository->update($addressId, $addressData);
            } else {
                // Create new address
                $addressData['customer_id'] = $customerId;
                
                // Handle primary flag
                if (isset($addressData['is_primary']) && $addressData['is_primary']) {
                    if ($primaryFound) {
                        $addressData['is_primary'] = false;
                    } else {
                        $primaryFound = true;
                    }
                }
                
                $this->addressRepository->create($addressData);
            }
        }
        
        // Ensure at least one primary address
        if (!$primaryFound && $existingAddresses->count() > 0) {
            $firstAddress = $existingAddresses->first();
            $this->addressRepository->update($firstAddress->id, ['is_primary' => true]);
        }
    }
}
