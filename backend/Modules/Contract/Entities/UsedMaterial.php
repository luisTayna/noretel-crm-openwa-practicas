<?php

namespace Modules\Contract\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Contract\Entities\Installation;
use Modules\Inventory\Entities\Material;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class UsedMaterial extends Model implements Auditable
{
    use HasFactory, AuditableTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'installation_id',
        'material_id',
        'quantity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the installation that used the material.
     */
    public function installation()
    {
        return $this->belongsTo(Installation::class);
    }

    /**
     * Get the material that was used.
     */
    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * Get the total cost of this used material.
     */
    public function getTotalCostAttribute()
    {
        if (!$this->material) {
            return 0;
        }
        
        return $this->quantity * $this->material->cost;
    }
}