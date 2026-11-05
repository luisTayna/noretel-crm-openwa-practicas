<?php

namespace Modules\Services\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Services\Services\AdditionalServiceService;
use Modules\Services\Services\ServiceService;
use Modules\Services\Http\Requests\AdditionalServiceRequest;
use Illuminate\Support\Facades\Auth;
use Modules\Services\Repositories\AdditionalServiceRepository;

class AdditionalServiceController extends Controller
{
    /**
     * @var AdditionalServiceRepository
     */
    protected $additionalServiceRepository;
    /**
     * @var AdditionalServiceService
     */
    protected $additionalServiceService;

    /**
     * @var ServiceService
     */
    protected $serviceService;

    /**
     * AdditionalServiceController constructor.
     *
     * @param AdditionalServiceService $additionalServiceService
     * @param ServiceService $serviceService
     */
    public function __construct(
        AdditionalServiceRepository $additionalServiceRepository,
        AdditionalServiceService $additionalServiceService,
        ServiceService $serviceService
    ) {
        $this->additionalServiceService = $additionalServiceService;
        $this->serviceService = $serviceService;
        $this->additionalServiceRepository = $additionalServiceRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $serviceId = $request->input('service_id');

        if ($serviceId) {
            $result = $this->additionalServiceService->getByService($serviceId);
            $service = $result['service'] ?? null;
            $additionalServices = $result['additionalServices'] ?? [];
        } else {
            $result = $this->additionalServiceService->getAllAdditionalServices();
            $service = null;
            $additionalServices = $result['additionalServices'];
        }

        return view('services::additional-services.index', [
            'additionalServices' => $additionalServices,
            'service' => $service
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create(Request $request)
    {
        $serviceId = $request->input('service_id');
        $services = $this->serviceService->getAllServices(true)['services'];

        $selectedService = null;
        if ($serviceId) {
            foreach ($services as $service) {
                if ($service->id == $serviceId) {
                    $selectedService = $service;
                    break;
                }
            }
        }

        return view('services::additional-services.create', [
            'services' => $services,
            'selectedService' => $selectedService
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param AdditionalServiceRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(AdditionalServiceRequest $request)
    {
        $result = $this->additionalServiceService->createAdditionalService(
            $request->validated(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']])->withInput();
        }

        if ($request->input('service_id')) {
            return redirect()->route('services.additional-services.index', ['service_id' => $request->input('service_id')])
                ->with('success', $result['message']);
        }

        return redirect()->route('services.additional-services.index')
            ->with('success', $result['message']);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $additionalService = $this->additionalServiceRepository->find($id);
        $service = $additionalService->service;

        return view('services::additional-services.show', [
            'additionalService' => $additionalService,
            'service' => $service
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $additionalService = $this->additionalServiceRepository->find($id);
        $services = $this->serviceService->getAllServices(true)['services'];

        return view('services::additional-services.edit', [
            'additionalService' => $additionalService,
            'services' => $services
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param AdditionalServiceRequest $request
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(AdditionalServiceRequest $request, $id)
    {
        $result = $this->additionalServiceService->updateAdditionalService(
            $id,
            $request->validated(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']])->withInput();
        }

        if ($request->input('service_id')) {
            return redirect()->route('services.additional-services.index', ['service_id' => $request->input('service_id')])
                ->with('success', $result['message']);
        }

        return redirect()->route('services.additional-services.index')
            ->with('success', $result['message']);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id, Request $request)
    {
        $additionalService = $this->additionalServiceRepository->find($id);
        $serviceId = $additionalService->service_id;

        $result = $this->additionalServiceService->deleteAdditionalService(
            $id,
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()->withErrors(['message' => $result['message']]);
        }

        if ($request->query('service_id')) {
            return redirect()->route('services.additional-services.index', ['service_id' => $serviceId])
                ->with('success', $result['message']);
        }

        return redirect()->route('services.additional-services.index')
            ->with('success', $result['message']);
    }
};
