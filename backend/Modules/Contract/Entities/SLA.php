<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class SLA extends Model implements Auditable
{
    use HasFactory, AuditableTrait;
    protected $table = 'slas';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'service_level',
        'response_time',
        'resolution_time',
        'penalties',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'response_time' => 'integer',
        'resolution_time' => 'integer',
        'penalties' => 'array',
    ];

    /**
     * Get the contracts using this SLA.
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get a color representation based on the service level.
     */
    public function getLevelColorAttribute()
    {
        switch ($this->service_level) {
            case 'premium':
                return 'success';
            case 'standard':
                return 'primary';
            case 'basic':
                return 'warning';
            default:
                return 'secondary';
        }
    }

    /**
     * Check if the SLA is suitable for a specific plan type.
     */
    public function isSuitableForPlan($planType)
    {
        if ($planType === 'business' && $this->service_level !== 'basic') {
            return true;
        }
        
        if ($planType === 'residential' && $this->service_level === 'basic') {
            return true;
        }
        
        return false;
    }
}