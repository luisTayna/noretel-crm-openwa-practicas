<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;
use Illuminate\Support\Facades\DB;

class Workflow extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'workflow_type',
        'active',
        'configuration'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
        'configuration' => 'array'
    ];

    /**
     * Get the states for the workflow.
     */
    public function states()
    {
        return $this->hasMany(WorkflowState::class);
    }

    /**
     * Get the transitions for the workflow.
     */
    public function transitions()
    {
        return $this->hasMany(WorkflowTransition::class);
    }

    /**
     * Get the initial state for the workflow.
     */
    public function initialState()
    {
        return $this->states()->where('is_initial', true)->first();
    }

    /**
     * Get all final states for the workflow.
     */
    public function finalStates()
    {
        return $this->states()->where('is_final', true)->get();
    }

    /**
     * Get available transitions from a current state.
     *
     * @param WorkflowState $currentState
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableTransitions($currentState)
    {
        return $this->transitions()->where('origin_state_id', $currentState->id)->get();
    }

    /**
     * Check if a transition is valid.
     *
     * @param WorkflowState $currentState
     * @param WorkflowState $targetState
     * @param array $context
     * @return bool|WorkflowTransition
     */
    public function canTransition($currentState, $targetState, $context = [])
    {
        $transition = $this->transitions()
            ->where('origin_state_id', $currentState->id)
            ->where('destination_state_id', $targetState->id)
            ->first();
            
        if (!$transition) {
            return false;
        }
        
        // Verificar condiciones de la transición
        if (!empty($transition->conditions)) {
            foreach ($transition->conditions as $key => $value) {
                if (!isset($context[$key]) || $context[$key] != $value) {
                    return false;
                }
            }
        }
        
        // Verificar validaciones
        if (!empty($transition->validations)) {
            // Implementar lógica para ejecutar validaciones
            // Esto podría involucrar llamar a clases de validación específicas
        }
        
        return $transition;
    }

    /**
     * Execute a transition.
     *
     * @param WorkflowState $currentState
     * @param WorkflowState $targetState
     * @param object $entity
     * @param array $context
     * @return bool
     */
    public function executeTransition($currentState, $targetState, $entity, $context = [])
    {
        $transition = $this->canTransition($currentState, $targetState, $context);
        
        if (!$transition) {
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            // Ejecutar acciones asociadas a la transición
            if (!empty($transition->actions)) {
                foreach ($transition->actions as $action) {
                    // Implementar lógica para ejecutar acciones
                    // Esto podría involucrar llamar a clases de acción específicas
                }
            }
            
            // Actualizar el estado de la entidad
            if (method_exists($entity, 'setWorkflowState')) {
                $entity->setWorkflowState($targetState->id);
            } else {
                $entity->workflow_state_id = $targetState->id;
                $entity->save();
            }
            
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}