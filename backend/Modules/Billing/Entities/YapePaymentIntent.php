<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;
use Modules\Customer\Entities\Customer;

class YapePaymentIntent extends Model
{
    use HasFactory;

    public const STATUS_WAITING_CLIENT = 'waiting_client_evidence';
    public const STATUS_WAITING_RECEIVER = 'waiting_receiver_event';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_OBSERVED = 'observed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'yape_payment_intents';

    protected $fillable = [
        'intent_number',
        'invoice_id',
        'contract_id',
        'customer_id',
        'payment_id',
        'client_evidence_id',
        'receiver_event_id',
        'expected_amount',
        'payment_mode',
        'months_to_pay',
        'whatsapp_phone',
        'customer_name',
        'status',
        'operation_code',
        'security_code',
        'receiver_account_name',
        'receiver_account_phone',
        'match_score',
        'validation_result',
        'source_payload',
        'observed_reason',
        'expires_at',
        'validated_at',
        'created_by',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'match_score' => 'decimal:2',
        'validation_result' => 'array',
        'source_payload' => 'array',
        'expires_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $intent) {
            if (empty($intent->intent_number)) {
                $intent->intent_number = self::generateIntentNumber();
            }
        });
    }

    public static function generateIntentNumber(): string
    {
        $prefix = 'YPI-' . now()->format('Ymd');
        $lastIntentNumber = self::query()
            ->where('intent_number', 'like', $prefix . '-%')
            ->orderByDesc('intent_number')
            ->value('intent_number');

        $lastSequence = 0;

        if (is_string($lastIntentNumber) && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastIntentNumber, $matches)) {
            $lastSequence = (int) $matches[1];
        }

        return $prefix . '-' . str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function clientEvidence()
    {
        return $this->belongsTo(YapeClientEvidence::class, 'client_evidence_id');
    }

    public function receiverEvent()
    {
        return $this->belongsTo(YapeReceiverEvent::class, 'receiver_event_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            self::STATUS_WAITING_CLIENT => 'Esperando WhatsApp',
            self::STATUS_WAITING_RECEIVER => 'Esperando receptor',
            self::STATUS_VALIDATED => 'Validado',
            self::STATUS_OBSERVED => 'Observado',
            self::STATUS_CANCELLED => 'Cancelado',
        ][$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
