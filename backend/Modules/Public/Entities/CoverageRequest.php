<?php

namespace Modules\Public\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Entities\User;

class CoverageRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'latitude',
        'longitude',
        'comments',
        'status',
        'ip_address',
        'user_agent',
        'notified_at',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'notified_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Relación con el usuario que aprobó
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Contar solicitudes en un radio específico
     */
    public static function countInRadius($latitude, $longitude, $radiusKm = 1): int
    {
        return self::where('status', 'pending')
            ->whereRaw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) <= ?
            ", [$latitude, $longitude, $latitude, $radiusKm])
            ->count();
    }

    /**
     * Obtener solicitudes cercanas
     */
    public static function getNearby($latitude, $longitude, $radiusKm = 1)
    {
        return self::where('status', 'pending')
            ->whereRaw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )) <= ?
            ", [$latitude, $longitude, $latitude, $radiusKm])
            ->get();
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
