<?php

namespace Modules\Network\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RouterMetricsHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'router_id',
        'connected_clients',
        'cpu_usage',
        'memory_usage',
        'signal_quality',
        'bandwidth_usage',
        'bandwidth_download',
        'bandwidth_upload',
        'uptime',
        'temperature',
        'status',
        'packet_loss',
        'latency',
        'recorded_at',
    ];

    protected $casts = [
        'cpu_usage' => 'decimal:2',
        'memory_usage' => 'decimal:2',
        'signal_quality' => 'decimal:2',
        'temperature' => 'decimal:2',
        'recorded_at' => 'datetime',
    ];

    /**
     * Relación con router
     */
    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * Scopes
     */
    public function scopeForRouter($query, $routerId)
    {
        return $query->where('router_id', $routerId);
    }

    public function scopeBetween($query, $start, $end)
    {
        return $query->whereBetween('recorded_at', [$start, $end]);
    }

    public function scopeLastHours($query, $hours = 24)
    {
        return $query->where('recorded_at', '>=', now()->subHours($hours));
    }

    public function scopeLastDays($query, $days = 7)
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }

    /**
     * Crear snapshot de métricas
     */
    public static function createSnapshot(Router $router)
    {
        return self::create([
            'router_id' => $router->id,
            'connected_clients' => $router->connected_clients,
            'cpu_usage' => $router->cpu_usage,
            'memory_usage' => $router->memory_usage,
            'signal_quality' => $router->signal_quality,
            'bandwidth_usage' => $router->bandwidth_usage,
            'uptime' => $router->uptime,
            'status' => $router->status,
            'recorded_at' => now(),
        ]);
    }
}
