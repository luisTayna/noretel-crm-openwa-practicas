<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class WorkflowState extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workflow_id',
        'name',
        'description',
        'is_initial',
        'is_final',
        'required_actions'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_initial' => 'boolean',
        'is_final' => 'boolean',
        'required_actions' => 'array'
    ];

    /**
     * Get the workflow that owns the state.
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the transitions from this state.
     */
    public function outgoingTransitions()
    {
        return $this->hasMany(WorkflowTransition::class, 'origin_state_id');
    }

    /**
     * Get the transitions to this state.
     */
    public function incomingTransitions()
    {
        return $this->hasMany(WorkflowTransition::class, 'destination_state_id');
    }

    /**
     * Get the next possible states from this state.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function nextStates()
    {
        return WorkflowState::whereIn('id', function ($query) {
            $query->select('destination_state_id')
                ->from('workflow_transitions')
                ->where('origin_state_id', $this->id);
        })->get();
    }

    /**
     * Check if this state is a final state.
     *
     * @return bool
     */
    public function isFinal()
    {
        return $this->is_final;
    }

    /**
     * Check if this state is an initial state.
     *
     * @return bool
     */
    public function isInitial()
    {
        return $this->is_initial;
    }

    /**
     * Check if any actions are required for this state.
     *
     * @return bool
     */
    public function requiresActions()
    {
        return !empty($this->required_actions);
    }

    /**
     * Check if all required actions have been completed.
     *
     * @param array $completedActions
     * @return bool
     */
    public function actionsCompleted($completedActions = [])
    {
        if (empty($this->required_actions)) {
            return true;
        }
        
        foreach ($this->required_actions as $action) {
            if (!in_array($action, $completedActions)) {
                return false;
            }
        }
        
        return true;
    }
}