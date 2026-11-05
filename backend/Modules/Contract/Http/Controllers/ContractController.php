<?php

namespace Modules\Contract\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Contract\Repositories\ContractRepository;
use Modules\Contract\Repositories\SLARepository;
use Modules\Core\Entities\AuditLog;
use Modules\Customer\Repositories\CustomerRepository;
use Modules\Services\Repositories\PlanRepository;
use Modules\Services\Repositories\AdditionalServiceRepository;
use Modules\Contract\Repositories\NodeRepository;
use Modules\Contract\Http\Requests\StoreContractRequest;
use Modules\Contract\Http\Requests\UpdateContractRequest;
use Modules\Contract\Http\Requests\RenewContractRequest;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractController extends Controller
{
    /**
     * @var ContractRepository
     */
    protected $contractRepository;

    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var PlanRepository
     */
    protected $planRepository;

    /**
     * @var AdditionalServiceRepository
     */
    protected $additionalServiceRepository;

    /**
     * @var NodeRepository
     */
    protected $nodeRepository;

    /**
     * @var SLARepository
     */
    protected $slaRepository;

    /**
     * ContractController constructor.
     *
     * @param ContractRepository $contractRepository
     * @param CustomerRepository $customerRepository
     * @param PlanRepository $planRepository
     * @param AdditionalServiceRepository $additionalServiceRepository
     * @param NodeRepository $nodeRepository
     * @param SLARepository $slaRepository
     */
    public function __construct(
        ContractRepository $contractRepository,
        CustomerRepository $customerRepository,
        PlanRepository $planRepository,
        AdditionalServiceRepository $additionalServiceRepository,
        NodeRepository $nodeRepository,
        SLARepository $slaRepository
    ) {
        $this->contractRepository = $contractRepository;
        $this->customerRepository = $customerRepository;
        $this->planRepository = $planRepository;
        $this->additionalServiceRepository = $additionalServiceRepository;
        $this->nodeRepository = $nodeRepository;
        $this->slaRepository = $slaRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $customerId = $request->get('customer_id');
        $perPage = $request->get('per_page', 10);

        $query = $this->contractRepository->query();

        // Apply filters
        if ($search) {
            $query->whereHas('customer', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('identity_document', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // With relationships
        $query->with(['customer', 'plan']);

        // Order by creation date
        $query->orderBy('created_at', 'desc');

        $contracts = $query->paginate($perPage);

        // Get statistics for dashboard widgets
        $contractsByStatus = $this->contractRepository->countByStatus();
        $nearExpirationCount = $this->contractRepository->getNearExpiration()->count();
        $expiredCount = $this->contractRepository->getExpired()->count();

        return view('contract::contracts.index', compact(
            'contracts',
            'search',
            'status',
            'customerId',
            'contractsByStatus',
            'nearExpirationCount',
            'expiredCount'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * @param Request $request
     * @return Renderable
     */
    public function create(Request $request)
    {
        $customerId = $request->get('customer_id');
        $customer = null;

        if ($customerId) {
            $customer = $this->customerRepository->find($customerId);
        }

        $customers = $this->customerRepository->getActiveCustomers();
        $plans = $this->planRepository->getActivePlans();
        $additionalServices = $this->additionalServiceRepository->getActiveServices();
        $nodes = $this->nodeRepository->getActiveNodes();
        $slas = $this->slaRepository->getActiveSLAs();

        return view('contract::contracts.create', compact(
            'customers',
            'customer',
            'plans',
            'additionalServices',
            'nodes',
            'slas'
        ));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreContractRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreContractRequest $request)
    {
        $data = $request->validated();

        // Prepare contract data
        $contractData = [
            'customer_id' => $data['customer_id'],
            'plan_id' => $data['plan_id'],
            'node_id' => $data['node_id'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => 'pending_installation',
            'final_price' => $data['final_price'],
            'assigned_ip' => $data['assigned_ip'] ?? null,
            'vlan' => $data['vlan'] ?? null,
        ];

        // Prepare contracted services data
        $contractedServices = [];
        if (isset($data['additional_services']) && is_array($data['additional_services'])) {
            foreach ($data['additional_services'] as $serviceId => $serviceData) {
                if (isset($serviceData['selected']) && $serviceData['selected']) {
                    $contractedServices[] = [
                        'additional_service_id' => $serviceId,
                        'price' => $serviceData['price'] ?? 0,
                        'configuration' => $serviceData['configuration'] ?? null,
                    ];
                }
            }
        }

        // Create contract with services
        $contract = $this->contractRepository->createWithServices($contractData, $contractedServices);

        // Create SLA if provided
        if (isset($data['sla_id']) && $data['sla_id']) {
            $contract->slas()->attach($data['sla_id']);
        }

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'contract_created',
            'contracts',
            "Contrato creado para cliente: {$contract->customer->first_name} {$contract->customer->last_name}",
            $request->ip(),
            null,
            $contract->toArray()
        );

        return redirect()->route('contract.contracts.show', $contract->id)
            ->with('success', 'Contrato creado correctamente. Ahora debe programar la instalación.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $contract = $this->contractRepository->getWithRelations($id);

        // Get installations for this contract
        $installations = $contract->installations()->with(['technician', 'route'])->get();

        // Get invoices for this contract
        $invoices = $contract->invoices()->with(['payments'])->get();

        return view('contract::contracts.show', compact('contract', 'installations', 'invoices'));
    }

    /**
     * Download the specified contract as a formal PDF document.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function pdf($id)
    {
        $contract = $this->contractRepository->getWithRelations($id);
        $contract->loadMissing([
            'customer.addresses',
            'plan.service',
            'contractedServices.additionalService',
            'slas',
            'installations.technician',
        ]);

        $pdf = Pdf::loadView('contract::contracts.pdf', compact('contract'))
            ->setPaper('a4');

        return $pdf->stream("contrato-{$contract->id}.pdf");
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $contract = $this->contractRepository->getWithRelations($id);

        $customers = $this->customerRepository->getActiveCustomers();
        $plans = $this->planRepository->getActivePlans();
        $additionalServices = $this->additionalServiceRepository->getActiveServices();
        $nodes = $this->nodeRepository->getActiveNodes();
        $slas = $this->slaRepository->getActiveSLAs();

        // Get current contracted services for pre-selection
        $currentServices = [];
        foreach ($contract->contractedServices as $service) {
            $currentServices[$service->additional_service_id] = [
                'selected' => true,
                'price' => $service->price,
                'configuration' => $service->configuration,
            ];
        }

        return view('contract::contracts.edit', compact(
            'contract',
            'customers',
            'plans',
            'additionalServices',
            'nodes',
            'slas',
            'currentServices'
        ));
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateContractRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateContractRequest $request, $id)
    {
        $contract = $this->contractRepository->find($id);
        $data = $request->validated();

        // Save old data for audit
        $oldData = $contract->toArray();

        // Prepare contract data
        $contractData = [
            'customer_id' => $data['customer_id'],
            'plan_id' => $data['plan_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? $contract->status,
            'final_price' => $data['final_price'],
            'assigned_ip' => $data['assigned_ip'] ?? null,
            'vlan' => $data['vlan'] ?? null,
        ];

        // Only touch node_id when the request actually carries the field, so an
        // absent key (e.g. a payload built before this field existed) never wipes
        // an already-assigned node. An explicit empty/null value still clears it.
        if (array_key_exists('node_id', $data)) {
            $contractData['node_id'] = $data['node_id'];
        }

        // Prepare contracted services data
        $contractedServices = [];
        if (isset($data['additional_services']) && is_array($data['additional_services'])) {
            foreach ($data['additional_services'] as $serviceId => $serviceData) {
                if (isset($serviceData['selected']) && $serviceData['selected']) {
                    $contractedServices[] = [
                        'additional_service_id' => $serviceId,
                        'price' => $serviceData['price'] ?? 0,
                        'configuration' => $serviceData['configuration'] ?? null,
                    ];
                }
            }
        }

        // Update contract with services
        $contract = $this->contractRepository->updateWithServices($id, $contractData, $contractedServices);

        // Update SLA if provided
        if (isset($data['sla_id'])) {
            $contract->sla()->delete();
            if ($data['sla_id']) {
                $contract->sla()->create([
                    'sla_id' => $data['sla_id']
                ]);
            }
        }

        // Actualizar facturas pendientes si se requiere
        if ($request->boolean('update_pending_invoices')) {
            $newPrice = (float) $contract->final_price;
            
            $pendingInvoices = \Modules\Billing\Entities\Invoice::where('contract_id', $contract->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->get();
                
            foreach ($pendingInvoices as $invoice) {
                $amountAlreadyPaid = (float) ($invoice->total_amount - $invoice->pending_amount);
                $newPending = round(max($newPrice - $amountAlreadyPaid, 0), 2);
                
                $newStatus = $invoice->status;
                if ($newPending <= 0.009) {
                    $newStatus = 'paid';
                } elseif ($amountAlreadyPaid > 0.009) {
                    $newStatus = 'partial';
                } else {
                    $newStatus = now()->gt(\Carbon\Carbon::parse($invoice->due_date)) ? 'overdue' : 'pending';
                }

                $invoice->update([
                    'amount' => $newPrice,
                    'total_amount' => $newPrice,
                    'pending_amount' => $newPending,
                    'status' => $newStatus
                ]);
                
                // Actualizar los items de la factura
                foreach ($invoice->items as $item) {
                    $item->update([
                        'unit_price' => $newPrice,
                        'amount' => $newPrice
                    ]);
                }
            }
        }

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'contract_updated',
            'contracts',
            "Contrato actualizado para cliente: {$contract->customer->first_name} {$contract->customer->last_name}",
            $request->ip(),
            $oldData,
            $contract->toArray()
        );

        $successMessage = 'Contrato actualizado correctamente.';
        if ($request->boolean('update_pending_invoices')) {
            $successMessage .= ' Se actualizaron también sus facturas pendientes.';
        }

        return redirect()->route('contract.contracts.show', $contract->id)
            ->with('success', $successMessage);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $contract = $this->contractRepository->find($id);

        // Check if contract has installations or invoices
        if (!$contract->canBeDeleted()) {
            return redirect()->route('contract.contracts.index')
                ->with('error', 'No se puede eliminar un contrato que tiene instalaciones o facturas asociadas.');
        }

        // Save old data for audit
        $contractData = $contract->toArray();

        // Delete contract
        $this->contractRepository->delete($id);

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'contract_deleted',
            'contracts',
            "Contrato eliminado para cliente: {$contract->customer->first_name} {$contract->customer->last_name}",
            $request->ip(),
            $contractData,
            null
        );

        return redirect()->route('contract.contracts.index')
            ->with('success', 'Contrato eliminado correctamente.');
    }

    /**
     * Show the form for renewing a contract.
     * @param int $id
     * @return Renderable
     */
    public function showRenewForm($id)
    {
        $contract = $this->contractRepository->getWithRelations($id);

        // Check if contract can be renewed
        if (!$contract->canBeRenewed()) {
            return redirect()->route('contract.contracts.show', $id)
                ->with('error', 'Este contrato no puede ser renovado en este momento.');
        }

        $plans = $this->planRepository->getActivePlans();

        return view('contract::contracts.renew', compact('contract', 'plans'));
    }

    /**
     * Renew a contract.
     * @param RenewContractRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function renew(RenewContractRequest $request, $id)
    {
        $contract = $this->contractRepository->find($id);
        $data = $request->validated();

        // Save old data for audit
        $oldData = $contract->toArray();

        // Prepare renewal data
        $renewalData = [
            'start_date' => $data['start_date'] ?? now(),
            'end_date' => $data['end_date'] ?? null,
            'final_price' => $data['final_price'] ?? $contract->final_price,
            'plan_id' => $data['plan_id'] ?? $contract->plan_id,
        ];

        // Renew contract
        $contract = $this->contractRepository->renew($id, $renewalData);

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'contract_renewed',
            'contracts',
            "Contrato renovado para cliente: {$contract->customer->first_name} {$contract->customer->last_name}",
            $request->ip(),
            $oldData,
            $contract->toArray()
        );

        return redirect()->route('contract.contracts.show', $contract->id)
            ->with('success', 'Contrato renovado correctamente.');
    }

    /**
     * Cancel a contract.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $contract = $this->contractRepository->find($id);

        if (!$contract->canBeCancelled()) {
            return redirect()->route('contract.contracts.show', $contract->id)
                ->with('error', 'Este contrato no puede ser anulado en su estado actual.');
        }

        // Save old data for audit
        $oldData = $contract->toArray();

        // Cancel contract
        $contract = $this->contractRepository->cancel($id, $request->reason);

        // Register action for audit log
        AuditLog::register(
            Auth::id(),
            'contract_cancelled',
            'contracts',
            "Contrato cancelado para cliente: {$contract->customer->first_name} {$contract->customer->last_name}",
            $request->ip(),
            $oldData,
            $contract->toArray()
        );

        return redirect()->route('contract.contracts.show', $contract->id)
            ->with('success', 'Contrato cancelado correctamente.');
    }

    /**
     * Display contracts near expiration.
     * @return Renderable
     */
    public function nearExpiration()
    {
        $contracts = $this->contractRepository->getNearExpiration();

        return view('contract::contracts.near-expiration', compact('contracts'));
    }

    /**
     * Display expired contracts.
     * @return Renderable
     */
    public function expired()
    {
        $contracts = $this->contractRepository->getExpired();

        return view('contract::contracts.expired', compact('contracts'));
    }

    /**
     * Display dashboard with contract statistics.
     * @return Renderable
     */
    public function dashboard()
    {
        // Get statistics for dashboard
        $contractsByStatus = $this->contractRepository->countByStatus();
        $nearExpirationContracts = $this->contractRepository->getNearExpiration();
        $expiredContracts = $this->contractRepository->getExpired();
        $monthlyContractsData = $this->contractRepository->getMonthlyContractsCount();

        // Get recent contracts
        $recentContracts = $this->contractRepository->query()
            ->with(['customer', 'plan'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('contract::dashboard', compact(
            'contractsByStatus',
            'nearExpirationContracts',
            'expiredContracts',
            'monthlyContractsData',
            'recentContracts'
        ));
    }

    /**
     * Search contracts for Select2 (AJAX)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchForSelect2(Request $request)
    {
        $term = $request->get('q');
        $contracts = $this->contractRepository->searchForSelect2($term);

        $results = [];
        foreach ($contracts as $contract) {
            $results[] = [
                'id' => $contract->id,
                'text' => "#{$contract->id} - {$contract->customer->full_name} ({$contract->customer->identity_document})",
                'customer' => $contract->customer,
                'plan' => $contract->plan,
                'contracted_services' => $contract->contractedServices->map(function($cs) {
                    return [
                        'id' => $cs->additional_service_id,
                        'name' => $cs->additionalService ? $cs->additionalService->name : 'Servicio Adicional',
                        'price' => $cs->price
                    ];
                })
            ];
        }

        return response()->json([
            'results' => $results
        ]);
    }
}
