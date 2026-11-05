<?php

namespace Modules\Network\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Network\Entities\Router;
use Modules\Network\Entities\Node;
use Modules\Network\Services\RouterService;
use Modules\Network\Http\Requests\StoreRouterRequest;
use Modules\Network\Http\Requests\UpdateRouterRequest;

class RouterController extends Controller
{
    protected $routerService;

    public function __construct(RouterService $routerService)
    {
        $this->routerService = $routerService;
    }

    /**
     * Mostrar lista de routers
     */
    public function index(Request $request)
    {
        $query = Router::with(['node', 'customers']);

        // Filtros
        if ($request->filled('zone')) {
            $query->byZone($request->zone);
        }

        if ($request->filled('brand')) {
            $query->byBrand($request->brand);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $routers = $query->paginate(20);

        $zones = Router::distinct('zone')->pluck('zone');
        $brands = ['Huawei', 'MikroTik', 'Cisco', 'TP-Link', 'Ubiquiti'];

        return view('network::routers.index', compact('routers', 'zones', 'brands'));
    }

    /**
     * Mostrar formulario de creación
     */
    public function create()
    {
        $nodes = Node::active()->get();
        $brands = ['Huawei', 'MikroTik', 'Cisco', 'TP-Link', 'Ubiquiti'];
        
        return view('network::routers.create', compact('nodes', 'brands'));
    }

    /**
     * Guardar nuevo router
     */
    public function store(StoreRouterRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Generar código si no se proporcionó
            if (empty($data['code'])) {
                $data['code'] = Router::generateCode($data['brand'], $data['zone']);
            }

            $router = Router::create($data);

            return redirect()
                ->route('network.routers.show', $router)
                ->with('success', 'Router creado exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al crear router', [
                'action' => 'router.store',
                'data' => $data ?? null,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo crear el router. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Mostrar detalles del router
     */
    public function show(Router $router)
    {
        $router->load([
            'node',
            'customers.user',
            'serviceRequests' => function($q) {
                $q->latest()->limit(10);
            },
            'logs' => function($q) {
                $q->latest()->limit(20);
            }
        ]);

        // Obtener métricas recientes
        $recentMetrics = $router->metricsHistory()
            ->lastHours(24)
            ->get();

        // Clientes activos
        $activeCustomers = $router->customers()
            ->wherePivot('connection_status', 'active')
            ->count();

        return view('network::routers.show', compact('router', 'recentMetrics', 'activeCustomers'));
    }

    /**
     * Mostrar formulario de edición
     */
    public function edit(Router $router)
    {
        $nodes = Node::active()->get();
        $brands = ['Huawei', 'MikroTik', 'Cisco', 'TP-Link', 'Ubiquiti'];
        
        return view('network::routers.edit', compact('router', 'nodes', 'brands'));
    }

    /**
     * Actualizar router
     */
    public function update(UpdateRouterRequest $request, Router $router)
    {
        try {
            $router->update($request->validated());

            return redirect()
                ->route('network.routers.show', $router)
                ->with('success', 'Router actualizado exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al actualizar router', [
                'action' => 'router.update',
                'router_id' => $router->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se pudo actualizar el router. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Eliminar router
     */
    public function destroy(Router $router)
    {
        try {
            // Verificar que no tenga clientes activos
            if ($router->customers()->wherePivot('connection_status', 'active')->exists()) {
                return redirect()
                    ->back()
                    ->with('error', 'No se puede eliminar un router con clientes activos');
            }

            $router->delete();

            return redirect()
                ->route('network.routers.index')
                ->with('success', 'Router eliminado exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al eliminar router', [
                'action' => 'router.destroy',
                'router_id' => $router->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo eliminar el router. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Reiniciar router
     */
    public function reboot(Router $router)
    {
        try {
            $result = $this->routerService->reboot($router);

            if ($result['success']) {
                return redirect()
                    ->back()
                    ->with('success', $result['message']);
            } else {
                return redirect()
                    ->back()
                    ->with('error', 'Error al reiniciar: ' . $result['error']);
            }

        } catch (\Exception $e) {
            \Log::error('Error al reiniciar router', [
                'action' => 'router.reboot',
                'router_id' => $router->id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo reiniciar el router. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Obtener estado actual del router
     */
    public function status(Router $router)
    {
        try {
            $result = $this->routerService->getStatus($router);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            \Log::error('Error al obtener estado del router', [
                'action' => 'router.status',
                'router_id' => $router->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo obtener el estado del router. Inténtelo nuevamente o contacte al administrador.'
            ], 500);
        }
    }

    /**
     * Obtener métricas e histórico
     */
    public function metrics(Router $router, Request $request)
    {
        $period = $request->get('period', '24h');

        try {
            $stats = $this->routerService->getStatistics($router, $period);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener métricas del router', [
                'action' => 'router.metrics',
                'router_id' => $router->id,
                'period' => $period,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudieron obtener las métricas del router. Inténtelo nuevamente o contacte al administrador.'
            ], 500);
        }
    }

    /**
     * Ver logs del router
     */
    public function logs(Router $router, Request $request)
    {
        $logs = $router->logs()
            ->with(['user', 'serviceRequest'])
            ->latest()
            ->paginate(50);

        if ($request->wantsJson()) {
            return response()->json($logs);
        }

        return view('network::routers.logs', compact('router', 'logs'));
    }

    /**
     * Asignar cliente a router
     */
    public function assignCustomer(Request $request, Router $router)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'contract_id' => 'required|exists:contracts,id',
            'pppoe_username' => 'required|string',
            'pppoe_password' => 'required|string',
            'bandwidth_limit_down' => 'required|integer|min:1',
            'bandwidth_limit_up' => 'required|integer|min:1',
        ]);

        try {
            // Verificar capacidad
            if (!$router->hasAvailableCapacity()) {
                return redirect()
                    ->back()
                    ->with('error', 'El router ha alcanzado su capacidad máxima');
            }

            $router->customers()->attach($request->customer_id, [
                'contract_id' => $request->contract_id,
                'pppoe_username' => $request->pppoe_username,
                'pppoe_password' => bcrypt($request->pppoe_password),
                'bandwidth_limit_down' => $request->bandwidth_limit_down,
                'bandwidth_limit_up' => $request->bandwidth_limit_up,
                'connection_status' => 'active',
                'assigned_at' => now(),
            ]);

            // Incrementar contador
            $router->increment('connected_clients');

            return redirect()
                ->back()
                ->with('success', 'Cliente asignado al router exitosamente');

        } catch (\Exception $e) {
            \Log::error('Error al asignar cliente al router', [
                'action' => 'router.assignCustomer',
                'router_id' => $router->id,
                'customer_id' => $request->customer_id,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo asignar el cliente al router. Inténtelo nuevamente o contacte al administrador.');
        }
    }

    /**
     * Remover cliente del router
     */
    public function removeCustomer(Router $router, $customerId)
    {
        try {
            $router->customers()->detach($customerId);
            $router->decrement('connected_clients');

            return redirect()
                ->back()
                ->with('success', 'Cliente removido del router');

        } catch (\Exception $e) {
            \Log::error('Error al remover cliente del router', [
                'action' => 'router.removeCustomer',
                'router_id' => $router->id,
                'customer_id' => $customerId,
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->with('error', 'No se pudo remover el cliente del router. Inténtelo nuevamente o contacte al administrador.');
        }
    }
}
