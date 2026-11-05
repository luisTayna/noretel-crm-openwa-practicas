<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Customer\Entities\Customer;
use Modules\Contract\Entities\Contract;

class Router extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'brand',
        'model',
        'serial_number',
        'ip_address',
        'mac_address',
        'api_endpoint',
        'api_key',
        'credentials',
        'location',
        'zone',
        'latitude',
        'longitude',
        'status',
        'firmware_version',
        'max_clients',
        'connected_clients',
        'signal_quality',
        'bandwidth_usage',
        'cpu_usage',
        'memory_usage',
        'uptime',
        'node_id',
        'parent_router_id',
        'last_reboot',
        'last_health_check',
        'installed_at',
        'notes',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'api_key' => 'encrypted',
        'last_reboot' => 'datetime',
        'last_health_check' => 'datetime',
        'installed_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'signal_quality' => 'decimal:2',
        'cpu_usage' => 'decimal:2',
        'memory_usage' => 'decimal:2',
    ];

    protected $appends = ['load_percentage', 'status_label', 'health_status'];

    /**
     * Relación con Node
     */
    public function node()
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Relación con router padre
     */
    public function parentRouter()
    {
        return $this->belongsTo(Router::class, 'parent_router_id');
    }

    /**
     * Relación con routers hijos
     */
    public function childRouters()
    {
        return $this->hasMany(Router::class, 'parent_router_id');
    }

    /**
     * Relación con clientes (muchos a muchos)
     */
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'router_customer')
                    ->withPivot([
                        'contract_id',
                        'port',
                        'vlan',
                        'assigned_ip',
                        'pppoe_username',
                        'bandwidth_limit_down',
                        'bandwidth_limit_up',
                        'connection_status',
                        'assigned_at',
                        'disconnected_at',
                        'last_connection',
                        'notes'
                    ])
                    ->withTimestamps();
    }

    /**
     * Relación con contratos activos
     */
    public function activeContracts()
    {
        return $this->belongsToMany(Contract::class, 'router_customer')
                    ->wherePivot('connection_status', 'active');
    }

    /**
     * Relación con solicitudes de servicio
     */
    public function serviceRequests()
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * Relación con logs
     */
    public function logs()
    {
        return $this->hasMany(RouterLog::class);
    }

    /**
     * Relación con histórico de métricas
     */
    public function metricsHistory()
    {
        return $this->hasMany(RouterMetricsHistory::class);
    }

    /**
     * Calcular porcentaje de carga
     */
    public function getLoadPercentageAttribute()
    {
        if ($this->max_clients == 0) {
            return 0;
        }
        return round(($this->connected_clients / $this->max_clients) * 100, 2);
    }

    /**
     * Obtener label del estado
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'maintenance' => 'Mantenimiento',
            'error' => 'Error',
            'offline' => 'Fuera de línea',
            default => 'Desconocido'
        };
    }

    /**
     * Determinar estado de salud
     */
    public function getHealthStatusAttribute()
    {
        if ($this->status === 'offline' || $this->status === 'error') {
            return 'critical';
        }

        if ($this->cpu_usage > 80 || $this->memory_usage > 85 || $this->load_percentage > 90) {
            return 'warning';
        }

        if ($this->signal_quality < 50) {
            return 'warning';
        }

        return 'good';
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', $brand);
    }

    public function scopeOverloaded($query)
    {
        return $query->whereRaw('(connected_clients / max_clients) > 0.85');
    }

    public function scopeWithProblems($query)
    {
        return $query->whereIn('status', ['error', 'offline'])
                     ->orWhere('cpu_usage', '>', 80)
                     ->orWhere('memory_usage', '>', 85);
    }

    /**
     * Verificar si el router está disponible para nuevos clientes
     */
    public function hasAvailableCapacity()
    {
        return $this->connected_clients < $this->max_clients;
    }

    /**
     * Verificar si el router necesita mantenimiento
     */
    public function needsMaintenance()
    {
        return $this->health_status === 'critical' || $this->health_status === 'warning';
    }

    /**
     * Obtener tiempo desde último reinicio
     */
    public function getTimeSinceLastReboot()
    {
        if (!$this->last_reboot) {
            return 'Nunca';
        }
        return $this->last_reboot->diffForHumans();
    }

    /**
     * Generar código único para el router
     */
    public static function generateCode($brand, $zone)
    {
        $prefix = strtoupper(substr($brand, 0, 2));
        $zoneCode = strtoupper(substr($zone, 0, 3));
        $codePrefix = "{$prefix}-{$zoneCode}-";
        $lastCode = self::query()
            ->where('code', 'like', $codePrefix . '%')
            ->orderByDesc('code')
            ->value('code');

        $lastSequence = 0;

        if (is_string($lastCode) && preg_match('/^' . preg_quote($codePrefix, '/') . '(\d+)$/', $lastCode, $matches)) {
            $lastSequence = (int) $matches[1];
        }

        $number = str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$zoneCode}-{$number}";
    }
}
