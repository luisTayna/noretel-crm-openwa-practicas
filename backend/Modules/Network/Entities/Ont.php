<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Contract\Entities\Contract;
use Modules\Customer\Entities\Customer;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Equipo terminal del cliente (ONT).
 *
 * Es una entidad de red con vida propia: se cambia, se reautoriza y se mueve de
 * puerto sin que eso deba tocar el acta de instalación. Por eso no cuelga de
 * InstalledEquipment, solo lo referencia para trazabilidad.
 *
 * Esta entidad no calcula el estado de señal: lo escribe el servicio que
 * procesa las lecturas. Aquí solo se lee y se traduce a lenguaje humano.
 */
class Ont extends Model implements Auditable
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'serial_number',
        'model',
        'olt_pon_port_id',
        'ont_index',
        'customer_id',
        'contract_id',
        'installed_equipment_id',
        'status',
        'last_rx_power',
        'last_tx_power',
        'baseline_rx_power',
        'distance_m',
        'last_seen_at',
        'signal_status',
        'notes',
    ];

    protected $casts = [
        'last_rx_power' => 'decimal:2',
        'last_tx_power' => 'decimal:2',
        'baseline_rx_power' => 'decimal:2',
        'last_seen_at' => 'datetime',
    ];

    protected $appends = [
        'signal_label',
        'signal_color',
    ];

    public function ponPort()
    {
        return $this->belongsTo(OltPonPort::class, 'olt_pon_port_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Texto que ve cualquier persona del CRM, sin vocabulario de red.
     */
    public function getSignalLabelAttribute(): string
    {
        return match ($this->signal_status) {
            'good' => 'Señal buena',
            'warning' => 'Señal débil',
            'critical' => 'Señal muy débil',
            'no_signal' => 'Sin señal',
            default => 'Sin datos',
        };
    }

    public function getSignalColorAttribute(): string
    {
        return match ($this->signal_status) {
            'good' => 'success',
            'warning' => 'warning',
            'critical' => 'danger',
            'no_signal' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Dirección completa del ONT en la OLT, por ejemplo "0/1/0:12".
     * Es el dato que pide el técnico, no la persona de atención.
     */
    public function getAddressAttribute(): ?string
    {
        if (! $this->ponPort || $this->ont_index === null) {
            return null;
        }

        return "{$this->ponPort->address}:{$this->ont_index}";
    }

    /**
     * Un ONT sin cliente asignado es una lectura huérfana: llega de la OLT
     * pero el CRM todavía no sabe de quién es.
     */
    public function getIsOrphanAttribute(): bool
    {
        return $this->customer_id === null;
    }

    public function scopeOrphan($query)
    {
        return $query->whereNull('customer_id');
    }

    public function scopeNeedsAttention($query)
    {
        return $query->whereIn('signal_status', ['warning', 'critical', 'no_signal']);
    }
}
