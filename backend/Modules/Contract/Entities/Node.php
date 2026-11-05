<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Node extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'location',
        'coordinates',
        'total_capacity',
        'used_capacity',
        'status',
        'connection_type',
        'node_type',
        'technical_configuration',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_capacity' => 'integer',
        'used_capacity' => 'integer',
        'technical_configuration' => 'json',
    ];

    /**
     * Get the contracts associated with this node.
     */
    public function contracts()
    {
        return $this->hasMany('Modules\Contract\Entities\Contract');
    }

    /**
     * Get the network equipment associated with this node.
     */
    public function networkEquipment()
    {
        return $this->hasMany('Modules\Network\Entities\NetworkEquipment');
    }

    /**
     * Get the monitoring data for this node.
     */
    public function monitoringData()
    {
        return $this->hasMany('Modules\Network\Entities\NetworkMonitoring');
    }

    /**
     * Get the available capacity.
     *
     * @return int
     */
    public function getAvailableCapacityAttribute()
    {
        return $this->total_capacity - $this->used_capacity;
    }

    /**
     * Get the usage percentage.
     *
     * @return float
     */
    public function getUsagePercentageAttribute()
    {
        if ($this->total_capacity == 0) {
            return 0;
        }
        
        return round(($this->used_capacity / $this->total_capacity) * 100, 2);
    }

    /**
     * Check if the node is at capacity.
     *
     * @return bool
     */
    public function getIsAtCapacityAttribute()
    {
        return $this->used_capacity >= $this->total_capacity;
    }

    /**
     * Check if the node is active.
     *
     * @return bool
     */
    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    /**
     * Get a formatted representation of the node capacity.
     *
     * @return string
     */
    public function getCapacityInfoAttribute()
    {
        return "{$this->used_capacity}/{$this->total_capacity} ({$this->usage_percentage}%)";
    }

    /**
     * Get a color class based on the node's capacity status.
     *
     * @return string
     */
    public function getCapacityColorAttribute()
    {
        $percentage = $this->usage_percentage;
        
        if ($percentage >= 90) {
            return 'danger';
        } elseif ($percentage >= 75) {
            return 'warning';
        } elseif ($percentage >= 50) {
            return 'info';
        } else {
            return 'success';
        }
    }

    /**
     * Check if the node has free capacity.
     *
     * @return bool
     */
    public function hasFreeCapacity()
    {
        return $this->used_capacity < $this->total_capacity;
    }

    /**
     * Check if the node can add more connections.
     *
     * @param int $count
     * @return bool
     */
    public function canAddConnections($count = 1)
    {
        return ($this->used_capacity + $count) <= $this->total_capacity;
    }

    /**
     * Add connections to the node.
     *
     * @param int $count
     * @return bool
     */
    public function addConnections($count = 1)
    {
        if (!$this->canAddConnections($count)) {
            return false;
        }
        
        $this->used_capacity += $count;
        return $this->save();
    }

    /**
     * Remove connections from the node.
     *
     * @param int $count
     * @return bool
     */
    public function removeConnections($count = 1)
    {
        $this->used_capacity = max(0, $this->used_capacity - $count);
        return $this->save();
    }
}