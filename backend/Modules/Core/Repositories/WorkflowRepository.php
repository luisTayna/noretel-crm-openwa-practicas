<?php

namespace Modules\Core\Repositories;

use Modules\Core\Entities\Workflow;
use Modules\Core\Entities\WorkflowState;
use Modules\Core\Entities\WorkflowTransition;
use Illuminate\Support\Facades\DB;

class WorkflowRepository extends BaseRepository
{
    /**
     * @var WorkflowState
     */
    protected $stateModel;

    /**
     * @var WorkflowTransition
     */
    protected $transitionModel;

    /**
     * WorkflowRepository constructor.
     *
     * @param Workflow $model
     * @param WorkflowState $stateModel
     * @param WorkflowTransition $transitionModel
     */
    public function __construct(
        Workflow $model,
        WorkflowState $stateModel,
        WorkflowTransition $transitionModel
    ) {
        parent::__construct($model);
        $this->stateModel = $stateModel;
        $this->transitionModel = $transitionModel;
    }

    /**
     * Get active workflows
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveWorkflows()
    {
        return $this->model->where('active', true)->get();
    }

    /**
     * Get workflow by type
     *
     * @param string $type
     * @return Workflow|null
     */
    public function findByType($type)
    {
        return $this->model->where('workflow_type', $type)
            ->where('active', true)
            ->first();
    }

    /**
     * Get complete workflow with states and transitions
     *
     * @param int $id
     * @return Workflow
     */
    public function getCompleteWorkflow($id)
    {
        return $this->model->with(['states', 'transitions'])->findOrFail($id);
    }

    /**
     * Create a new workflow with states and transitions
     *
     * @param array $workflowData
     * @param array $states
     * @param array $transitions
     * @return Workflow
     */
    public function createWorkflow(array $workflowData, array $states, array $transitions)
    {
        DB::beginTransaction();
        
        try {
            // Crear workflow
            $workflow = $this->create($workflowData);
            
            // Crear estados
            foreach ($states as $stateData) {
                $stateData['workflow_id'] = $workflow->id;
                $this->stateModel->create($stateData);
            }
            
            // Crear transiciones
            foreach ($transitions as $transitionData) {
                $transitionData['workflow_id'] = $workflow->id;
                $this->transitionModel->create($transitionData);
            }
            
            DB::commit();
            return $this->getCompleteWorkflow($workflow->id);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a workflow with its states and transitions
     *
     * @param int $id
     * @param array $workflowData
     * @param array $states
     * @param array $transitions
     * @return Workflow
     */
    public function updateWorkflow($id, array $workflowData, array $states, array $transitions)
    {
        DB::beginTransaction();
        
        try {
            // Actualizar workflow
            $workflow = $this->find($id);
            $workflow->update($workflowData);
            
            // Eliminar estados y transiciones existentes
            $workflow->states()->delete();
            $workflow->transitions()->delete();
            
            // Crear nuevos estados
            foreach ($states as $stateData) {
                $stateData['workflow_id'] = $workflow->id;
                $this->stateModel->create($stateData);
            }
            
            // Crear nuevas transiciones
            foreach ($transitions as $transitionData) {
                $transitionData['workflow_id'] = $workflow->id;
                $this->transitionModel->create($transitionData);
            }
            
            DB::commit();
            return $this->getCompleteWorkflow($workflow->id);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get initial state for a workflow
     *
     * @param int $workflowId
     * @return WorkflowState|null
     */
    public function getInitialState($workflowId)
    {
        return $this->stateModel
            ->where('workflow_id', $workflowId)
            ->where('is_initial', true)
            ->first();
    }

    /**
     * Get available transitions from a current state
     *
     * @param int $stateId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableTransitions($stateId)
    {
        return $this->transitionModel
            ->with('destinationState')
            ->where('origin_state_id', $stateId)
            ->get();
    }

    /**
     * Execute a transition for an entity
     *
     * @param int $transitionId
     * @param object $entity
     * @param array $context
     * @return bool
     */
    public function executeTransition($transitionId, $entity, array $context = [])
    {
        $transition = $this->transitionModel->findOrFail($transitionId);
        $workflow = $this->find($transition->workflow_id);
        
        return $workflow->executeTransition(
            $transition->originState, 
            $transition->destinationState, 
            $entity, 
            $context
        );
    }
}