<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Entities\User;

class AutomationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'trigger_type',
        'trigger_conditions',
        'action_type',
        'action_config',
        'scope',
        'scope_config',
        'schedule_cron',
        'next_execution',
        'last_execution',
        'is_active',
        'execution_count',
        'success_count',
        'failure_count',
        'created_by',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'action_config' => 'array',
        'scope_config' => 'array',
        'next_execution' => 'datetime',
        'last_execution' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $appends = ['success_rate', 'trigger_type_label', 'action_type_label'];

    /**
     * Relación con el usuario que creó la regla
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relación con logs de routers que ejecutaron esta regla
     */
    public function routerLogs()
    {
        return $this->hasMany(RouterLog::class);
    }

    /**
     * Calcular tasa de éxito
     */
    public function getSuccessRateAttribute()
    {
        if ($this->execution_count == 0) {
            return 0;
        }
        return round(($this->success_count / $this->execution_count) * 100, 2);
    }

    /**
     * Obtener label del tipo de trigger
     */
    public function getTriggerTypeLabelAttribute()
    {
        return match($this->trigger_type) {
            'service_request' => 'Solicitud de Servicio',
            'schedule' => 'Programado',
            'threshold' => 'Umbral',
            'event' => 'Evento',
            'manual' => 'Manual',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener label del tipo de acción
     */
    public function getActionTypeLabelAttribute()
    {
        return match($this->action_type) {
            'router_reboot' => 'Reiniciar Router',
            'bandwidth_adjust' => 'Ajustar Ancho de Banda',
            'send_notification' => 'Enviar Notificación',
            'create_ticket' => 'Crear Ticket',
            'suspend_service' => 'Suspender Servicio',
            'activate_service' => 'Activar Servicio',
            'run_script' => 'Ejecutar Script',
            'multiple_actions' => 'Múltiples Acciones',
            default => 'Desconocido'
        };
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeScheduled($query)
    {
        return $query->where('trigger_type', 'schedule')
                     ->whereNotNull('schedule_cron');
    }

    public function scopeDueForExecution($query)
    {
        return $query->active()
                     ->scheduled()
                     ->where('next_execution', '<=', now());
    }

    /**
     * Incrementar contador de ejecuciones
     */
    public function incrementExecutionCount($success = true)
    {
        $this->increment('execution_count');
        
        if ($success) {
            $this->increment('success_count');
        } else {
            $this->increment('failure_count');
        }

        $this->update(['last_execution' => now()]);
    }

    /**
     * Activar regla
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
    }

    /**
     * Desactivar regla
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Obtener routers afectados por esta regla
     */
    public function getAffectedRouters()
    {
        return match($this->scope) {
            'all_routers' => Router::active()->get(),
            'specific_routers' => Router::whereIn('id', $this->scope_config['router_ids'] ?? [])->get(),
            'zone' => Router::byZone($this->scope_config['zone'] ?? '')->get(),
            'node' => Router::where('node_id', $this->scope_config['node_id'] ?? null)->get(),
            default => collect()
        };
    }
}
