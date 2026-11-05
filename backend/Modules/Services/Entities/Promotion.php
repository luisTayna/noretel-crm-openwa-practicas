<?php

namespace Modules\Services\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'discount',
        'discount_type',
        'start_date',
        'end_date',
        'conditions',
        'active'
    ];

    protected $casts = [
        'discount' => 'float',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'conditions' => 'array',
        'active' => 'boolean'
    ];

    /**
     * Los planes a los que se aplica esta promoción.
     */
    public function plans()
    {
        return $this->belongsToMany(Plan::class);
    }

    /**
     * Determina si la promoción está actualmente vigente.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active &&
               $this->start_date <= now() &&
               $this->end_date >= now();
    }

    /**
     * Obtener solo promociones activas y vigentes.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrentlyActive($query)
    {
        return $query->where('active', true)
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>=', now());
    }
}
