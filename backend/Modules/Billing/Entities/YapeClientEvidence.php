<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Customer\Entities\Customer;

class YapeClientEvidence extends Model
{

    use HasFactory;

    protected $table = 'yape_client_evidences';

    protected $fillable = [
        'yape_payment_intent_id',
        'customer_id',
        'whatsapp_phone',
        'source_message_id',
        'source_chat_name',
        'image_path',
        'raw_text',
        'parsed_amount',
        'operation_code',
        'security_code',
        'occurred_at',
        'receiver_name',
        'receiver_phone',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
