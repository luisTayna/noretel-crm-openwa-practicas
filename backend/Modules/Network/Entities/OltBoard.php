<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Tarjeta física de la OLT. Importa como unidad de falla:
 * cuando se cae una tarjeta, se caen todos sus puertos PON a la vez.
 */
class OltBoard extends Model
{
    use HasFactory;

    protected $fillable = [
        'olt_id',
        'frame',
        'slot',
        'board_type',
        'port_count',
        'status',
    ];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function ponPorts()
    {
        return $this->hasMany(OltPonPort::class);
    }

    /**
     * Dirección Huawei de la tarjeta, por ejemplo "0/1".
     */
    public function getAddressAttribute(): string
    {
        return "{$this->frame}/{$this->slot}";
    }
}
