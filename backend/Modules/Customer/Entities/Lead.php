<?php

namespace Modules\Customer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Lead extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'contact',
        'phone',
        'email',
        'source',
        'capture_date',
        'status',
        'potential_value',
        'message',
        'interest',
        'metadata',
        'last_contacted_at',
        'assigned_to_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'capture_date' => 'datetime',
        'potential_value' => 'decimal:2',
        'metadata' => 'array',
        'last_contacted_at' => 'datetime',
    ];

    /**
     * Get the customers generated from this lead.
     */
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'customer_lead')
                    ->withPivot('conversion_date', 'notes')
                    ->withTimestamps();
    }

    /**
     * Check if the lead has been converted to a customer.
     *
     * @return bool
     */
    public function isConverted()
    {
        return $this->customers()->exists();
    }

    /**
     * Scope a query to only include leads of a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include leads from a specific source.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $source
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromSource($query, $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope a query to only include unconverted leads.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnconverted($query)
    {
        return $query->whereDoesntHave('customers');
    }

    /**
     * Convert this lead to a customer.
     *
     * @param  array  $customerData
     * @param  string|null  $notes
     * @return Customer
     */
    public function convertToCustomer(array $customerData, $notes = null)
    {
        // Create the customer
        $customer = Customer::create($customerData);
        
        // Associate lead with customer
        $this->customers()->attach($customer->id, [
            'conversion_date' => now(),
            'notes' => $notes
        ]);
        
        // Update lead status
        $this->update(['status' => 'converted']);
        
        return $customer;
    }
}
