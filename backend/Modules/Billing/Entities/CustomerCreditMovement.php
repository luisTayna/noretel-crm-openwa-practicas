<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;

class CustomerCreditMovement extends Model
{
    use HasFactory;

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'customer_id',
        'contract_id',
        'source_type',
        'source_id',
        'type',
        'amount',
        'balance_after',
        'status',
        'description',
        'metadata',
        'created_by',
        'voided_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'voided_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
