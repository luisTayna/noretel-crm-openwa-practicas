<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class WorkflowTransition extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workflow_id',
        'origin_state_id',
        'destination_state_id',
        'conditions',
        'validations',
        'actions'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'conditions' => 'array',
        'validations' => 'array',
        'actions' => 'array'
    ];

    /**
     * Get the workflow that owns the transition.
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the origin state of the transition.
     */
    public function originState()
    {
        return $this->belongsTo(WorkflowState::class, 'origin_state_id');
    }

    /**
     * Get the destination state of the transition.
     */
    public function destinationState()
    {
        return $this->belongsTo(WorkflowState::class, 'destination_state_id');
    }

    /**
     * Check if the transition can be executed based on the provided context.
     *
     * @param array $context
     * @return bool
     */
    public function canExecute($context = [])
    {
        // Si no hay condiciones, la transición es válida
        if (empty($this->conditions)) {
            return true;
        }
        
        // Verificar cada condición contra el contexto
        foreach ($this->conditions as $key => $value) {
            if (!isset($context[$key]) || $context[$key] != $value) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Validate the transition based on the provided data.
     *
     * @param array $data
     * @return array|true Array of errors or true if valid
     */
    public function validate($data = [])
    {
        if (empty($this->validations)) {
            return true;
        }
        
        $errors = [];
        
        // Implementar lógica para ejecutar validaciones
        // Esto podría involucrar validadores específicos según el tipo de workflow
        
        return empty($errors) ? true : $errors;
    }

    /**
     * Get the actions that should be executed with this transition.
     *
     * @return array
     */
    public function getActions()
    {
        return $this->actions ?? [];
    }
}