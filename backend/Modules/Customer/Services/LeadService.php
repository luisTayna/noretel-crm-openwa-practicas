<?php

namespace Modules\Customer\Services;

use Modules\Customer\Interfaces\LeadRepositoryInterface;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\DB;

class LeadService
{
    /**
     * @var LeadRepositoryInterface
     */
    protected $leadRepository;

    /**
     * LeadService constructor.
     *
     * @param LeadRepositoryInterface $leadRepository
     */
    public function __construct(LeadRepositoryInterface $leadRepository)
    {
        $this->leadRepository = $leadRepository;
    }

    /**
     * Create a new lead
     *
     * @param array $data
     * @param int $createdBy
     * @param string $ip
     * @return array
     */
    public function createLead(array $data, $createdBy, $ip)
    {
        // Set capture date if not provided
        $data['capture_date'] = $data['capture_date'] ?? now();
        $data['status'] = $data['status'] ?? 'new';
        
        // Create lead
        $lead = $this->leadRepository->create($data);
        
        // Register audit
        AuditLog::register(
            $createdBy,
            'lead_created',
            'customer_lead',
            "Lead creado: {$lead->name}",
            $ip,
            null,
            $lead->toArray()
        );
        
        return [
            'success' => true,
            'lead' => $lead
        ];
    }

    /**
     * Update a lead
     *
     * @param int $leadId
     * @param array $data
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function updateLead($leadId, array $data, $updatedBy, $ip)
    {
        // Get lead
        $lead = $this->leadRepository->find($leadId);
        
        if (!$lead) {
            return [
                'success' => false,
                'message' => 'Lead no encontrado.'
            ];
        }
        
        $oldData = $lead->toArray();
        
        // Update lead
        $this->leadRepository->update($leadId, $data);
        $lead = $this->leadRepository->find($leadId);
        
        // Register audit
        AuditLog::register(
            $updatedBy,
            'lead_updated',
            'customer_lead',
            "Lead actualizado: {$lead->name}",
            $ip,
            $oldData,
            $lead->toArray()
        );
        
        return [
            'success' => true,
            'lead' => $lead
        ];
    }

    /**
     * Delete a lead
     *
     * @param int $leadId
     * @param int $deletedBy
     * @param string $ip
     * @return array
     */
    public function deleteLead($leadId, $deletedBy, $ip)
    {
        // Get lead
        $lead = $this->leadRepository->find($leadId);
        
        if (!$lead) {
            return [
                'success' => false,
                'message' => 'Lead no encontrado.'
            ];
        }
        
        // Check if lead is already converted
        if ($lead->isConverted()) {
            return [
                'success' => false,
                'message' => 'No se puede eliminar un lead que ya ha sido convertido a cliente.'
            ];
        }
        
        $leadData = $lead->toArray();
        
        // Delete lead
        $this->leadRepository->delete($leadId);
        
        // Register audit
        AuditLog::register(
            $deletedBy,
            'lead_deleted',
            'customer_lead',
            "Lead eliminado: {$lead->name}",
            $ip,
            $leadData,
            null
        );
        
        return [
            'success' => true,
            'message' => 'Lead eliminado correctamente.'
        ];
    }

    /**
     * Convert lead to customer
     *
     * @param int $leadId
     * @param array $customerData
     * @param string|null $notes
     * @param int $convertedBy
     * @param string $ip
     * @return array
     */
    public function convertToCustomer($leadId, array $customerData, $notes, $convertedBy, $ip)
    {
        DB::beginTransaction();
        
        try {
            // Get lead
            $lead = $this->leadRepository->find($leadId);
            
            if (!$lead) {
                return [
                    'success' => false,
                    'message' => 'Lead no encontrado.'
                ];
            }
            
            // Check if lead is already converted
            if ($lead->isConverted()) {
                return [
                    'success' => false,
                    'message' => 'Este lead ya ha sido convertido a cliente.'
                ];
            }
            
            // Convert lead to customer
            $customer = $this->leadRepository->convertToCustomer($leadId, $customerData, $notes);
            
            // Register audit
            AuditLog::register(
                $convertedBy,
                'lead_converted',
                'customer_lead',
                "Lead convertido a cliente: {$lead->name} → {$customer->full_name}",
                $ip,
                $lead->toArray(),
                [
                    'lead_id' => $lead->id,
                    'customer_id' => $customer->id,
                    'customer_data' => $customer->toArray()
                ]
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'customer' => $customer,
                'message' => 'Lead convertido a cliente correctamente.'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al convertir el lead: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Change lead status
     *
     * @param int $leadId
     * @param string $status
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function changeStatus($leadId, $status, $updatedBy, $ip)
    {
        // Get lead
        $lead = $this->leadRepository->find($leadId);
        
        if (!$lead) {
            return [
                'success' => false,
                'message' => 'Lead no encontrado.'
            ];
        }
        
        $oldStatus = $lead->status;
        
        // Update status
        $this->leadRepository->update($leadId, ['status' => $status]);
        
        // Register audit
        AuditLog::register(
            $updatedBy,
            'lead_status_changed',
            'customer_lead',
            "Estado de lead cambiado: {$lead->name} ($oldStatus → $status)",
            $ip,
            ['status' => $oldStatus],
            ['status' => $status]
        );
        
        return [
            'success' => true,
            'message' => 'Estado de lead actualizado correctamente.'
        ];
    }

    /**
     * Search leads with filters
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function searchLeads(array $filters, $perPage = 15)
    {
        if (isset($filters['status'])) {
            return $this->leadRepository->getByStatus($filters['status'], $perPage);
        }
        
        if (isset($filters['source'])) {
            return $this->leadRepository->getBySource($filters['source'], $perPage);
        }
        
        if (isset($filters['unconverted']) && $filters['unconverted']) {
            return $this->leadRepository->getUnconverted($perPage);
        }
        
        if (isset($filters['search'])) {
            return $this->leadRepository->search($filters['search'], $perPage);
        }
        
        // Default: get all leads
        return $this->leadRepository->paginate($perPage);
    }
}