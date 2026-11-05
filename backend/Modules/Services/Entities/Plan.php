<?php

namespace Modules\Services\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'price',
        'upload_speed',
        'download_speed',
        'features',
        'commitment_period',
        'active',
        'is_featured',
        'featured_order'
    ];
    
    protected $casts = [
        'price' => 'float',
        'upload_speed' => 'integer',
        'download_speed' => 'integer',
        'features' => 'array',
        'commitment_period' => 'integer',
        'active' => 'boolean',
        'is_featured' => 'boolean'
    ];
    

    /**
     * El servicio al que pertenece este plan.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Las promociones asociadas a este plan.
     */
    public function promotions()
    {
        return $this->belongsToMany(Promotion::class, 'plan_promotion');
    }

    /**
     * Los contratos que incluyen este plan.
     */
    public function contracts()
    {
        return $this->hasMany(\Modules\Contract\Entities\Contract::class);
    }

    /**
     * Obtener solo planes activos.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Obtener el precio mensual del plan con promoción, si aplica.
     *
     * @return float
     */
    public function getDiscountedPrice()
    {
        $activePromotion = $this->promotions()
            ->where('active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (!$activePromotion) {
            return $this->price;
        }

        if ($activePromotion->discount_type === 'percentage') {
            return $this->price * (100 - $activePromotion->discount) / 100;
        }

        return max(0, $this->price - $activePromotion->discount);
    }
}
