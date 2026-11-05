<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Entities\User;

class RouterLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'action',
        'status',
        'description',
        'request_data',
        'response_data',
        'error_message',
        'metrics_before',
        'metrics_after',
        'user_id',
        'service_request_id',
        'automation_rule_id',
        'is_automated',
        'execution_time',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'metrics_before' => 'array',
        'metrics_after' => 'array',
        'is_automated' => 'boolean',
    ];

    protected $appends = ['action_label', 'status_label'];

    /**
     * Relación con router
     */
    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * Relación con usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con solicitud de servicio
     */
    public function serviceRequest()
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * Relación con regla de automatización
     */
    public function automationRule()
    {
        return $this->belongsTo(AutomationRule::class);
    }

    /**
     * Obtener label de la acción
     */
    public function getActionLabelAttribute()
    {
        return match($this->action) {
            'reboot' => 'Reinicio',
            'configuration_change' => 'Cambio de Configuración',
            'status_check' => 'Verificación de Estado',
            'bandwidth_adjustment' => 'Ajuste de Ancho de Banda',
            'firmware_update' => 'Actualización de Firmware',
            'client_connected' => 'Cliente Conectado',
            'client_disconnected' => 'Cliente Desconectado',
            'error' => 'Error',
            'health_check' => 'Chequeo de Salud',
            'manual_command' => 'Comando Manual',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener label del estado
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'initiated' => 'Iniciado',
            'success' => 'Exitoso',
            'failed' => 'Fallido',
            'timeout' => 'Timeout',
            default => 'Desconocido'
        };
    }

    /**
     * Scopes
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'timeout']);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeAutomated($query)
    {
        return $query->where('is_automated', true);
    }

    public function scopeManual($query)
    {
        return $query->where('is_automated', false);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
