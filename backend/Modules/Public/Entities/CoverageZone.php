<?php

namespace Modules\Public\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoverageZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'department',
        'province',
        'district',
        'latitude',
        'longitude',
        'radius_km',
        'quality',
        'active',
        'available_plans',
        'description',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_km' => 'decimal:2',
        'active' => 'boolean',
        'available_plans' => 'array',
    ];

    /**
     * Verificar si un punto está dentro de esta zona
     */
    public function containsPoint($latitude, $longitude): bool
    {
        $distance = $this->calculateDistance($latitude, $longitude);
        return $distance <= $this->radius_km;
    }

    /**
     * Calcular distancia en kilómetros usando fórmula Haversine
     */
    public function calculateDistance($latitude, $longitude): float
    {
        $earthRadius = 6371; // Radio de la Tierra en km

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }

    /**
     * Scope para zonas activas
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope para buscar por distrito
     */
    public function scopeByDistrict($query, $district)
    {
        return $query->where('district', $district);
    }

    /**
     * Obtener planes disponibles (relación con módulo Services)
     */
    public function plans()
    {
        if (empty($this->available_plans)) {
            return collect();
        }

        return \Modules\Services\Entities\Plan::whereIn('id', $this->available_plans)
            ->where('active', true)
            ->get();
    }
}
