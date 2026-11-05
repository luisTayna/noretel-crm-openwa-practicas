<?php

namespace Modules\Customer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Entities\User;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Interaction extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
        'created_by_user_id',
        'interaction_type',
        'date',
        'channel',
        'description',
        'result',
        'follow_up_required'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date' => 'datetime',
        'follow_up_required' => 'boolean'
    ];

    /**
     * Get the customer that owns the interaction.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Backward-compatible alias for old code that still calls employee.
     */
    public function employee()
    {
        return $this->createdBy();
    }

    /**
     * Scope a query to only include interactions that require follow-up.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRequiresFollowUp($query)
    {
        return $query->where('follow_up_required', true);
    }

    /**
     * Scope a query to only include interactions of a specific type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope a query to only include interactions from a specific channel.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $channel
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope a query to only include interactions within a date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \DateTime  $start
     * @param  \DateTime  $end
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInDateRange($query, $start, $end)
    {
        return $query->whereBetween('date', [$start, $end]);
    }
}
