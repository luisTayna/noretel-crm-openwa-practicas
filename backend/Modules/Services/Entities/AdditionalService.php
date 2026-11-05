<?php

namespace Modules\Services\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdditionalService extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'price',
        'description',
        'configurable',
        'configuration_options'
    ];

    protected $casts = [
        'price' => 'float',
        'configurable' => 'boolean',
        'configuration_options' => 'array'
    ];

    /**
     * El servicio principal al que pertenece este servicio adicional.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Los servicios contratados que incluyen este servicio adicional.
     */
    public function contractedServices()
    {
        return $this->hasMany(\Modules\Contract\Entities\ContractedService::class);
    }
}
