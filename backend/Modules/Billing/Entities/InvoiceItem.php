<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'tax_amount',
        'discount',
        'amount',
        'order',
        'item_type',
        'service_id',
        'period_start',
        'period_end'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'amount' => 'decimal:2',
        'order' => 'integer'
    ];

    /**
     * Relación con factura
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Calcular el subtotal (sin impuestos ni descuentos)
     */
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->unit_price;
    }
}
