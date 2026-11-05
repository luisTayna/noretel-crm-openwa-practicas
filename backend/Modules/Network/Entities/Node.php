<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Node extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'location',
        'zone',
        'district',
        'province',
        'department',
        'latitude',
        'longitude',
        'capacity',
        'current_load',
        'coverage_radius',
        'status',
        'is_operational',
        'parent_node_id',
        'description',
        'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'is_operational' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'coverage_radius' => 'decimal:2',
    ];

    protected $appends = ['load_percentage', 'type_label'];

    /**
     * Relación con nodo padre
     */
    public function parentNode()
    {
        return $this->belongsTo(Node::class, 'parent_node_id');
    }

    /**
     * Relación con nodos hijos
     */
    public function childNodes()
    {
        return $this->hasMany(Node::class, 'parent_node_id');
    }

    /**
     * Relación con routers
     */
    public function routers()
    {
        return $this->hasMany(Router::class);
    }

    /**
     * Calcular porcentaje de carga
     */
    public function getLoadPercentageAttribute()
    {
        if ($this->capacity == 0) {
            return 0;
        }
        return round(($this->current_load / $this->capacity) * 100, 2);
    }

    /**
     * Obtener label del tipo
     */
    public function getTypeLabelAttribute()
    {
        return match($this->type) {
            'core' => 'Núcleo',
            'distribution' => 'Distribución',
            'access' => 'Acceso',
            'backbone' => 'Troncal',
            default => 'Otro'
        };
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOperational($query)
    {
        return $query->where('is_operational', true);
    }

    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Verificar si el nodo tiene capacidad disponible
     */
    public function hasAvailableCapacity()
    {
        return $this->current_load < $this->capacity;
    }

    /**
     * Verificar si está cerca de la capacidad máxima
     */
    public function isNearCapacity($threshold = 85)
    {
        return $this->load_percentage >= $threshold;
    }
}
