<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Chasis OLT. Solo describe el equipo y cómo alcanzarlo;
 * la conversación con la OLT vive en los servicios, no aquí.
 */
class Olt extends Model implements Auditable
{
    use HasFactory, SoftDeletes, AuditableTrait;

    protected $fillable = [
        'name',
        'code',
        'brand',
        'model',
        'management_ip',
        'ssh_port',
        'ssh_username',
        'credentials',
        'snmp_enabled',
        'snmp_port',
        'node_id',
        'location',
        'zone',
        'status',
        'last_sync_at',
        'last_sync_error',
        'notes',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'snmp_enabled' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Las credenciales nunca salen en serializaciones ni en logs.
     */
    protected $hidden = [
        'credentials',
    ];

    public function boards()
    {
        return $this->hasMany(OltBoard::class);
    }

    public function ponPorts()
    {
        return $this->hasManyThrough(OltPonPort::class, OltBoard::class);
    }

    /**
     * ONTs colgados de esta OLT. No es una relación de Eloquent porque son tres
     * saltos (OLT → tarjeta → puerto → ONT); devuelve un query builder.
     */
    public function ontsQuery()
    {
        return Ont::whereIn(
            'olt_pon_port_id',
            OltPonPort::whereIn('olt_board_id', $this->boards()->select('id'))->select('id')
        );
    }

    public function node()
    {
        return $this->belongsTo(Node::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
