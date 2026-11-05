<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\Entities\Installation;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Route extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date',
        'zone',
        'order',
        'start_coordinates',
        'end_coordinates',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'order' => 'integer',
    ];

    /**
     * Get the installations associated with this route.
     */
    public function installations()
    {
        return $this->hasMany(Installation::class);
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case 'scheduled':
                return 'primary';
            case 'in_progress':
                return 'warning';
            case 'completed':
                return 'success';
            case 'cancelled':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    /**
     * Check if the route is for today.
     */
    public function getIsTodayAttribute()
    {
        return $this->date->isToday();
    }

    /**
     * Get the count of installations for this route.
     */
    public function getInstallationsCountAttribute()
    {
        return $this->installations()->count();
    }
    
    /**
     * Get the completed installations percentage for this route.
     */
    public function getCompletionPercentageAttribute()
    {
        $total = $this->installations()->count();
        
        if ($total === 0) {
            return 0;
        }
        
        $completed = $this->installations()->where('status', 'completed')->count();
        
        return round(($completed / $total) * 100);
    }
}