<?php

namespace Modules\Contract\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contract\Repositories\InstallationRepository;
use Modules\Contract\Repositories\ContractRepository;
use Modules\Contract\Repositories\RouteRepository;
// COMENTADAS: Dependencias del módulo Inventory que no existe
// use Modules\Inventory\Repositories\EquipmentRepository;
// use Modules\Inventory\Repositories\MaterialRepository;
use Modules\Core\Repositories\EmployeeRepository;
use Modules\Core\Entities\AuditLog;
use Modules\Operations\Entities\OnboardingOrder;
use Modules\Contract\Http\Requests\StoreInstallationRequest;
use Modules\Contract\Http\Requests\UpdateInstallationRequest;
use Modules\Contract\Http\Requests\CompleteInstallationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class InstallationController extends Controller
{
    /**
     * @var InstallationRepository
     */
    protected $installationRepository;

    /**
     * @var ContractRepository
     */
    protected $contractRepository;

    /**
     * @var RouteRepository
     */
    protected $routeRepository;

    /**
     * @var EmployeeRepository
     */
    protected $employeeRepository;

    /**
     * InstallationController constructor.
     *
     * @param InstallationRepository $installationRepository
     * @param ContractRepository $contractRepository
     * @param RouteRepository $routeRepository
     * @param EmployeeRepository $employeeRepository
     */
    public function __construct(
        InstallationRepository $installationRepository,
        ContractRepository $contractRepository,
        RouteRepository $routeRepository,
        EmployeeRepository $employeeRepository
    ) {
        $this->installationRepository = $installationRepository;
        $this->contractRepository = $contractRepository;
        $this->routeRepository = $routeRepository;
        $this->employeeRepository = $employeeRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $technician = $request->get('technician');
        $date = $request->get('date');
        $contractId = $request->get('contract_id');
        $perPage = $request->get('per_page', 10);

        $query = $this->installationRepository->query();

        // Apply filters
        if ($search) {
            $query->whereHas('contract.customer', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('identity_document', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($technician) {
            $query->where('technician_id', $technician);
        }

        if ($date) {
            $query->whereDate('scheduled_date', $date);
        }

        if ($contractId) {
            $query->where('contract_id', $contractId);
        }

        // With relationships
        $query->with(['contract.customer', 'technician', 'route']);

        // Order by scheduled date
        $query->orderBy('scheduled_date', 'desc');

        $installations = $query->paginate($perPage);

        // Get technicians for filter
        $technicians = $this->employeeRepository->findAssignableTechnicians();

        // Get statistics for dashboard widgets
        $installationsByStatus = $this->installationRepository->countByStatus();
        $pendingCount = $this->installationRepository->getPendingInstallations()->count();
        $lateCount = $this->installationRepository->getLateInstallations()->count();
        $todayCount = $this->installationRepository->getScheduledForToday()->count();

        return view('contract::installations.index', compact(
            'installations',
            'search',
            'status',
            'technician',
            'date',
            'contractId',
            'technicians',
            'installationsByStatus',
            'pendingCount',
            'lateCount',
            'todayCount'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * @param Request $request
     * @return Renderable
     */
    public function create(Request $request)
    {
        $contractId = $request->get('contract_id');
        $onboardingOrderId = $request->get('onboarding_order_id');
        $contract = null;
        $onboardingOrder = null;
        $contracts = collect();
        $onboardingOrders = collect();

        if ($contractId) {
            $contract = $this->contractRepository->find($contractId);
        } elseif ($onboardingOrderId) {
            $onboardingOrder = OnboardingOrder::with(['customer', 'plan', 'technician'])->findOrFail($onboardingOrderId);
        } else {
            // Get contracts without active installations
            $contracts = $this->contractRepository->query()
                ->where('status', 'pending_installation')
                ->whereDoesntHave('installations', function($q) {
                    $q->whereIn('status', ['scheduled', 'in_progress']);
                })
                ->with(['customer'])
                ->get();

            $onboardingOrders = OnboardingOrder::query()
                ->whereIn('status', ['created', 'installation_scheduled'])
                ->whereDoesntHave('installation', function ($q) {
                    $q->whereIn('status', ['scheduled', 'in_progress']);
                })
                ->with(['customer', 'plan'])
                ->latest()
                ->get();
        }

        $technicians = $this->employeeRepository->findAssignableTechnicians();
        $routes = $this->routeRepository->query()->whereIn('status', ['scheduled', 'in_progress'])->get();

        // TEMPORALMENTE: Arrays vacíos para equipment y materials
        // Cuando crees el módulo Inventory, descomentar las líneas originales
        $equipment = [];
        $materials = [];

        return view('contract::installations.create', compact(
            'contract',
            'onboardingOrder',
            'contracts',
            'onboardingOrders',
            'technicians',
            'routes',
            'equipment',
            'materials'
        ));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreInstallationRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreInstallationRequest $request)
    {
        $data = $request->validated();

        // Prepare installation data
        $installationData = [
            'contract_id' => $data['contract_id'] ?? null,
            'onboarding_order_id' => $data['onboarding_order_id'] ?? null,
            'technician_id' => $data['technician_id'],
            'route_id' => $data['route_id'] ?? null,
            'scheduled_date' => $data['scheduled_date'],
            'status' => 'scheduled',
            'notes' => $data['notes'] ?? null,
        ];

        // Prepare equipment data (temporalmente vacío)
        $equipment = [];

        // Prepare materials data (temporalmente vacío)
        $materials = [];

        // Create installation with equipment and materials
        $installation = $this->installationRepository->createWithDetails($installationData, $equipment, $materials);

        if ($installation->onboarding_order_id) {
            OnboardingOrder::whereKey($installation->onboarding_order_id)->update([
                'installation_id' => $installation->id,
                'technician_id' => $installation->technician_id,
                'status' => 'installation_scheduled',
            ]);
        }

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'installation_created',
            'installations',
            $installation->contract_id
                ? "Instalación creada para el contrato #{$installation->contract_id}"
                : "Instalación creada para la orden de instalación #{$installation->onboarding_order_id}",
            $request->ip(),
            null,
            $installation->toArray()
        );

        return redirect()->route('contract.installations.show', $installation->id)
            ->with('success', 'Instalación creada correctamente.');
    }

    /**
     * Display the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $installation = $this->installationRepository->getWithRelations($id);

        return view('contract::installations.show', compact('installation'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $installation = $this->installationRepository->getWithRelations($id);
        $technicians = $this->employeeRepository->findAssignableTechnicians();
        $routes = $this->routeRepository->query()->whereIn('status', ['scheduled', 'in_progress'])->get();

        $onboardingOrders = OnboardingOrder::with(['customer', 'plan'])->latest()->get();

        return view('contract::installations.edit', compact('installation', 'technicians', 'routes', 'onboardingOrders'));
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateInstallationRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateInstallationRequest $request, $id)
    {
        $installation = $this->installationRepository->find($id);

        // Save old data for audit
        $oldData = $installation->toArray();

        $data = $request->validated();

        // Update installation
        $installation->update($data);

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'installation_updated',
            'installations',
            "Instalación actualizada #{$installation->id}",
            $request->ip(),
            $oldData,
            $installation->toArray()
        );

        return redirect()->route('contract.installations.show', $installation->id)
            ->with('success', 'Instalación actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $installation = $this->installationRepository->find($id);

        // Check if installation can be deleted
        if ($installation->status === 'completed') {
            return redirect()->route('contract.installations.index')
                ->with('error', 'No se puede eliminar una instalación completada.');
        }

        // Save old data for audit
        $installationData = $installation->toArray();

        // Delete installation
        $installation->delete();

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'installation_deleted',
            'installations',
            "Instalación eliminada #{$installation->id}",
            $request->ip(),
            $installationData,
            null
        );

        return redirect()->route('contract.installations.index')
            ->with('success', 'Instalación eliminada correctamente.');
    }

    /**
     * Show the form for completing an installation.
     * @param int $id
     * @return Renderable
     */
    public function showCompleteForm($id)
    {
        $installation = $this->installationRepository->getWithRelations($id);

        // Check if installation can be completed
        if ($installation->status === 'completed' || $installation->status === 'cancelled') {
            return redirect()->route('contract.installations.show', $id)
                ->with('error', 'No se puede completar una instalación ya finalizada o cancelada.');
        }

        return view('contract::installations.complete', compact('installation'));
    }

    /**
     * Complete an installation.
     * @param CompleteInstallationRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function complete(CompleteInstallationRequest $request, $id)
    {
        $installation = $this->installationRepository->find($id);
        $data = $request->validated();

        // Save old data for audit
        $oldData = $installation->toArray();

        // Prepare completion data
        $completionData = [
            'completed_date' => $data['completed_date'] ?? now(),
            'notes' => $data['notes'] ?? $installation->notes,
        ];

        // Handle customer signature if provided
        if ($request->has('customer_signature')) {
            $image = $request->get('customer_signature');
            if ($image) {
                // Remove header from base64 string
                $image = str_replace('data:image/png;base64,', '', $image);
                $image = str_replace(' ', '+', $image);

                // Save signature image
                $imageName = 'signatures/' . uniqid() . '.png';
                Storage::put('public/' . $imageName, base64_decode($image));

                $completionData['customer_signature'] = $imageName;
            }
        }

        // Handle photos
        $photos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $index => $file) {
                $path = $file->store('installation_photos', 'public');

                $description = $data['photo_descriptions'][$index] ?? null;

                $photos[] = [
                    'file_path' => $path,
                    'description' => $description,
                ];
            }
        }

        // Complete installation
        $installation = $this->installationRepository->completeInstallation($id, $completionData, $photos);

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'installation_completed',
            'installations',
            "Instalación completada para el contrato #{$installation->contract_id}",
            $request->ip(),
            $oldData,
            $installation->toArray()
        );

        return redirect()->route('contract.installations.show', $installation->id)
            ->with('success', 'Instalación completada correctamente.');
    }

    /**
     * Cancel an installation.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $installation = $this->installationRepository->find($id);

        // Check if installation can be cancelled
        if ($installation->status === 'completed' || $installation->status === 'cancelled') {
            return redirect()->route('contract.installations.show', $id)
                ->with('error', 'No se puede cancelar una instalación ya finalizada o cancelada.');
        }

        // Save old data for audit
        $oldData = $installation->toArray();

        // Cancel installation
        $installation->update([
            'status' => 'cancelled',
            'notes' => $request->reason
        ]);

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'installation_cancelled',
            'installations',
            "Instalación cancelada para el contrato #{$installation->contract_id}",
            $request->ip(),
            $oldData,
            $installation->toArray()
        );

        return redirect()->route('contract.installations.show', $installation->id)
            ->with('success', 'Instalación cancelada correctamente.');
    }

    /**
     * Display today's installations.
     * @return Renderable
     */
    public function today()
    {
        $installations = $this->installationRepository->getScheduledForToday();

        return view('contract::installations.today', compact('installations'));
    }

    /**
     * Display pending installations.
     * @return Renderable
     */
    public function pending()
    {
        $installations = $this->installationRepository->getPendingInstallations();

        return view('contract::installations.pending', compact('installations'));
    }

    /**
     * Display late installations.
     * @return Renderable
     */
    public function late()
    {
        $installations = $this->installationRepository->getLateInstallations();

        return view('contract::installations.late', compact('installations'));
    }

    /**
     * Add a photo to an installation.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function addPhoto(Request $request, $id)
    {
        $request->validate([
            'photo' => 'required|image|max:10240',
            'description' => 'nullable|string|max:255'
        ]);

        $installation = $this->installationRepository->find($id);

        // Store the photo
        $path = $request->file('photo')->store('installation_photos', 'public');

        // Create photo record
        $photo = $installation->photos()->create([
            'file_path' => $path,
            'description' => $request->description
        ]);

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'installation_photo_added',
            'installations',
            "Foto añadida a la instalación #{$installation->id}",
            $request->ip(),
            null,
            $photo->toArray()
        );

        return redirect()->route('contract.installations.show', $installation->id)
            ->with('success', 'Foto añadida correctamente.');
    }

    /**
     * Delete a photo from an installation.
     * @param int $id
     * @param int $photoId
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deletePhoto($id, $photoId, Request $request)
    {
        $installation = $this->installationRepository->find($id);
        $photo = $installation->photos()->findOrFail($photoId);

        // Save old data for audit
        $photoData = $photo->toArray();

        // Delete file from storage
        Storage::disk('public')->delete($photo->file_path);

        // Delete photo record
        $photo->delete();

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'installation_photo_deleted',
            'installations',
            "Foto eliminada de la instalación #{$installation->id}",
            $request->ip(),
            $photoData,
            null
        );

        return redirect()->route('contract.installations.show', $installation->id)
            ->with('success', 'Foto eliminada correctamente.');
    }
}
