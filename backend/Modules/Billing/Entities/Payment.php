<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Entities\User;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'status',
        'reference',
        'transaction_id',
        'payment_gateway',
        'payment_details',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'payment_details' => 'json'
    ];

    /**
     * Relación con factura
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Relación con usuario que registró el pago
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validación automática Yape asociada, si el pago nació de WhatsApp + notificación Android.
     */
    public function yapePaymentIntent()
    {
        return $this->hasOne(YapePaymentIntent::class);
    }

    /**
     * Obtener nombre del método de pago desde la configuración
     */
    public function getPaymentMethodNameAttribute()
    {
        $methods = config('billing.payment.methods');
        return $methods[$this->payment_method] ?? $this->payment_method;
    }
}
