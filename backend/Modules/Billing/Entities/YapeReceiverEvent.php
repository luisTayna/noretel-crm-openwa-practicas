<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YapeReceiverEvent extends Model
{
    use HasFactory;

    protected $table = 'yape_receiver_events';

    protected $fillable = [
        'yape_payment_intent_id',
        'device_serial',
        'screenshot_path',
        'raw_text',
        'parsed_amount',
        'operation_code',
        'security_code',
        'occurred_at',
        'sender_name',
        'sender_phone',
        'confidence',
        'status',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'parsed_amount' => 'decimal:2',
        'confidence' => 'decimal:2',
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function intent()
    {
        return $this->belongsTo(YapePaymentIntent::class, 'yape_payment_intent_id');
    }
}
