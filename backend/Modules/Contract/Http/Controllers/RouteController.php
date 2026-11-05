<?php

namespace Modules\Contract\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contract\Repositories\RouteRepository;
use Modules\Contract\Repositories\InstallationRepository;
use Modules\Core\Entities\AuditLog;
use Modules\Contract\Http\Requests\StoreRouteRequest;
use Modules\Contract\Http\Requests\UpdateRouteRequest;
use Illuminate\Support\Facades\Auth;

class RouteController extends Controller
{
    /**
     * @var RouteRepository
     */
    protected $routeRepository;

    /**
     * @var InstallationRepository
     */
    protected $installationRepository;

    /**
     * RouteController constructor.
     *
     * @param RouteRepository $routeRepository
     * @param InstallationRepository $installationRepository
     */
    public function __construct(
        RouteRepository $routeRepository,
        InstallationRepository $installationRepository
    ) {
        $this->routeRepository = $routeRepository;
        $this->installationRepository = $installationRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $zone = $request->get('zone');
        $date = $request->get('date');
        $perPage = $request->get('per_page', 10);

        $query = $this->routeRepository->query();

        // Apply filters
        if ($search) {
            $query->where('zone', 'like', "%{$search}%");
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($zone) {
            $query->where('zone', $zone);
        }

        if ($date) {
            $query->whereDate('date', $date);
        }

        // With relationships
        $query->with(['installations']);

        // Order by date
        $query->orderBy('date', 'desc');

        $routes = $query->paginate($perPage);

        // Get zones for filter
        $zones = $this->routeRepository->getAvailableZones();

        return view('contract::routes.index', compact(
            'routes', 
            'search', 
            'status', 
            'zone',
            'date',
            'zones'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        // Get available installations for assignment
        $availableInstallations = $this->installationRepository->query()
            ->where('status', 'scheduled')
            ->whereNull('route_id')
            ->with(['contract.customer'])
            ->get();

        return view('contract::routes.create', compact('availableInstallations'));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreRouteRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRouteRequest $request)
    {
        $data = $request->validated();
        
        // Create route
        $route = $this->routeRepository->create([
            'date' => $data['date'],
            'zone' => $data['zone'],
            'order' => $data['order'] ?? 0,
            'start_coordinates' => $data['start_coordinates'] ?? null,
            'end_coordinates' => $data['end_coordinates'] ?? null,
            'status' => 'scheduled'
        ]);
        
        // Assign installations to this route if provided
        if (isset($data['installations']) && is_array($data['installations'])) {
            foreach ($data['installations'] as $installationId) {
                $installation = $this->installationRepository->find($installationId);
                $installation->update(['route_id' => $route->id]);
            }
        }
        
        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'route_created',
            'routes',
            "Ruta creada para la zona {$route->zone} en fecha {$route->date->format('d/m/Y')}",
            $request->ip(),
            null,
            $route->toArray()
        );
        
        return redirect()->route('contract.routes.show', $route->id)
            ->with('success', 'Ruta creada correctamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $route = $this->routeRepository->getWithInstallations($id);
        
        return view('contract::routes.show', compact('route'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $route = $this->routeRepository->getWithInstallations($id);
        
        // Check if route can be edited
        if ($route->status === 'completed' || $route->status === 'cancelled') {
            return redirect()->route('contract.routes.show', $id)
                ->with('error', 'No se puede editar una ruta completada o cancelada.');
        }
        
        // Get available installations for assignment
        $availableInstallations = $this->installationRepository->query()
            ->where('status', 'scheduled')
            ->where(function($query) use ($route) {
                $query->whereNull('route_id')
                    ->orWhere('route_id', $route->id);
            })
            ->with(['contract.customer'])
            ->get();
        
        // Get current installations IDs for pre-selection
        $currentInstallations = $route->installations->pluck('id')->toArray();
        
        return view('contract::routes.edit', compact(
            'route',
            'availableInstallations',
            'currentInstallations'
        ));
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateRouteRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRouteRequest $request, $id)
    {
        $route = $this->routeRepository->find($id);
        $data = $request->validated();
        
        // Save old data for audit
        $oldData = $route->toArray();
        
        // Update route
        $route->update([
            'date' => $data['date'],
            'zone' => $data['zone'],
            'order' => $data['order'] ?? $route->order,
            'start_coordinates' => $data['start_coordinates'] ?? $route->start_coordinates,
            'end_coordinates' => $data['end_coordinates'] ?? $route->end_coordinates,
            'status' => $data['status'] ?? $route->status
        ]);
        
        // Update installations assignments
        // First, remove all current assignments
        $this->installationRepository->query()
            ->where('route_id', $id)
            ->update(['route_id' => null]);
        
        // Then, assign installations to this route if provided
        if (isset($data['installations']) && is_array($data['installations'])) {
            foreach ($data['installations'] as $installationId) {
                $installation = $this->installationRepository->find($installationId);
                $installation->update(['route_id' => $route->id]);
            }
        }
        
        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'route_updated',
            'routes',
            "Ruta actualizada para la zona {$route->zone} en fecha {$route->date->format('d/m/Y')}",
            $request->ip(),
            $oldData,
            $route->toArray()
        );
        
        return redirect()->route('contract.routes.show', $route->id)
            ->with('success', 'Ruta actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $route = $this->routeRepository->find($id);
        
        // Check if route has installations
        if ($route->installations()->count() > 0) {
            return redirect()->route('contract.routes.index')
                ->with('error', 'No se puede eliminar una ruta que tiene instalaciones asociadas.');
        }
        
        // Save old data for audit
        $routeData = $route->toArray();
        
        // Delete route
        $this->routeRepository->delete($id);
        
        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'route_deleted',
            'routes',
            "Ruta eliminada para la zona {$route->zone} en fecha {$route->date->format('d/m/Y')}",
            $request->ip(),
            $routeData,
            null
        );
        
        return redirect()->route('contract.routes.index')
            ->with('success', 'Ruta eliminada correctamente.');
    }

    /**
     * Change route status.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:scheduled,in_progress,completed,cancelled'
        ]);
        
        $route = $this->routeRepository->find($id);
        
        // Save old data for audit
        $oldData = $route->toArray();
        
        // Change status
        $route = $this->routeRepository->changeStatus($id, $request->status);
        
        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'route_status_changed',
            'routes',
            "Estado de ruta cambiado a {$request->status} para la zona {$route->zone}",
            $request->ip(),
            $oldData,
            $route->toArray()
        );
        
        return redirect()->route('contract.routes.show', $route->id)
            ->with('success', 'Estado de la ruta actualizado correctamente.');
    }

    /**
     * Display routes for today.
     * @return Renderable
     */
    public function today()
    {
        $routes = $this->routeRepository->getForToday();
        
        return view('contract::routes.today', compact('routes'));
    }

    /**
     * Display active routes with installations.
     * @return Renderable
     */
    public function active()
    {
        $routes = $this->routeRepository->getActiveRoutesWithInstallations();
        
        return view('contract::routes.active', compact('routes'));
    }

    /**
     * Show route on map.
     * @param int $id
     * @return Renderable
     */
    public function map($id)
    {
        $route = $this->routeRepository->getWithInstallations($id);
        
        // Get installations coordinates from customer addresses
        $installationsData = [];
        foreach ($route->installations as $installation) {
            if ($installation->contract && $installation->contract->customer) {
                $address = $installation->contract->customer->addresses()
                    ->where('is_primary', true)
                    ->first();
                
                if ($address && $address->coordinates) {
                    $installationsData[] = [
                        'id' => $installation->id,
                        'customer_name' => $installation->contract->customer->first_name . ' ' . $installation->contract->customer->last_name,
                        'address' => $address->street . ' ' . $address->number . ', ' . $address->city,
                        'coordinates' => $address->coordinates,
                        'status' => $installation->status
                    ];
                }
            }
        }
        
        return view('contract::routes.map', compact('route', 'installationsData'));
    }
}