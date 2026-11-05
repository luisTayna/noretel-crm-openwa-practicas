<?php

namespace Modules\Services\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    public const SERVICE_TYPE_INTERNET = 'internet';

    public const TECHNOLOGY_FIBER_WIFI = 'fiber_wifi';

    public const LEGACY_FIBER_TECHNOLOGIES = [
        'fiber',
        'ftth',
    ];

    protected $fillable = [
        'name',
        'description',
        'service_type',
        'technology',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean'
    ];

    /**
     * Los planes asociados a este servicio.
     */
    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Los servicios adicionales asociados a este servicio.
     */
    public function additionalServices()
    {
        return $this->hasMany(AdditionalService::class);
    }

    /**
     * Obtener solo servicios activos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public static function serviceTypeOptions(): array
    {
        return [
            self::SERVICE_TYPE_INTERNET => ['label' => 'Internet', 'active' => true],
            'voip' => ['label' => 'VoIP', 'active' => false],
            'tv' => ['label' => 'Televisión', 'active' => false],
            'hosting' => ['label' => 'Hosting', 'active' => false],
            'cloud' => ['label' => 'Cloud', 'active' => false],
            'other' => ['label' => 'Otro', 'active' => false],
        ];
    }

    public static function technologyOptions(): array
    {
        return [
            self::TECHNOLOGY_FIBER_WIFI => ['label' => 'Fibra Óptica + WiFi', 'active' => true],
            'wireless' => ['label' => 'Inalámbrico', 'active' => false],
            'cable' => ['label' => 'Cable Coaxial', 'active' => false],
            'dsl' => ['label' => 'DSL', 'active' => false],
            'satellite' => ['label' => 'Satélite', 'active' => false],
            'other' => ['label' => 'Otro', 'active' => false],
        ];
    }

    public static function activeServiceTypeKeys(): array
    {
        return array_keys(array_filter(self::serviceTypeOptions(), fn ($option) => $option['active']));
    }

    public static function activeTechnologyKeys(): array
    {
        return array_keys(array_filter(self::technologyOptions(), fn ($option) => $option['active']));
    }

    public static function normalizeTechnology(?string $technology): ?string
    {
        if (in_array($technology, self::LEGACY_FIBER_TECHNOLOGIES, true)) {
            return self::TECHNOLOGY_FIBER_WIFI;
        }

        return $technology;
    }

    public function getServiceTypeLabelAttribute(): string
    {
        return self::serviceTypeOptions()[$this->service_type]['label'] ?? ucfirst((string) $this->service_type);
    }

    public function getTechnologyLabelAttribute(): string
    {
        return self::technologyOptions()[$this->technology]['label'] ?? ucfirst((string) $this->technology);
    }
}
