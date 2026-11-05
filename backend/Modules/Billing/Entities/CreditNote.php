<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Entities\User;

class CreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'credit_note_number',
        'amount',
        'issue_date',
        'status',
        'reason',
        'notes',
        'user_id'
    ];

    protected $casts = [
        'issue_date' => 'date',
        'amount' => 'decimal:2'
    ];

    /**
     * Relación con factura
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Relación con usuario que generó la nota de crédito
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
