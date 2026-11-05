<?php

namespace Modules\Billing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\User;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'invoice_number',
        'amount',
        'taxes',
        'total_amount',
        'issue_date',
        'due_date',
        'status',
        'document_type',
        'notes',
        'sent',
        'sent_at',
        'billing_name',
        'billing_address',
        'billing_document',
        'billing_email',
        'payment_reference',
        'services_detail',
        'generation_type',
        'billing_period',
        'deactivation_review_required',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'services_detail' => 'json',
        'amount' => 'decimal:2',
        'taxes' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sent' => 'boolean',
        'deactivation_review_required' => 'boolean',
    ];

    /**
     * Relación con contrato
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Relación con pagos
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Intents Yape pendientes o validados para este recibo.
     */
    public function yapePaymentIntents()
    {
        return $this->hasMany(YapePaymentIntent::class);
    }

    /**
     * Relación con ítems de factura
     */
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Relación con notas de crédito
     */
    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * Relación con recordatorios de pago
     */
    public function reminders()
    {
        return $this->hasMany(PaymentReminder::class);
    }

    /**
     * Obtener el cliente asociado a través del contrato
     */
    public function customer()
    {
        return $this->contract->customer();
    }

    /**
     * Verificar si la factura está vencida
     */
    public function isOverdue()
    {
        return in_array($this->status, ['pending', 'partial'], true)
            && now()->startOfDay()->greaterThanOrEqualTo($this->collectionStartDate());
    }

    /**
     * Fecha desde la que la deuda entra en cobranza luego del periodo de gracia.
     */
    public function collectionStartDate()
    {
        $gracePeriodDays = max(0, (int) config('billing.invoice.grace_period_days', 5));

        return $this->due_date->copy()->addDays($gracePeriodDays)->startOfDay();
    }

    /**
     * Calcular cuántos días faltan para vencimiento
     */
    public function daysUntilDue()
    {
        return now()->startOfDay()->diffInDays($this->collectionStartDate(), false);
    }

    /**
     * Calcular cuántos días está vencida
     */
    public function daysOverdue()
    {
        if ($this->isOverdue()) {
            return now()->startOfDay()->diffInDays($this->collectionStartDate());
        }
        return 0;
    }

    /**
     * Scope that preloads the completed-payments sum and applied-credit-notes
     * sum as query-time aggregates, so getPaidAmountAttribute() and
     * getPendingAmountAttribute() can read them from the model's own
     * attributes instead of issuing a fresh query per invoice.
     *
     * Use this on any list/index query that renders paid_amount or
     * pending_amount for multiple invoices, to avoid an N+1.
     */
    public function scopeWithAmountTotals($query)
    {
        return $query
            ->withSum(['payments as completed_payments_sum' => function ($paymentsQuery) {
                $paymentsQuery->where('status', 'completed');
            }], 'amount')
            ->withSum(['creditNotes as applied_credit_notes_sum' => function ($creditNotesQuery) {
                $creditNotesQuery->where('status', 'applied');
            }], 'amount');
    }

    /**
     * Calcular monto pagado
     */
    public function getPaidAmountAttribute()
    {
        if (array_key_exists('completed_payments_sum', $this->attributes)) {
            return $this->attributes['completed_payments_sum'] ?? 0;
        }

        return $this->payments()->where('status', 'completed')->sum('amount');
    }

    /**
     * Calcular saldo pendiente
     */
    public function getPendingAmountAttribute()
    {
        $paidAmount = $this->paid_amount;

        if (array_key_exists('applied_credit_notes_sum', $this->attributes)) {
            $creditNoteAmount = $this->attributes['applied_credit_notes_sum'] ?? 0;
        } else {
            $creditNoteAmount = $this->creditNotes()->where('status', 'applied')->sum('amount');
        }

        return $this->total_amount - $paidAmount - $creditNoteAmount;
    }
}
