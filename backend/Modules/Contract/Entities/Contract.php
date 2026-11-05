<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Customer\Entities\Customer;
use Modules\Services\Entities\Plan;
use Modules\Contract\Entities\Node;
use Modules\Contract\Entities\ContractedService;
use Modules\Contract\Entities\SLA;
use Modules\Contract\Entities\Installation;
use Modules\Billing\Entities\Invoice;
use Modules\Operations\Entities\OnboardingOrder;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Contract extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $table = 'contracts';
    protected $fillable = [
        'customer_id',
        'plan_id',
        'node_id',
        'start_date',
        'end_date',
        'status',
        'suspension_reason',
        'final_price',
        'assigned_ip',
        'vlan',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'final_price' => 'decimal:2',
    ];

    /**
     * Get the customer that owns the contract.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the plan associated with the contract.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the node associated with the contract.
     */
    public function node()
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Get the contracted additional services for this contract.
     */
    public function contractedServices()
    {
        return $this->hasMany(ContractedService::class);
    }

    /**
     * Get the SLA associated with the contract.
     */
    // public function sla()
    // {
    //     return $this->hasOne(SLA::class);
    // }
public function slas()
{
    return $this->belongsToMany(SLA::class, 'contract_sla', 'contract_id', 'sla_id');
}

    /**
     * Get the installations associated with the contract.
     */
    public function installations()
    {
        return $this->hasMany(Installation::class);
    }

    /**
     * Get the invoices generated for this contract.
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function creditMovements()
    {
        return $this->hasMany(\Modules\Billing\Entities\CustomerCreditMovement::class);
    }

    public function onboardingOrder()
    {
        return $this->hasOne(OnboardingOrder::class);
    }

    /**
     * Get the latest installation for this contract.
     */
    public function latestInstallation()
    {
        return $this->installations()->latest()->first();
    }

    /**
     * Check if the contract has active installations.
     */
    public function hasActiveInstallation()
    {
        return $this->installations()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();
    }

    /**
     * Check if the contract is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if the contract is expired.
     */
    public function isExpired()
    {
        if (!$this->end_date) {
            return false;
        }
        
        return $this->end_date->isPast() && $this->status !== 'renewed';
    }

    /**
     * Check if the contract is near expiration (30 days before end date).
     */
    public function isNearExpiration()
    {
        if (!$this->end_date) {
            return false;
        }
        
        $nearExpirationDate = now()->addDays(30);
        return $this->end_date->lessThan($nearExpirationDate) && 
               $this->end_date->greaterThan(now()) && 
               $this->status === 'active';
    }

    /**
     * Get time remaining until contract expiration.
     */
    public function getRemainingTimeAttribute()
    {
        if (!$this->end_date) {
            return null;
        }
        
        return now()->diffInDays($this->end_date, false);
    }

    /**
     * Check if contract can be renewed.
     */
    public function canBeRenewed()
    {
        return in_array($this->status, ['active', 'expired']) && 
               (!$this->end_date || $this->end_date->diffInDays(now(), false) >= -30);
    }

    /**
     * Check if contract can be cancelled without deleting its history.
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['cancelled', 'expired', 'renewed'], true);
    }

    /**
     * Check if contract can be physically deleted.
     */
    public function canBeDeleted(): bool
    {
        return !$this->installations()->exists() && !$this->invoices()->exists();
    }
}
