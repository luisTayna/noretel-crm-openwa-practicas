<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\Entities\Customer;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\Installation;
use Modules\Core\Entities\User;
use Modules\Operations\Entities\OnboardingOrder;

class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'router_id',
        'contract_id',
        'onboarding_order_id',
        'installation_id',
        'type',
        'priority',
        'status',
        'description',
        'customer_notes',
        'assigned_to',
        'assigned_at',
        'resolution_notes',
        'technical_notes',
        'response_data',
        'activation_data',
        'started_at',
        'completed_at',
        'resolution_time',
        'is_automated',
        'requires_visit',
        'scheduled_visit',
    ];

    protected $casts = [
        'response_data' => 'array',
        'activation_data' => 'array',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_visit' => 'datetime',
        'is_automated' => 'boolean',
        'requires_visit' => 'boolean',
    ];

    protected $appends = ['type_label', 'status_label', 'priority_label'];

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ticket_number)) {
                $model->ticket_number = self::generateTicketNumber();
            }
        });

        static::updated(function ($model) {
            // Calcular tiempo de resolución cuando se completa
            if ($model->isDirty('status') && $model->status === 'completed' && $model->started_at) {
                $model->resolution_time = $model->started_at->diffInMinutes($model->completed_at ?? now());
                $model->saveQuietly();
            }
        });
    }

    /**
     * Relación con cliente
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relación con router
     */
    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * Relación con contrato
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function onboardingOrder()
    {
        return $this->belongsTo(OnboardingOrder::class);
    }

    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    /**
     * Relación con técnico asignado
     */
    public function assignedTechnician()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Relación con logs del router
     */
    public function routerLogs()
    {
        return $this->hasMany(RouterLog::class);
    }

    /**
     * Obtener label del tipo
     */
    public function getTypeLabelAttribute()
    {
        return match($this->type) {
            'router_reboot' => 'Reinicio de Router',
            'connection_issue' => 'Problema de Conexión',
            'slow_speed' => 'Velocidad Lenta',
            'no_internet' => 'Sin Internet',
            'configuration_change' => 'Cambio de Configuración',
            'technical_visit' => 'Visita Técnica',
            'equipment_replacement' => 'Reemplazo de Equipo',
            'equipment_activation' => 'Habilitación de equipo',
            'other' => 'Otro',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener label del estado
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'in_progress' => 'En Progreso',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'cancelled' => 'Cancelado',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener label de prioridad
     */
    public function getPriorityLabelAttribute()
    {
        return match($this->priority) {
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
            default => 'Media'
        };
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeAutomated($query)
    {
        return $query->where('is_automated', true);
    }

    public function scopeManual($query)
    {
        return $query->where('is_automated', false);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRequiresVisit($query)
    {
        return $query->where('requires_visit', true);
    }

    /**
     * Generar número de ticket único
     */
    public static function generateTicketNumber()
    {
        $prefix = 'SR-' . now()->format('Ymd');
        $lastTicketNumber = self::query()
            ->where('ticket_number', 'like', $prefix . '-%')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $lastSequence = 0;

        if (is_string($lastTicketNumber) && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastTicketNumber, $matches)) {
            $lastSequence = (int) $matches[1];
        }

        return $prefix . '-' . str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Verificar si puede ser automatizado
     */
    public function canBeAutomated()
    {
        return in_array($this->type, [
            'router_reboot',
            'configuration_change',
        ]);
    }

    /**
     * Asignar a técnico
     */
    public function assignTo(User $technician)
    {
        $this->update([
            'assigned_to' => $technician->id,
            'assigned_at' => now(),
            'status' => 'in_progress',
        ]);
    }

    /**
     * Marcar como iniciado
     */
    public function markAsStarted()
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    /**
     * Marcar como completado
     */
    public function markAsCompleted($resolutionNotes = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'resolution_notes' => $resolutionNotes,
        ]);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed($errorNotes = null)
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'technical_notes' => $errorNotes,
        ]);
    }
}
