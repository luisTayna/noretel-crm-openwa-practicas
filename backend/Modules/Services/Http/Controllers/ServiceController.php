<?php

namespace Modules\Services\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Services\Services\ServiceService;
use Modules\Services\Http\Requests\ServiceRequest;
use Illuminate\Support\Facades\Auth;
use Modules\Services\Entities\Service;
use Modules\Services\Repositories\ServiceRepository;

class ServiceController extends Controller
{
    /**
     * @var ServiceRepository
     */
    protected $serviceRepository;
    /**
     * @var ServiceService
     */
    protected $serviceService;

    /**
     * ServiceController constructor.
     *
     * @param ServiceService $serviceService
     */
    public function __construct(ServiceRepository $serviceRepository,
         ServiceService $serviceService)
    {
        $this->serviceService = $serviceService;
        $this->serviceRepository = $serviceRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $filter = $request->query('filter');

        if (in_array($filter, ['active', 'inactive'], true)) {
            $services = $this->serviceRepository->query()
                ->where('active', $filter === 'active')
                ->withCount(['plans', 'additionalServices'])
                ->get();
        } else {
            $result = $this->serviceService->getAllServices();
            $services = $result['services'];
        }

        return view('services::services.index', [
            'services' => $services
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $serviceTypes = Service::serviceTypeOptions();
        $technologies = Service::technologyOptions();

        return view('services::services.create', compact('serviceTypes', 'technologies'));
    }

    /**
     * Store a newly created resource in storage.
     * @param ServiceRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(ServiceRequest $request)
    {
        $result = $this->serviceService->createService(
            $request->validated(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']])->withInput();
        }

        return redirect()->route('services.services.index')
            ->with('success', $result['message']);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $service = $this->serviceRepository->find($id);

        $plans = $service->plans;
        $additionalServices = $service->additionalServices;

        return view('services::services.show', compact('service', 'plans', 'additionalServices'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $service = $this->serviceRepository->find($id);

        $serviceTypes = Service::serviceTypeOptions();
        $technologies = Service::technologyOptions();

        return view('services::services.edit', compact('service', 'serviceTypes', 'technologies'));
    }

    /**
     * Update the specified resource in storage.
     * @param ServiceRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ServiceRequest $request, $id)
    {
        $result = $this->serviceService->updateService(
            $id,
            $request->validated(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']])->withInput();
        }

        return redirect()->route('services.services.index')
            ->with('success', $result['message']);
    }

    /**
     * Activate the specified resource in storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activate($id, Request $request)
    {
        $result = $this->serviceService->toggleServiceStatus(
            $id,
            true,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Deactivate the specified resource in storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deactivate($id, Request $request)
    {
        $result = $this->serviceService->toggleServiceStatus(
            $id,
            false,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->back()->with('success', $result['message']);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id, Request $request)
    {
        $result = $this->serviceService->deleteService(
            $id,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']]);
        }

        return redirect()->route('services.services.index')
            ->with('success', $result['message']);
    }

    /**
     * API: List active services for the public frontend.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiIndex()
    {
        try {
            $services = $this->serviceRepository->query()
                ->where('active', true)
                ->with(['plans' => function ($query) {
                    $query->where('active', true)
                        ->orderBy('price', 'asc');
                }, 'additionalServices'])
                ->withCount(['plans', 'additionalServices'])
                ->orderBy('name')
                ->get()
                ->map(function ($service) {
                    return $this->formatServiceForFrontend($service);
                });

            return response()->json([
                'success' => true,
                'data' => $services,
                'total' => $services->count(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en apiIndex de ServiceController', [
                'action' => 'service.apiIndex',
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener servicios',
                'error' => 'No se pudieron obtener los servicios. Inténtelo nuevamente más tarde.',
            ], 500);
        }
    }

    /**
     * API: Show one active service for the public frontend.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function apiShow($id)
    {
        try {
            $service = $this->serviceRepository->query()
                ->where('id', $id)
                ->where('active', true)
                ->with(['plans' => function ($query) {
                    $query->where('active', true)
                        ->orderBy('price', 'asc');
                }, 'additionalServices'])
                ->withCount(['plans', 'additionalServices'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $this->formatServiceForFrontend($service),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en apiShow de ServiceController', [
                'action' => 'service.apiShow',
                'service_id' => $id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Servicio no encontrado',
                'error' => 'Servicio no encontrado.',
            ], 404);
        }
    }

    private function formatServiceForFrontend($service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description ?? '',
            'type' => $service->service_type,
            'typeLabel' => $service->service_type_label,
            'technology' => $service->technology,
            'technologyLabel' => $service->technology_label,
            'active' => (bool) $service->active,
            'plansCount' => (int) ($service->plans_count ?? $service->plans->count()),
            'additionalServicesCount' => (int) ($service->additional_services_count ?? $service->additionalServices->count()),
            'plans' => $service->plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'title' => $plan->name,
                    'price' => (float) $plan->price,
                    'downloadSpeed' => (float) $plan->download_speed,
                    'uploadSpeed' => (float) $plan->upload_speed,
                ];
            })->values(),
            'additionalServices' => $service->additionalServices->map(function ($additionalService) {
                return [
                    'id' => $additionalService->id,
                    'name' => $additionalService->name,
                    'description' => $additionalService->description ?? '',
                    'price' => (float) $additionalService->price,
                    'configurable' => (bool) $additionalService->configurable,
                ];
            })->values(),
        ];
    }
}
