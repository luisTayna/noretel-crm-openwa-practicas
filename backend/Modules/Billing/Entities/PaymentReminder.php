<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Customer\Entities\Customer;

class PaymentReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'reminder_date',
        'scheduled_for',
        'status',
        'type',
        'reminder_key',
        'message',
        'channel',
        'target_phone',
        'batch_id',
        'attempts',
        'openwa_response',
        'error_message',
        'metadata',
        'sent_at'
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
        'openwa_response' => 'array',
        'metadata' => 'array',
        'attempts' => 'integer',
    ];

    /**
     * Relación con factura
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
