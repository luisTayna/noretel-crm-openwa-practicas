<?php

namespace Modules\Core\Services;

use Modules\Core\Repositories\WorkflowRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * @var WorkflowRepository
     */
    protected $workflowRepository;

    /**
     * WorkflowService constructor.
     *
     * @param WorkflowRepository $workflowRepository
     */
    public function __construct(WorkflowRepository $workflowRepository)
    {
        $this->workflowRepository = $workflowRepository;
    }

    /**
     * Create a new workflow
     *
     * @param array $workflowData
     * @param array $states
     * @param array $transitions
     * @param int $createdBy
     * @param string $ip
     * @return array
     */
    public function createWorkflow(array $workflowData, array $states, array $transitions, $createdBy, $ip)
    {
        try {
            $workflow = $this->workflowRepository->createWorkflow($workflowData, $states, $transitions);
            
            // Registrar acción
            AuditLog::register(
                $createdBy,
                'workflow_created',
                'workflows',
                "Workflow creado: {$workflow->name}",
                $ip,
                null,
                $workflow->toArray()
            );
            
            return [
                'success' => true,
                'workflow' => $workflow
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear workflow: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update a workflow
     *
     * @param int $workflowId
     * @param array $workflowData
     * @param array $states
     * @param array $transitions
     * @param int $updatedBy
     * @param string $ip
     * @return array
     */
    public function updateWorkflow($workflowId, array $workflowData, array $states, array $transitions, $updatedBy, $ip)
    {
        try {
            $oldWorkflow = $this->workflowRepository->getCompleteWorkflow($workflowId);
            
            $workflow = $this->workflowRepository->updateWorkflow($workflowId, $workflowData, $states, $transitions);
            
            // Registrar acción
            AuditLog::register(
                $updatedBy,
                'workflow_updated',
                'workflows',
                "Workflow actualizado: {$workflow->name}",
                $ip,
                $oldWorkflow->toArray(),
                $workflow->toArray()
            );
            
            return [
                'success' => true,
                'workflow' => $workflow
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar workflow: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get workflow by type
     *
     * @param string $type
     * @return array
     */
    public function getWorkflowByType($type)
    {
        $workflow = $this->workflowRepository->findByType($type);
        
        if (!$workflow) {
            return [
                'success' => false,
                'message' => "No se encontró un workflow activo para el tipo: {$type}"
            ];
        }
        
        return [
            'success' => true,
            'workflow' => $this->workflowRepository->getCompleteWorkflow($workflow->id)
        ];
    }

    /**
     * Get initial state for an entity
     *
     * @param string $workflowType
     * @return array
     */
    public function getInitialState($workflowType)
    {
        $workflow = $this->workflowRepository->findByType($workflowType);
        
        if (!$workflow) {
            return [
                'success' => false,
                'message' => "No se encontró un workflow activo para el tipo: {$workflowType}"
            ];
        }
        
        $initialState = $this->workflowRepository->getInitialState($workflow->id);
        
        if (!$initialState) {
            return [
                'success' => false,
                'message' => "No se encontró un estado inicial para el workflow: {$workflow->name}"
            ];
        }
        
        return [
            'success' => true,
            'state' => $initialState
        ];
    }

    /**
     * Get available transitions for an entity
     *
     * @param object $entity
     * @param string $workflowType
     * @return array
     */
    public function getAvailableTransitions($entity, $workflowType)
    {
        $workflow = $this->workflowRepository->findByType($workflowType);
        
        if (!$workflow) {
            return [
                'success' => false,
                'message' => "No se encontró un workflow activo para el tipo: {$workflowType}"
            ];
        }
        
        // Obtener el estado actual de la entidad
        $currentStateId = $entity->workflow_state_id;
        
        if (!$currentStateId) {
            $initialState = $this->workflowRepository->getInitialState($workflow->id);
            
            if (!$initialState) {
                return [
                    'success' => false,
                    'message' => "No se encontró un estado inicial para el workflow: {$workflow->name}"
                ];
            }
            
            $currentStateId = $initialState->id;
        }
        
        $transitions = $this->workflowRepository->getAvailableTransitions($currentStateId);
        
        return [
            'success' => true,
            'transitions' => $transitions
        ];
    }

    /**
     * Execute a transition for an entity
     *
     * @param object $entity
     * @param int $transitionId
     * @param array $context
     * @param int $executedBy
     * @param string $ip
     * @return array
     */
    public function executeTransition($entity, $transitionId, array $context, $executedBy, $ip)
    {
        try {
            DB::beginTransaction();
            
            // Obtener la transición
            $transition = $this->workflowRepository->getTransitionModel()->with(['originState', 'destinationState'])->findOrFail($transitionId);
            
            // Obtener el estado previo
            $previousState = $entity->workflow_state_id ? 
                $this->workflowRepository->getStateModel()->find($entity->workflow_state_id) : 
                null;
            
            // Ejecutar la transición
            $result = $this->workflowRepository->executeTransition($transitionId, $entity, $context);
            
            if (!$result) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'No se pudo ejecutar la transición.'
                ];
            }
            
            // Registrar acción
            AuditLog::register(
                $executedBy,
                'workflow_transition_executed',
                'workflows',
                "Transición ejecutada: {$transition->originState->name} -> {$transition->destinationState->name}",
                $ip,
                [
                    'entity_type' => get_class($entity),
                    'entity_id' => $entity->id,
                    'previous_state' => $previousState ? $previousState->name : 'Ninguno',
                    'context' => $context
                ],
                [
                    'entity_type' => get_class($entity),
                    'entity_id' => $entity->id,
                    'new_state' => $transition->destinationState->name,
                    'transition_id' => $transitionId
                ]
            );
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => "Transición ejecutada correctamente.",
                'new_state' => $transition->destinationState
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Error al ejecutar la transición: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get workflow state history for an entity
     *
     * @param object $entity
     * @return array
     */
    public function getStateHistory($entity)
    {
        $logs = AuditLog::where('action_type', 'workflow_transition_executed')
            ->where('new_data->entity_type', get_class($entity))
            ->where('new_data->entity_id', $entity->id)
            ->orderBy('action_date', 'desc')
            ->get();
            
        $history = [];
        
        foreach ($logs as $log) {
            $history[] = [
                'previous_state' => $log->previous_data['previous_state'] ?? 'Ninguno',
                'new_state' => $log->new_data['new_state'],
                'date' => $log->action_date,
                'user' => $log->user ? $log->user->username : 'Sistema',
                'context' => $log->previous_data['context'] ?? []
            ];
        }
        
        return [
            'success' => true,
            'history' => $history
        ];
    }
}