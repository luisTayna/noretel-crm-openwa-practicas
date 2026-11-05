<?php

namespace Modules\Customer\Services;

use Modules\Customer\Interfaces\InteractionRepositoryInterface;
use Modules\Customer\Interfaces\CustomerRepositoryInterface;
use Modules\Core\Entities\AuditLog;

class InteractionService
{
    /**
     * @var InteractionRepositoryInterface
     */
    protected $interactionRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * InteractionService constructor.
     *
     * @param InteractionRepositoryInterface $interactionRepository
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        InteractionRepositoryInterface $interactionRepository,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->interactionRepository = $interactionRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Register a new interaction
     *
     * @param array $data
     * @param int $createdBy
     * @param string $ip
     * @return array
     */
    public function registerInteraction(array $data, $createdBy, $ip)
    {
        // Verify customer exists
        $customer = $this->customerRepository->find($data['customer_id']);
        
        if (!$customer) {
            return [
                'success' => false,
                'message' => 'Cliente no encontrado.'
            ];
        }
        
        // Set actor user and date if not provided
        $data['created_by_user_id'] = $data['created_by_user_id'] ?? $createdBy;
        unset($data['employee_id']);
        $data['date'] = $data['date'] ?? now();
        
        // Create interaction
        $interaction = $this->interactionRepository->create($data);
        
        // Register audit
        AuditLog::register(
            $createdBy,
            'interaction_registered',
            'customer_interaction',
            "Interacción registrada con cliente: {$customer->full_name} ({$interaction->interaction_type})",
            $ip,
            null,
            $interaction->toArray()
        );
        
        return [
            'success' => true,
            'interaction' => $interaction
        ];
    }

    /**
     * Update an interaction
     *
     * @param int $interactionId
     * @param array $data
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function updateInteraction($interactionId, array $data, $updatedBy, $ip)
    {
        // Get interaction
        $interaction = $this->interactionRepository->find($interactionId);
        
        if (!$interaction) {
            return [
                'success' => false,
                'message' => 'Interacción no encontrada.'
            ];
        }
        
        $oldData = $interaction->toArray();
        
        // Update interaction
        $this->interactionRepository->update($interactionId, $data);
        $interaction = $this->interactionRepository->find($interactionId);
        
        // Register audit
        AuditLog::register(
            $updatedBy,
            'interaction_updated',
            'customer_interaction',
            "Interacción actualizada con cliente ID {$interaction->customer_id}",
            $ip,
            $oldData,
            $interaction->toArray()
        );
        
        return [
            'success' => true,
            'interaction' => $interaction
        ];
    }

    /**
     * Delete an interaction
     *
     * @param int $interactionId
     * @param int $deletedBy
     * @param string $ip
     * @return array
     */
    public function deleteInteraction($interactionId, $deletedBy, $ip)
    {
        // Get interaction
        $interaction = $this->interactionRepository->find($interactionId);
        
        if (!$interaction) {
            return [
                'success' => false,
                'message' => 'Interacción no encontrada.'
            ];
        }
        
        $interactionData = $interaction->toArray();
        
        // Delete interaction
        $this->interactionRepository->delete($interactionId);
        
        // Register audit
        AuditLog::register(
            $deletedBy,
            'interaction_deleted',
            'customer_interaction',
            "Interacción eliminada con cliente ID {$interaction->customer_id}",
            $ip,
            $interactionData,
            null
        );
        
        return [
            'success' => true,
            'message' => 'Interacción eliminada correctamente.'
        ];
    }

    /**
     * Get customer interactions
     *
     * @param int $customerId
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getCustomerInteractions($customerId, array $filters = [], $perPage = 15)
    {
        // Verify customer exists
        $customer = $this->customerRepository->find($customerId);
        
        if (!$customer) {
            return collect();
        }
        
        // Apply filters
        if (isset($filters['type'])) {
            return $this->interactionRepository->getByType($customerId, $filters['type'], $perPage);
        }
        
        if (isset($filters['follow_up']) && $filters['follow_up']) {
            return $this->interactionRepository->getRequiringFollowUp($customerId, $perPage);
        }
        
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            return $this->interactionRepository->getInDateRange(
                $customerId,
                $filters['date_from'],
                $filters['date_to'],
                $perPage
            );
        }
        
        // Default: get all interactions
        return $this->interactionRepository->getByCustomer($customerId, $perPage);
    }

    /**
     * Mark interaction as requiring follow-up
     *
     * @param int $interactionId
     * @param bool $requiresFollowUp
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function markForFollowUp($interactionId, $requiresFollowUp, $updatedBy, $ip)
    {
        // Get interaction
        $interaction = $this->interactionRepository->find($interactionId);
        
        if (!$interaction) {
            return [
                'success' => false,
                'message' => 'Interacción no encontrada.'
            ];
        }
        
        $oldValue = $interaction->follow_up_required;
        
        // Update follow-up flag
        $this->interactionRepository->update($interactionId, ['follow_up_required' => $requiresFollowUp]);
        
        // Register audit
        $action = $requiresFollowUp ? 'interaction_marked_for_followup' : 'interaction_unmarked_for_followup';
        $message = $requiresFollowUp 
            ? "Interacción marcada para seguimiento con cliente ID {$interaction->customer_id}"
            : "Interacción desmarcada de seguimiento con cliente ID {$interaction->customer_id}";
        
        AuditLog::register(
            $updatedBy,
            $action,
            'customer_interaction',
            $message,
            $ip,
            ['follow_up_required' => $oldValue],
            ['follow_up_required' => $requiresFollowUp]
        );
        
        return [
            'success' => true,
            'message' => $requiresFollowUp 
                ? 'Interacción marcada para seguimiento correctamente.'
                : 'Interacción desmarcada de seguimiento correctamente.'
        ];
    }
}
