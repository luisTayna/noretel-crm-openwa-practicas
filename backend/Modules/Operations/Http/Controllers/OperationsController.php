<?php

namespace Modules\Operations\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Contract\Entities\Contract;
use Modules\Core\Entities\AuditLog;
use Modules\Customer\Entities\Customer;
use Modules\Network\Entities\ServiceRequest;
use Modules\Operations\Entities\OnboardingOrder;
use Modules\Operations\Services\CustomerOperationFlowService;
use Modules\Services\Entities\Plan;

class OperationsController extends Controller
{
    public function __construct(
        protected CustomerOperationFlowService $flowService
    ) {
    }

    public function index()
    {
        return redirect()->route('operations.onboarding');
    }

    public function onboarding(Request $request)
    {
        $status = $request->get('status', 'all');
        $search = $request->get('search');
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $customerCollection = Customer::query()
            ->with([
                'onboardingOrders' => function ($query) {
                    $query->with(['plan', 'technician', 'installation.technician', 'activationRequest', 'contract.invoices.payments', 'invoice.payments'])->latest();
                },
                'contracts' => function ($query) {
                    $query->with(['plan', 'installations', 'invoices' => function ($invoicesQuery) {
                        $invoicesQuery->withAmountTotals()->with('payments');
                    }])->latest();
                },
                'addresses',
            ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('identity_document', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        $flows = $customerCollection
            ->mapWithKeys(fn (Customer $customer) => [
                $customer->id => $this->flowService->build($customer),
            ]);

        if ($status !== 'all') {
            $filteredIds = $flows
                ->filter(fn (array $flow) => $flow['status_key'] === $status)
                ->keys();

            $customerCollection = $customerCollection->whereIn('id', $filteredIds)->values();
            $flows = $flows->only($filteredIds);
        }

        $pagedCustomers = $customerCollection
            ->slice(($page - 1) * $perPage, $perPage)
            ->values();

        $customers = new LengthAwarePaginator(
            $pagedCustomers,
            $customerCollection->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $statusOptions = $this->flowService->statusOptions();
        $contractQueue = OnboardingOrder::where('status', OnboardingOrder::STATUS_CONTRACT_PENDING_30)
            ->with(['customer', 'plan', 'technician', 'installation.technician', 'activationRequest'])
            ->orderBy('expected_contract_date')
            ->limit(10)
            ->get();

        return view('operations::onboarding.index', compact(
            'customers',
            'flows',
            'contractQueue',
            'statusOptions',
            'status',
            'search'
        ));
    }

    public function createOrder(Request $request)
    {
        $customer = $request->filled('customer_id')
            ? Customer::with('addresses')->findOrFail($request->get('customer_id'))
            : null;

        $customers = Customer::query()->where('active', true)->orderBy('first_name')->get();
        $plans = Plan::query()->where('active', true)->orderBy('name')->get();
        return view('operations::orders.create', compact('customer', 'customers', 'plans'));
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'plan_id' => 'required|exists:plans,id',
            'expected_contract_date' => 'nullable|date',
            'final_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $order = OnboardingOrder::create([
            'customer_id' => $data['customer_id'],
            'plan_id' => $data['plan_id'],
            'technician_id' => null,
            'expected_contract_date' => $data['expected_contract_date'] ?? OnboardingOrder::nextContractDeliveryDate(),
            'final_price' => $data['final_price'] ?? $plan->price,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        AuditLog::register(
            auth()->id(),
            'onboarding_order_created',
            'operations',
            "Orden de instalación {$order->order_number} registrada",
            $request->ip(),
            null,
            $order->toArray()
        );

        return redirect()->route('operations.customers.flow', $order->customer_id)
            ->with('success', 'Orden de instalación registrada correctamente. Ahora programa la instalación.');
    }

    public function customerFlow(Customer $customer)
    {
        $customer->load([
            'addresses',
            'onboardingOrders' => function ($query) {
                $query->with(['plan', 'technician', 'installation.technician', 'activationRequest', 'contract.invoices.payments', 'invoice.payments'])->latest();
            },
            'contracts' => function ($query) {
                $query->with(['plan', 'installations', 'invoices' => function ($invoicesQuery) {
                    $invoicesQuery->withAmountTotals()->with('payments');
                }])->latest();
            },
        ]);

        $flow = $this->flowService->build($customer);
        $auditLogs = $this->flowService->auditLogsFor($customer, $flow['contract'], $flow['onboarding_order']);
        return view('operations::customers.flow', compact('customer', 'flow', 'auditLogs'));
    }

    public function requestActivation(Request $request, OnboardingOrder $order)
    {
        if ($this->equipmentActivationDisabled()) {
            return back()->with('info', 'La habilitación de equipo está deshabilitada por ahora. El flujo continúa desde instalación hacia contrato día 30.');
        }

        $order->load(['customer', 'installation', 'contract']);

        if (!$order->installation || $order->installation->status !== 'completed') {
            return back()->with('error', 'Primero debe completarse la instalación antes de solicitar habilitación.');
        }

        $data = $request->validate($this->activationRules(false));
        $activationData = $this->activationPayload($data);

        $serviceRequest = ServiceRequest::create([
            'customer_id' => $order->customer_id,
            'router_id' => $data['router_id'] ?? null,
            'contract_id' => $order->contract_id,
            'onboarding_order_id' => $order->id,
            'installation_id' => $order->installation_id,
            'type' => 'equipment_activation',
            'priority' => 'high',
            'status' => 'pending',
            'description' => 'Solicitud de habilitación de equipo para orden de instalación ' . $order->order_number,
            'technical_notes' => $data['technical_notes'] ?? null,
            'activation_data' => $activationData,
            'assigned_to' => auth()->id(),
            'assigned_at' => now(),
            'requires_visit' => false,
            'is_automated' => false,
        ]);

        $order->update([
            'activation_request_id' => $serviceRequest->id,
            'activation_data' => $activationData,
            'status' => OnboardingOrder::STATUS_ACTIVATION_PENDING,
        ]);

        AuditLog::register(
            auth()->id(),
            'equipment_activation_requested',
            'operations',
            "Solicitud de habilitación {$serviceRequest->ticket_number} creada para {$order->order_number}",
            $request->ip(),
            null,
            $serviceRequest->toArray()
        );

        return redirect()->route('operations.customers.flow', $order->customer_id)
            ->with('success', 'Solicitud de habilitación creada. Cuando red confirme, completa la habilitación.');
    }

    public function completeActivation(Request $request, OnboardingOrder $order)
    {
        if ($this->equipmentActivationDisabled()) {
            return back()->with('info', 'La habilitación de equipo está deshabilitada por ahora. El flujo continúa desde instalación hacia contrato día 30.');
        }

        $order->load(['customer', 'installation', 'activationRequest']);

        if (!$order->installation || $order->installation->status !== 'completed') {
            return back()->with('error', 'Primero debe completarse la instalación antes de habilitar el equipo.');
        }

        $data = $request->validate($this->activationRules(true));
        $activationData = $this->activationPayload($data);

        $serviceRequest = $order->activationRequest ?: ServiceRequest::create([
            'customer_id' => $order->customer_id,
            'router_id' => $data['router_id'] ?? null,
            'contract_id' => $order->contract_id,
            'onboarding_order_id' => $order->id,
            'installation_id' => $order->installation_id,
            'type' => 'equipment_activation',
            'priority' => 'high',
            'description' => 'Habilitación de equipo para orden de instalación ' . $order->order_number,
            'requires_visit' => false,
            'is_automated' => false,
        ]);

        $serviceRequest->update([
            'router_id' => $data['router_id'] ?? $serviceRequest->router_id,
            'status' => 'completed',
            'technical_notes' => $data['technical_notes'] ?? $serviceRequest->technical_notes,
            'resolution_notes' => $data['resolution_notes'],
            'activation_data' => $activationData,
            'started_at' => $serviceRequest->started_at ?: now(),
            'completed_at' => now(),
            'assigned_to' => $serviceRequest->assigned_to ?: auth()->id(),
            'assigned_at' => $serviceRequest->assigned_at ?: now(),
        ]);

        $order->update([
            'activation_request_id' => $serviceRequest->id,
            'activation_data' => $activationData,
            'activated_at' => now(),
            'status' => OnboardingOrder::STATUS_CONTRACT_PENDING_30,
        ]);

        AuditLog::register(
            auth()->id(),
            'equipment_activation_completed',
            'operations',
            "Equipo habilitado para {$order->order_number}; contrato queda pendiente para el día 30",
            $request->ip(),
            null,
            $order->fresh()->toArray()
        );

        return redirect()->route('operations.customers.flow', $order->customer_id)
            ->with('success', 'Equipo habilitado. La orden de instalación queda en cola para generar contrato el día 30.');
    }

    public function generateContract(Request $request, OnboardingOrder $order)
    {
        $order->load(['customer', 'plan', 'installation', 'activationRequest', 'contract']);

        if ($order->contract) {
            return redirect()->route('contract.contracts.show', $order->contract_id)
                ->with('info', 'Esta orden de instalación ya tiene contrato generado.');
        }

        $installationCompleted = $order->installation && $order->installation->status === 'completed';

        if ($order->status !== OnboardingOrder::STATUS_CONTRACT_PENDING_30 && !$installationCompleted) {
            return back()->with('error', 'La orden de instalación aún no está lista para generar contrato.');
        }

        if ($order->expected_contract_date && $order->expected_contract_date->isFuture()) {
            return back()->with('error', 'El contrato se genera desde la fecha programada: ' . $order->expected_contract_date->format('d/m/Y') . '.');
        }

        $contract = DB::transaction(function () use ($order, $request) {
            $activation = $order->activation_data ?? [];

            $contract = Contract::create([
                'customer_id' => $order->customer_id,
                'plan_id' => $order->plan_id,
                'start_date' => Carbon::parse($order->expected_contract_date ?: now())->startOfDay(),
                'end_date' => null,
                'status' => 'active',
                'final_price' => $order->final_price ?: $order->plan->price,
                'assigned_ip' => $activation['assigned_ip'] ?? null,
                'vlan' => $activation['vlan'] ?? null,
                'notes' => $order->notes,
            ]);

            if ($order->installation) {
                $order->installation->update(['contract_id' => $contract->id]);
            }

            if ($order->activationRequest) {
                $order->activationRequest->update(['contract_id' => $contract->id]);
            }

            $order->update([
                'contract_id' => $contract->id,
                'status' => OnboardingOrder::STATUS_CONTRACTED,
                'contracted_at' => now(),
            ]);

            AuditLog::register(
                auth()->id(),
                'contract_generated_from_onboarding_order',
                'contracts',
                "Contrato #{$contract->id} generado desde {$order->order_number}",
                $request->ip(),
                null,
                $contract->toArray()
            );

            return $contract;
        });

        return redirect()->route('operations.customers.flow', $order->customer_id)
            ->with('success', "Contrato #{$contract->id} generado. Ahora puedes emitir el recibo mensual.");
    }

    protected function activationRules(bool $completing): array
    {
        return [
            'router_id' => 'nullable|exists:routers,id',
            'olt_name' => 'required|string|max:120',
            'olt_serial' => 'nullable|string|max:120',
            'module' => 'nullable|string|max:80',
            'port' => 'nullable|string|max:80',
            'assigned_ip' => 'required|ip',
            'vlan' => 'required|string|max:50',
            'ont_serial' => 'nullable|string|max:120',
            'technical_notes' => 'nullable|string|max:1000',
            'resolution_notes' => $completing ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ];
    }

    protected function equipmentActivationDisabled(): bool
    {
        return true;
    }

    protected function activationPayload(array $data): array
    {
        return [
            'olt_name' => $data['olt_name'],
            'olt_serial' => $data['olt_serial'] ?? null,
            'router_id' => $data['router_id'] ?? null,
            'module' => $data['module'] ?? null,
            'port' => $data['port'] ?? null,
            'assigned_ip' => $data['assigned_ip'],
            'vlan' => $data['vlan'],
            'ont_serial' => $data['ont_serial'] ?? null,
            'technical_notes' => $data['technical_notes'] ?? null,
            'registered_by_user_id' => auth()->id(),
            'registered_at' => now()->toDateTimeString(),
        ];
    }
}
