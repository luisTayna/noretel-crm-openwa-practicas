<?php

namespace Modules\Network\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Network\Entities\Router;
use Modules\Network\Entities\Node;
use Modules\Network\Entities\ServiceRequest;
use Modules\Network\Entities\RouterLog;
use Illuminate\Support\Facades\DB;

class NetworkDashboardController extends Controller
{
    /**
     * Mostrar dashboard principal de red
     */
    public function index()
    {
        // Estadísticas generales
        $stats = [
            'total_routers' => Router::count(),
            'active_routers' => Router::where('status', 'active')->count(),
            'offline_routers' => Router::where('status', 'offline')->count(),
            'maintenance_routers' => Router::where('status', 'maintenance')->count(),

            'total_nodes' => Node::count(),
            'active_nodes' => Node::where('status', 'active')->count(),

            'total_clients' => Router::sum('connected_clients'),
            'max_capacity' => Router::sum('max_clients'),

            'pending_requests' => ServiceRequest::where('status', 'pending')->count(),
            'in_progress_requests' => ServiceRequest::where('status', 'in_progress')->count(),
            'completed_today' => ServiceRequest::where('status', 'completed')
                ->whereDate('completed_at', today())
                ->count(),
        ];

        // Calcular porcentaje de capacidad utilizada
        $stats['capacity_percentage'] = $stats['max_capacity'] > 0
            ? round(($stats['total_clients'] / $stats['max_capacity']) * 100, 2)
            : 0;

        // Routers con problemas
        $problematicRouters = Router::whereIn('status', ['error', 'offline'])
            ->orWhere('cpu_usage', '>', 80)
            ->orWhere('memory_usage', '>', 85)
            ->with('node')
            ->limit(10)
            ->get();

        // Routers más cargados
        $overloadedRouters = Router::where('status', 'active')
            ->whereRaw('(connected_clients / max_clients) > 0.85')
            ->with('node')
            ->orderByRaw('(connected_clients / max_clients) DESC')
            ->limit(10)
            ->get();

        // Solicitudes recientes
        $recentRequests = ServiceRequest::with(['customer', 'router'])
            ->latest()
            ->limit(10)
            ->get();

        // Distribución por zona
        $routersByZone = Router::select('zone', DB::raw('count(*) as total'))
            ->groupBy('zone')
            ->get();

        // Distribución por marca
        $routersByBrand = Router::select('brand', DB::raw('count(*) as total'))
            ->groupBy('brand')
            ->get();

        // Actividad reciente (últimas 24 horas)
        $recentLogs = RouterLog::with(['router', 'user'])
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->limit(15)
            ->get();

        return view('network::dashboard', compact(
            'stats',
            'problematicRouters',
            'overloadedRouters',
            'recentRequests',
            'routersByZone',
            'routersByBrand',
            'recentLogs'
        ));
    }

    /**
     * Obtener salud de la red
     */
    public function networkHealth()
    {
        $routers = Router::with('node')->get();

        $health = [
            'total' => $routers->count(),
            'healthy' => $routers->where('health_status', 'good')->count(),
            'warning' => $routers->where('health_status', 'warning')->count(),
            'critical' => $routers->where('health_status', 'critical')->count(),
            'by_zone' => [],
            'by_node' => [],
        ];

        // Salud por zona
        foreach ($routers->groupBy('zone') as $zone => $zoneRouters) {
            $health['by_zone'][$zone] = [
                'total' => $zoneRouters->count(),
                'healthy' => $zoneRouters->where('health_status', 'good')->count(),
                'warning' => $zoneRouters->where('health_status', 'warning')->count(),
                'critical' => $zoneRouters->where('health_status', 'critical')->count(),
            ];
        }

        // Salud por nodo
        foreach ($routers->groupBy('node_id') as $nodeId => $nodeRouters) {
            if ($nodeId) {
                $node = $nodeRouters->first()->node;
                $health['by_node'][$node->name ?? 'Sin nodo'] = [
                    'total' => $nodeRouters->count(),
                    'healthy' => $nodeRouters->where('health_status', 'good')->count(),
                    'warning' => $nodeRouters->where('health_status', 'warning')->count(),
                    'critical' => $nodeRouters->where('health_status', 'critical')->count(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $health
        ]);
    }

    /**
     * Obtener rendimiento de routers
     */
    public function routerPerformance(Request $request)
    {
        $period = $request->get('period', '24h');

        $hours = match($period) {
            '1h' => 1,
            '6h' => 6,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720,
            default => 24
        };

        // Obtener métricas agregadas
        $metrics = DB::table('router_metrics_history')
            ->select(
                DB::raw('AVG(cpu_usage) as avg_cpu'),
                DB::raw('MAX(cpu_usage) as max_cpu'),
                DB::raw('AVG(memory_usage) as avg_memory'),
                DB::raw('MAX(memory_usage) as max_memory'),
                DB::raw('AVG(connected_clients) as avg_clients'),
                DB::raw('MAX(connected_clients) as max_clients'),
                DB::raw('DATE_FORMAT(recorded_at, "%Y-%m-%d %H:00:00") as hour')
            )
            ->where('recorded_at', '>=', now()->subHours($hours))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $metrics
        ]);
    }

    /**
     * Resumen de solicitudes de servicio
     */
    public function serviceRequestsSummary(Request $request)
    {
        $period = $request->get('period', 'week');

        $startDate = match($period) {
            'today' => today(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfWeek()
        };

        $requests = ServiceRequest::where('created_at', '>=', $startDate)->get();

        $summary = [
            'total' => $requests->count(),
            'by_status' => [
                'pending' => $requests->where('status', 'pending')->count(),
                'in_progress' => $requests->where('status', 'in_progress')->count(),
                'completed' => $requests->where('status', 'completed')->count(),
                'failed' => $requests->where('status', 'failed')->count(),
                'cancelled' => $requests->where('status', 'cancelled')->count(),
            ],
            'by_type' => [
                'router_reboot' => $requests->where('type', 'router_reboot')->count(),
                'connection_issue' => $requests->where('type', 'connection_issue')->count(),
                'slow_speed' => $requests->where('type', 'slow_speed')->count(),
                'no_internet' => $requests->where('type', 'no_internet')->count(),
                'other' => $requests->where('type', 'other')->count(),
            ],
            'by_priority' => [
                'low' => $requests->where('priority', 'low')->count(),
                'medium' => $requests->where('priority', 'medium')->count(),
                'high' => $requests->where('priority', 'high')->count(),
                'critical' => $requests->where('priority', 'critical')->count(),
            ],
            'automated' => $requests->where('is_automated', true)->count(),
            'manual' => $requests->where('is_automated', false)->count(),
            'avg_resolution_time' => $requests->where('status', 'completed')
                ->whereNotNull('resolution_time')
                ->avg('resolution_time'),
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $summary
        ]);
    }
}
