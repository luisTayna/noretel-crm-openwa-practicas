<?php

namespace Modules\Operations\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Entities\Invoice;
use Modules\Contract\Entities\Contract;
use Modules\Contract\Entities\Installation;
use Modules\Core\Entities\Employee;
use Modules\Customer\Entities\Customer;
use Modules\Network\Entities\ServiceRequest;
use Modules\Services\Entities\Plan;

class OnboardingOrder extends Model
{
    use HasFactory;

    public const STATUS_CREATED = 'created';
    public const STATUS_INSTALLATION_SCHEDULED = 'installation_scheduled';
    public const STATUS_INSTALLED = 'installed';
    public const STATUS_ACTIVATION_PENDING = 'activation_pending';
    public const STATUS_ACTIVATED = 'activated';
    public const STATUS_CONTRACT_PENDING_30 = 'contract_pending_30';
    public const STATUS_CONTRACTED = 'contracted';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'order_number',
        'customer_id',
        'plan_id',
        'technician_id',
        'installation_id',
        'activation_request_id',
        'contract_id',
        'invoice_id',
        'status',
        'expected_contract_date',
        'final_price',
        'activation_data',
        'notes',
        'activated_at',
        'contracted_at',
        'invoiced_at',
        'paid_at',
        'created_by',
    ];

    protected $casts = [
        'expected_contract_date' => 'date',
        'final_price' => 'decimal:2',
        'activation_data' => 'array',
        'activated_at' => 'datetime',
        'contracted_at' => 'datetime',
        'invoiced_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }

            if (empty($order->expected_contract_date)) {
                $order->expected_contract_date = self::nextContractDeliveryDate();
            }
        });
    }

    public static function generateOrderNumber(): string
    {
        $prefix = 'OA-' . now()->format('Ymd');
        $lastOrderNumber = self::query()
            ->where('order_number', 'like', $prefix . '-%')
            ->orderByDesc('order_number')
            ->value('order_number');

        $lastSequence = 0;

        if (is_string($lastOrderNumber) && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastOrderNumber, $matches)) {
            $lastSequence = (int) $matches[1];
        }

        return $prefix . '-' . str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function nextContractDeliveryDate(): string
    {
        $date = now()->day <= 30
            ? now()->copy()->day(min(30, now()->daysInMonth))
            : now()->copy()->addMonthNoOverflow()->day(min(30, now()->copy()->addMonthNoOverflow()->daysInMonth));

        return $date->toDateString();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function technician()
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }

    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    public function activationRequest()
    {
        return $this->belongsTo(ServiceRequest::class, 'activation_request_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_PAID]);
    }

    public function scopeReadyForContract($query)
    {
        return $query->where('status', self::STATUS_CONTRACT_PENDING_30)
            ->whereDate('expected_contract_date', '<=', now()->toDateString());
    }

    public function getStatusLabelAttribute(): string
    {
        return [
            self::STATUS_CREATED => 'Orden registrada',
            self::STATUS_INSTALLATION_SCHEDULED => 'Instalación programada',
            self::STATUS_INSTALLED => 'Instalado',
            self::STATUS_ACTIVATION_PENDING => 'Habilitación pendiente',
            self::STATUS_ACTIVATED => 'Equipo habilitado',
            self::STATUS_CONTRACT_PENDING_30 => 'Contrato pendiente día 30',
            self::STATUS_CONTRACTED => 'Contrato generado',
            self::STATUS_INVOICED => 'Recibo pendiente generado',
            self::STATUS_PAID => 'Pagado',
        ][$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }
}
