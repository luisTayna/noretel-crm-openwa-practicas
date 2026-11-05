<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\Entities\Installation;
use Modules\Inventory\Entities\Equipment;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class InstalledEquipment extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'installation_id',
        'equipment_id',
        'serial',
        'mac_address',
        'status',
    ];

    /**
     * Get the installation that owns the equipment.
     */
    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    /**
     * Get the equipment model associated with this installed equipment.
     */
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Check if the MAC address is valid.
     */
    public function isValidMacAddress()
    {
        return preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $this->mac_address) === 1;
    }

    /**
     * Validate the serial number against the equipment type.
     */
    public function isValidSerial()
    {
        if (!$this->equipment) {
            return false;
        }
        
        // Different equipment types might have different serial number patterns
        switch ($this->equipment->equipment_type) {
            case 'router':
                return preg_match('/^RT-\d{6}$/', $this->serial) === 1;
            case 'onu':
                return preg_match('/^ONU-\d{6}$/', $this->serial) === 1;
            default:
                return strlen($this->serial) >= 6;
        }
    }
}