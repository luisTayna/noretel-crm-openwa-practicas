<?php

namespace Modules\Customer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Entities\User;
use Modules\Contract\Entities\Contract;
use Modules\Operations\Entities\OnboardingOrder;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_type',
        'first_name',
        'last_name',
        'identity_document',
        'email',
        'phone',
        'credit_score',
        'contact_preferences',
        'segment',
        'bi_segment',
        'bi_score',
        'bi_updated_at',
        'registration_date',
        'active',
        'deactivated_at',
        'customer_status',
        'first_purchase_at',
        'last_purchase_at',
        'churned_at',
        'lifetime_value',
        'contract_count',
        'months_as_customer',
        'acquisition_channel',
        'utm_source',
        'utm_campaign',
    ];

    protected $casts = [
        'active' => 'boolean',
        'registration_date' => 'datetime',
        'deactivated_at' => 'datetime',
        'bi_updated_at' => 'datetime',
        'bi_score' => 'float',
        'first_purchase_at' => 'datetime',
        'last_purchase_at' => 'datetime',
        'churned_at' => 'datetime',
        'lifetime_value' => 'decimal:2',
    ];

    // ==================== RELACIONES ====================

    /**
     * Usuario asociado (cuenta en el sistema)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Contratos del cliente
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function onboardingOrders()
    {
        return $this->hasMany(OnboardingOrder::class);
    }

    public function yapePaymentIntents()
    {
        return $this->hasMany(\Modules\Billing\Entities\YapePaymentIntent::class);
    }

    public function creditMovements()
    {
        return $this->hasMany(\Modules\Billing\Entities\CustomerCreditMovement::class);
    }

    /**
     * Direcciones
     */
    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Contactos de emergencia
     */
    public function emergencyContacts()
    {
        return $this->hasMany(EmergencyContact::class);
    }

    /**
     * Documentos
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Interacciones
     */
    public function interactions()
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * Facturas del cliente a través de contratos
     */
    public function invoices()
    {
        return $this->hasManyThrough(
            \Modules\Billing\Entities\Invoice::class,
            \Modules\Contract\Entities\Contract::class,
            'customer_id', // Clave foránea en la tabla contracts
            'contract_id', // Clave foránea en la tabla invoices
            'id',          // Clave local en la tabla customers
            'id'           // Clave local en la tabla contracts
        );
    }

    // ==================== SCOPES ====================

    public function scopeLead($query)
    {
        return $query->where('customer_status', 'lead');
    }

    public function scopeProspect($query)
    {
        return $query->where('customer_status', 'prospect');
    }

    public function scopeNew($query)
    {
        return $query->where('customer_status', 'new');
    }

    public function scopeActive($query)
    {
        return $query->where('customer_status', 'active');
    }

    public function scopeFormer($query)
    {
        return $query->where('customer_status', 'former');
    }

    public function scopeHasAccount($query)
    {
        return $query->whereNotNull('user_id');
    }

    public function scopeWithoutAccount($query)
    {
        return $query->whereNull('user_id');
    }

    // ==================== MÉTODOS ====================

    /**
     * Verificar si tiene cuenta en el sistema
     */
    public function hasAccount(): bool
    {
        return !is_null($this->user_id);
    }

    /**
     * Obtener nombre completo
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Verificar si es cliente oficial (> 3 meses)
     */
    public function isOfficialCustomer(): bool
    {
        return $this->customer_status === 'active' &&
               $this->months_as_customer >= 3;
    }

    /**
     * Verificar si es cliente nuevo (< 3 meses)
     */
    public function isNewCustomer(): bool
    {
        return $this->customer_status === 'new' ||
               ($this->customer_status === 'active' && $this->months_as_customer < 3);
    }

    /**
     * Actualizar estado del cliente basado en contratos
     */
    public function updateCustomerStatus()
    {
        $activeContracts = $this->contracts()->where('status', 'active')->count();

        if ($activeContracts === 0) {
            // Sin contratos activos
            if ($this->contract_count > 0) {
                $this->customer_status = 'former'; // Cliente antiguo
            } else {
                $this->customer_status = 'lead'; // Solo preguntó
            }
        } else {
            // Con contratos activos
            if ($this->months_as_customer >= 3) {
                $this->customer_status = 'active'; // Cliente oficial
            } else {
                $this->customer_status = 'new'; // Cliente nuevo
            }
        }

        $this->save();
    }

    /**
     * Verificar si el cliente tiene contratos activos.
     */
    public function hasActiveContracts(): bool
    {
        return $this->contracts()->where('status', 'active')->exists();
    }

    /**
     * Verificar si el cliente tiene historial contractual.
     */
    public function hasContracts(): bool
    {
        return $this->contracts()->exists();
    }

    /**
     * Calcular meses como cliente
     */
    public function calculateMonthsAsCustomer()
    {
        if (!$this->first_purchase_at) {
            $this->months_as_customer = 0;
            return;
        }

        $this->months_as_customer = $this->first_purchase_at->diffInMonths(now());
        $this->save();
    }

    /**
     * Calcular valor de por vida (LTV)
     */
    public function calculateLifetimeValue()
    {
        $this->lifetime_value = $this->contracts()
            ->join('invoices', 'contracts.id', '=', 'invoices.contract_id')
            ->where('invoices.status', 'paid')
            ->sum('invoices.total_amount');

        $this->save();
    }
}
