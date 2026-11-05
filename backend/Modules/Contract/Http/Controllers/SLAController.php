<?php

namespace Modules\Contract\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contract\Repositories\SLARepository;
use Modules\Core\Entities\AuditLog;
use Modules\Contract\Http\Requests\StoreSLARequest;
use Modules\Contract\Http\Requests\UpdateSLARequest;
use Illuminate\Support\Facades\Auth;

class SLAController extends Controller
{
    /**
     * @var SLARepository
     */
    protected $slaRepository;

    /**
     * SLAController constructor.
     *
     * @param SLARepository $slaRepository
     */
    public function __construct(SLARepository $slaRepository)
    {
        $this->slaRepository = $slaRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $serviceLevel = $request->get('service_level');
        $perPage = $request->get('per_page', 10);

        $query = $this->slaRepository->query();

        // Apply filters
        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('service_level', 'like', "%{$search}%");
        }

        if ($serviceLevel) {
            $query->where('service_level', $serviceLevel);
        }

        // With contract count
        $query->withCount('contracts');
        
        // Order by name
        $query->orderBy('name');

        $slas = $query->paginate($perPage);

        // Get SLA usage statistics
        $slaUsageStats = $this->slaRepository->getSLAUsageStats();

        return view('contract::slas.index', compact(
            'slas', 
            'search', 
            'serviceLevel',
            'slaUsageStats'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('contract::slas.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreSLARequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSLARequest $request)
    {
        $data = $request->validated();
        
        // Handle penalties as JSON
        if (isset($data['penalties']) && is_array($data['penalties'])) {
            $data['penalties'] = json_encode($data['penalties']);
        }
        
        // Create SLA
        $sla = $this->slaRepository->create($data);
        
        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'sla_created',
            'slas',
            "SLA creado: {$sla->name}",
            $request->ip(),
            null,
            $sla->toArray()
        );
        
        return redirect()->route('contract.slas.index')
            ->with('success', 'SLA creado correctamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $sla = $this->slaRepository->find($id);
        
        // Get contracts using this SLA with pagination
        $contracts = $sla->contracts()->with(['customer'])->paginate(10);
        
        return view('contract::slas.show', compact('sla', 'contracts'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $sla = $this->slaRepository->find($id);
        
        return view('contract::slas.edit', compact('sla'));
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateSLARequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateSLARequest $request, $id)
    {
        $sla = $this->slaRepository->find($id);
        $data = $request->validated();
        
        // Save old data for audit
        $oldData = $sla->toArray();
        
        // Handle penalties as JSON
        if (isset($data['penalties']) && is_array($data['penalties'])) {
            $data['penalties'] = json_encode($data['penalties']);
        }
        
        // Update SLA
        $this->slaRepository->update($id, $data);
        
        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'sla_updated',
            'slas',
            "SLA actualizado: {$sla->name}",
            $request->ip(),
            $oldData,
            $sla->fresh()->toArray()
        );
        
        return redirect()->route('contract.slas.index')
            ->with('success', 'SLA actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $sla = $this->slaRepository->find($id);
        
        // Check if SLA is being used in contracts
        if ($sla->contracts()->count() > 0) {
            return redirect()->route('contract.slas.index')
                ->with('error', 'No se puede eliminar un SLA que está siendo utilizado en contratos.');
        }
        
        // Save old data for audit
        $slaData = $sla->toArray();
        
        // Delete SLA
        $this->slaRepository->delete($id);
        
        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'sla_deleted',
            'slas',
            "SLA eliminado: {$sla->name}",
            $request->ip(),
            $slaData,
            null
        );
        
        return redirect()->route('contract.slas.index')
            ->with('success', 'SLA eliminado correctamente.');
    }

    /**
     * Get SLAs suitable for a specific plan type.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSuitableForPlanType(Request $request)
    {
        $request->validate([
            'plan_type' => 'required|string|in:business,residential'
        ]);
        
        $slas = $this->slaRepository->getSuitableForPlanType($request->plan_type);
        
        return response()->json([
            'success' => true,
            'data' => $slas
        ]);
    }
}