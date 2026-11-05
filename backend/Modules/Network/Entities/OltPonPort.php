<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Puerto PON. Es la unidad de consulta contra la OLT: se pide la información
 * óptica de todos los ONT de un puerto en una sola vez, nunca ONT por ONT.
 */
class OltPonPort extends Model
{
    use HasFactory;

    protected $fillable = [
        'olt_board_id',
        'port',
        'description',
        'status',
        'max_onts',
        'ont_count',
    ];

    public function board()
    {
        return $this->belongsTo(OltBoard::class, 'olt_board_id');
    }

    public function onts()
    {
        return $this->hasMany(Ont::class);
    }

    /**
     * Dirección Huawei completa del puerto, por ejemplo "0/1/0".
     */
    public function getAddressAttribute(): string
    {
        return $this->board ? "{$this->board->address}/{$this->port}" : (string) $this->port;
    }

    public function getOccupancyPercentageAttribute(): float
    {
        if (! $this->max_onts) {
            return 0.0;
        }

        return round(($this->ont_count / $this->max_onts) * 100, 1);
    }
}
