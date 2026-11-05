<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\InstalledEquipment;
use Modules\Contract\Entities\UsedMaterial;
use Modules\Contract\Entities\InstallationPhoto;
use Modules\Contract\Entities\Route;
use Modules\Core\Entities\Employee;
use Modules\Operations\Entities\OnboardingOrder;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Installation extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'onboarding_order_id',
        'contract_id',
        'technician_id',
        'route_id',
        'scheduled_date',
        'completed_date',
        'status',
        'notes',
        'customer_signature',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_date' => 'datetime',
        'completed_date' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['status_color', 'is_late'];

    /**
     * Get the contract that owns the installation.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function onboardingOrder()
    {
        return $this->belongsTo(OnboardingOrder::class);
    }

    /**
     * Get the technician (employee) assigned to the installation.
     */
    public function technician()
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }

    /**
     * Get the route associated with the installation.
     */
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Get the equipment installed in this installation.
     */
    public function installedEquipment()
    {
        return $this->hasMany(InstalledEquipment::class);
    }

    /**
     * Get the materials used in this installation.
     */
    public function usedMaterials()
    {
        return $this->hasMany(UsedMaterial::class);
    }

    /**
     * Get the photos associated with this installation.
     */
    public function photos()
    {
        return $this->hasMany(InstallationPhoto::class);
    }

    /**
     * Get color for the status.
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
            case 'postponed':
                return 'info';
            default:
                return 'secondary';
        }
    }

    /**
     * Check if the installation is late.
     */
    public function getIsLateAttribute()
    {
        if (in_array($this->status, ['completed', 'cancelled'])) {
            return false;
        }
        
        return $this->scheduled_date && $this->scheduled_date->isPast();
    }

    /**
     * Get the elapsed time since scheduled date.
     */
    public function getElapsedTimeAttribute()
    {
        if (!$this->scheduled_date) {
            return null;
        }
        
        if ($this->completed_date) {
            return $this->scheduled_date->diffInHours($this->completed_date);
        }
        
        return $this->scheduled_date->diffInHours(now());
    }

    /**
     * Get the total cost of materials used in this installation.
     */
    public function getTotalMaterialCostAttribute()
    {
        return $this->usedMaterials->sum(function ($material) {
            return $material->quantity * ($material->material->cost ?? 0);
        });
    }

    /**
     * Check if all required photos have been uploaded.
     */
    public function hasAllRequiredPhotos()
    {
        $requiredTypes = ['exterior', 'interior', 'equipment'];
        $uploadedTypes = $this->photos->pluck('description')->toArray();
        
        foreach ($requiredTypes as $type) {
            if (!in_array($type, $uploadedTypes)) {
                return false;
            }
        }
        
        return true;
    }
}
