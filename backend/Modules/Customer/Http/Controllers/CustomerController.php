<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\CustomerService;
use Modules\Customer\Http\Requests\StoreCustomerRequest;
use Modules\Customer\Http\Requests\UpdateCustomerRequest;
use Modules\Customer\Entities\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Modules\Network\Services\RouterService;
use Modules\Network\Entities\Router;
use Modules\Billing\Services\CustomerCreditService;

class CustomerController extends Controller
{
    /**
     * @var CustomerService
     */
    protected $customerService;

    /**
     * CustomerController constructor.
     *
     * @param CustomerService $customerService
     */
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'type',
            'segment',
            'active',
            'customer_active',
            'contract_status',
            'date_from',
            'date_to',
            'plan',
            'zone',
            'status',
            'incomplete'
        ]);

        $perPage = $request->get('per_page', 15);

        $customers = $this->customerService->searchCustomers($filters, $perPage);
        $stats = $this->customerService->getDashboardStats();

        // Cargar listas para los filtros
        $plans = DB::table('plans')->where('active', 1)->select('id', 'name')->get();
        $zones = Schema::hasTable('coverage_zones')
            ? DB::table('coverage_zones')->select('id', 'name')->get()
            : collect();

        return view('customer::customers.index', compact('customers', 'filters', 'stats', 'plans', 'zones'));
    }

    public function searchSuggestions(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['results' => []]);
        }

        $results = $this->customerService
            ->searchSuggestions($search)
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => trim($customer->first_name.' '.$customer->last_name),
                'document' => $customer->identity_document,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'initials' => mb_strtoupper(
                    mb_substr((string) $customer->first_name, 0, 1)
                    .mb_substr((string) $customer->last_name, 0, 1)
                ),
                'url' => route('customer.customers.show', $customer->id),
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $customerTypes = [
            'individual' => 'Individual',
            'business' => 'Empresa'
        ];

        $segments = [
            'residential' => 'Residencial',
            'business' => 'Empresarial',
            'corporate' => 'Corporativo',
            'public' => 'Sector Público'
        ];

        return view('customer::customers.create', compact('customerTypes', 'segments'));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreCustomerRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreCustomerRequest $request)
    {
        $customerData = $request->except(['addresses', 'emergency_contacts']);
        $addressesData = $request->input('addresses', []);

        $result = $this->customerService->createCustomer(
            $customerData,
            $addressesData,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }

        return redirect()->route('customer.customers.show', $result['customer']->id)
            ->with('success', 'Cliente creado exitosamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $customer = Customer::with([
                'addresses',
                'emergencyContacts',
                'documents.documentType',
                'interactions.createdBy',
                'contracts.plan',
                'contracts.node',
                'contracts.contractedServices.additionalService',
                'contracts.installations',
                'contracts.invoices.payments',
                'contracts.invoices.creditNotes',
                'contracts.invoices.reminders',
                'onboardingOrders',
                'yapePaymentIntents',
            ])
            ->withCount('contracts')
            ->findOrFail($id);

        $contracts = $customer->contracts->sortByDesc('start_date')->values();
        $activeContracts = $contracts->where('status', 'active')->values();
        $activeContract = $activeContracts->first() ?: $contracts->first();

        $invoices = $contracts
            ->flatMap(fn ($contract) => $contract->invoices->map(function ($invoice) use ($contract) {
                $invoice->setRelation('contract', $contract);
                return $invoice;
            }))
            ->sortByDesc('due_date')
            ->values();

        $payments = $invoices
            ->flatMap(fn ($invoice) => $invoice->payments->map(function ($payment) use ($invoice) {
                $payment->setRelation('invoice', $invoice);
                return $payment;
            }))
            ->sortByDesc('payment_date')
            ->values();

        $activeInvoices = $invoices->whereNotIn('status', ['void', 'cancelled'])->values();
        $voidInvoices = $invoices->whereIn('status', ['void', 'cancelled'])->values();

        $pendingInvoices = $invoices
            ->filter(fn ($invoice) => in_array($invoice->status, ['pending', 'partial', 'overdue'], true) && (float) $invoice->pending_amount > 0)
            ->sortBy('due_date')
            ->values();

        $overdueInvoices = $pendingInvoices
            ->filter(fn ($invoice) => $invoice->due_date && Carbon::parse($invoice->due_date)->isPast())
            ->values();

        $today = now()->startOfDay();
        $completedPayments = $payments->where('status', 'completed');
        $lastPayment = $payments->first();
        $nextDueInvoice = $pendingInvoices->first(function ($invoice) use ($today) {
            return $invoice->due_date
                && Carbon::parse($invoice->due_date)->startOfDay()->greaterThanOrEqualTo($today);
        }) ?: $pendingInvoices->first();

        $billingSummary = [
            'paid_total' => (float) $completedPayments->sum('amount'),
            'pending_total' => (float) $pendingInvoices->sum(fn ($invoice) => (float) $invoice->pending_amount),
            'overdue_total' => (float) $overdueInvoices->sum(fn ($invoice) => (float) $invoice->pending_amount),
            'credit_balance_total' => (float) app(CustomerCreditService::class)->getBalance($customer->id),
            'credit_balance_active_contract' => $activeContract
                ? (float) app(CustomerCreditService::class)->getBalance($customer->id, $activeContract->id)
                : 0.0,
            'invoice_count' => $invoices->count(),
            'active_invoice_count' => $activeInvoices->count(),
            'void_invoice_count' => $voidInvoices->count(),
            'active_contract_count' => $activeContracts->count(),
            'registered_contract_count' => $contracts->count(),
            'historical_contract_count' => $contracts->count() - $activeContracts->count(),
            'pending_count' => $pendingInvoices->count(),
            'overdue_count' => $overdueInvoices->count(),
            'last_payment' => $lastPayment,
            'next_due_invoice' => $nextDueInvoice,
        ];

        $months = collect(range(5, 0))->map(fn ($monthsAgo) => now()->startOfMonth()->subMonths($monthsAgo));
        $paymentChart = [
            'labels' => $months->map(fn ($date) => ucfirst($date->locale('es')->translatedFormat('F Y')))->values(),
            'values' => $months->map(function ($date) use ($completedPayments) {
                return (float) $completedPayments
                    ->filter(fn ($payment) => $payment->payment_date && Carbon::parse($payment->payment_date)->format('Y-m') === $date->format('Y-m'))
                    ->sum('amount');
            })->values(),
        ];

        $invoiceStatusLabels = [
            'paid' => 'Pagadas',
            'pending' => 'Pendientes',
            'partial' => 'Parciales',
            'overdue' => 'Vencidas',
            'void' => 'Anuladas',
            'cancelled' => 'Canceladas',
        ];

        $invoiceStatusChart = [
            'labels' => collect($invoiceStatusLabels)->values(),
            'values' => collect($invoiceStatusLabels)->keys()->map(fn ($status) => $invoices->where('status', $status)->count())->values(),
        ];

        $paymentMethodsSummary = $completedPayments
            ->groupBy('payment_method')
            ->map(fn ($items, $method) => [
                'method' => $method,
                'label' => config("billing.payment.methods.{$method}", ucfirst(str_replace('_', ' ', $method ?: 'unknown'))),
                'amount' => (float) $items->sum('amount'),
                'count' => $items->count(),
            ])
            ->sortByDesc('amount')
            ->values();

        $routerAssignments = collect();
        if (Schema::hasTable('router_customer') && Schema::hasTable('routers')) {
            $routerAssignments = DB::table('router_customer')
                ->join('routers', 'routers.id', '=', 'router_customer.router_id')
                ->where('router_customer.customer_id', $customer->id)
                ->select([
                    'routers.name as router_name',
                    'routers.ip_address as router_ip',
                    'router_customer.contract_id',
                    'router_customer.port',
                    'router_customer.vlan',
                    'router_customer.assigned_ip',
                    'router_customer.pppoe_username',
                    'router_customer.connection_status',
                    'router_customer.bandwidth_limit_down',
                    'router_customer.bandwidth_limit_up',
                ])
                ->get();
        }

        $recentInvoices = $invoices->take(6);
        $recentPayments = $payments->take(6);

        $timeline = collect()
            ->merge($recentPayments->map(fn ($payment) => [
                'date' => $payment->payment_date,
                'icon' => 'fa-money-bill-wave',
                'color' => 'success',
                'title' => 'Pago registrado',
                'paid_period' => $payment->invoice?->billing_period,
                'description' => 'S/ ' . number_format((float) $payment->amount, 2) . ' vía ' . $payment->payment_method_name,
            ]))
            ->merge($recentInvoices->map(fn ($invoice) => [
                'date' => $invoice->issue_date,
                'icon' => 'fa-file-invoice',
                'color' => $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'danger' : 'warning'),
                'title' => 'Factura ' . ($invoice->invoice_number ?: '#' . $invoice->id),
                'description' => 'Total S/ ' . number_format((float) $invoice->total_amount, 2) . ' · ' . ucfirst($invoice->status),
            ]))
            ->merge($customer->interactions->sortByDesc('date')->take(5)->map(fn ($interaction) => [
                'date' => $interaction->date,
                'icon' => 'fa-comments',
                'color' => 'info',
                'title' => 'Interacción',
                'description' => trim(($interaction->interaction_type ?: 'Sin tipo') . ' · ' . ($interaction->channel ?: 'Sin canal')),
            ]))
            ->filter(fn ($item) => !empty($item['date']))
            ->sortByDesc('date')
            ->take(10)
            ->values();

        return view('customer::customers.show', compact(
            'customer',
            'contracts',
            'activeContracts',
            'activeContract',
            'invoices',
            'pendingInvoices',
            'overdueInvoices',
            'recentInvoices',
            'payments',
            'recentPayments',
            'billingSummary',
            'paymentChart',
            'invoiceStatusChart',
            'paymentMethodsSummary',
            'routerAssignments',
            'timeline'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $customer = Customer::with(['addresses', 'emergencyContacts'])
            ->findOrFail($id);

        $customerTypes = [
            'individual' => 'Individual',
            'business' => 'Empresa'
        ];

        $segments = [
            'residential' => 'Residencial',
            'business' => 'Empresarial',
            'corporate' => 'Corporativo',
            'public' => 'Sector Público'
        ];

        return view('customer::customers.edit', compact('customer', 'customerTypes', 'segments'));
    }

    /**
     * Update the specified resource in storage.
     * @param UpdateCustomerRequest $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateCustomerRequest $request, $id)
    {
        $customerData = $request->except(['addresses', 'emergency_contacts']);
        $addressesData = $request->input('addresses', []);

        $result = $this->customerService->updateCustomer(
            $id,
            $customerData,
            $addressesData,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }

        return redirect()->route('customer.customers.show', $id)
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $result = $this->customerService->deleteCustomer(
            $id,
            Auth::id(),
            $request->ip(),
            $request->boolean('delete_deletable_contracts'),
            $request->boolean('force_delete_related_history')
        );

        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }

        return redirect()->route('customer.customers.index')
            ->with('success', $result['message']);
    }

    /**
     * Activate a customer.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function activate($id, Request $request)
    {
        $result = $this->customerService->changeStatus(
            $id,
            true,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }

        return redirect()->back()
            ->with('success', $result['message']);
    }

    /**
     * Deactivate a customer.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deactivate($id, Request $request)
    {
        $result = $this->customerService->changeStatus(
            $id,
            false,
            Auth::id(),
            $request->ip()
        );

        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }

        return redirect()->back()
            ->with('success', $result['message']);
    }

    // ========================================================================
    // MÉTODOS API PARA CLIENTES (Frontend Vue)
    // ========================================================================

    /**
     * API: Dashboard del cliente
     */
    public function apiDashboard(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $customer = Customer::where('user_id', $user->id)->first();

        if (!$customer) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $contracts = $customer->contracts()
            ->where('status', 'active')
            ->with('plan')
            ->orderBy('id')
            ->get();

        if ($contracts->isEmpty()) {
            return response()->json([
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'service' => null,
                'services' => [],
                'message' => 'No tiene servicio activo'
            ]);
        }

        $services = $contracts->map(fn ($contract) => [
            'contract_id' => $contract->id,
            'plan_name' => $contract->plan?->name,
            'download_speed' => $contract->plan?->download_speed,
            'upload_speed' => $contract->plan?->upload_speed,
            'price' => $contract->final_price ?? $contract->plan?->price,
            'status' => $contract->status,
            'next_payment' => $contract->next_billing_date,
        ])->values();

        return response()->json([
            'success' => true,
            'customer' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            // Keep service for backwards compatibility with the current app.
            'service' => $services->first(),
            'services' => $services,
        ]);
    }

    /**
     * API: Reiniciar línea del cliente
     */
    public function apiRestartLine(Request $request)
    {
        $request->validate([
            'contract_id' => 'nullable|integer|exists:contracts,id',
        ]);
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $customer = Customer::where('user_id', $user->id)->first();

        if (!$customer) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $assignments = \DB::table('router_customer')
            ->where('customer_id', $customer->id)
            ->where('connection_status', 'active')
            ->when($request->filled('contract_id'), fn ($query) => $query->where('contract_id', $request->integer('contract_id')))
            ->get();

        if (! $request->filled('contract_id') && $assignments->count() > 1) {
            return response()->json([
                'error' => 'Seleccione el servicio que desea reiniciar.',
                'services' => $assignments->map(fn ($assignment) => [
                    'contract_id' => $assignment->contract_id,
                    'assigned_ip' => $assignment->assigned_ip,
                    'pppoe_username' => $assignment->pppoe_username,
                ])->values(),
            ], 422);
        }

        $routerCustomer = $assignments->first();

        if (!$routerCustomer) {
            return response()->json(['error' => 'No tiene router asignado'], 404);
        }

        $router = Router::find($routerCustomer->router_id);
        $username = $routerCustomer->pppoe_username;

        try {
            $routerService = app(RouterService::class);

            // Desconectar y reconectar
            $routerService->suspendClient($router, $username);
            sleep(2);
            $routerService->activateClient($router, $username);

            return response()->json([
                'success' => true,
                'message' => 'Línea reiniciada correctamente. Espere 1-2 minutos.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al reiniciar línea del cliente', [
                'action' => 'customer.restartLine',
                'customer_id' => $customer->id,
                'router_id' => $routerCustomer->router_id,
                'username' => $username,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo reiniciar la línea. Inténtelo nuevamente o contacte al administrador.'
            ], 500);
        }
    }

    /**
     * Dashboard de Inteligencia de Negocios y Clasificación de Clientes
     */
    public function biDashboard(Request $request)
    {
        $segmentFilter = $request->input('segment');

        $query = Customer::query()->with(['contracts.plan', 'invoices', 'addresses']);

        if ($segmentFilter) {
            $query->where('bi_segment', $segmentFilter);
        }

        $customers = $query->paginate(20)->withQueryString();

        $segmentCounts = [
            'vip' => Customer::where('bi_segment', 'vip')->count(),
            'regular' => Customer::where('bi_segment', 'regular')->count(),
            'churn_risk' => Customer::where('bi_segment', 'churn_risk')->count(),
            'new' => Customer::where('bi_segment', 'new')->count(),
            'unclassified' => Customer::whereNull('bi_segment')->count(),
        ];

        $totalCustomers = Customer::count();

        $revenuePerSegment = [
            'vip' => \DB::table('customers')
                ->join('contracts', 'customers.id', '=', 'contracts.customer_id')
                ->join('plans', 'contracts.plan_id', '=', 'plans.id')
                ->where('customers.bi_segment', 'vip')
                ->where('contracts.status', 'active')
                ->sum('plans.price'),

            'regular' => \DB::table('customers')
                ->join('contracts', 'customers.id', '=', 'contracts.customer_id')
                ->join('plans', 'contracts.plan_id', '=', 'plans.id')
                ->where('customers.bi_segment', 'regular')
                ->where('contracts.status', 'active')
                ->sum('plans.price'),

            'churn_risk' => \DB::table('customers')
                ->join('contracts', 'customers.id', '=', 'contracts.customer_id')
                ->join('plans', 'contracts.plan_id', '=', 'plans.id')
                ->where('customers.bi_segment', 'churn_risk')
                ->where('contracts.status', 'active')
                ->sum('plans.price'),

            'new' => \DB::table('customers')
                ->join('contracts', 'customers.id', '=', 'contracts.customer_id')
                ->join('plans', 'contracts.plan_id', '=', 'plans.id')
                ->where('customers.bi_segment', 'new')
                ->where('contracts.status', 'active')
                ->sum('plans.price'),
        ];

        // Alertas Tempranas BI
        $criticalVipAlerts = Customer::where('bi_segment', 'vip')
            ->whereHas('invoices', function ($q) {
                $q->whereIn('invoices.status', ['overdue', 'pending']);
            })
            ->with(['contracts.plan'])
            ->get();

        $now = \Carbon\Carbon::now();
        $newClientMilestones = Customer::where('bi_segment', 'new')
            ->whereBetween('registration_date', [$now->copy()->subDays(95), $now->copy()->subDays(60)])
            ->with(['contracts.plan'])
            ->get();

        return view('customer::customers.bi', compact(
            'customers',
            'segmentCounts',
            'totalCustomers',
            'revenuePerSegment',
            'segmentFilter',
            'criticalVipAlerts',
            'newClientMilestones'
        ));
    }

    /**
     * Ejecutar clasificación BI en vivo
     */
    public function runBiClassification(Request $request)
    {
        try {
            $customers = Customer::all();
            $now = \Carbon\Carbon::now();
            $updated = 0;

            foreach ($customers as $c) {
                $regDate = $c->registration_date ? \Carbon\Carbon::parse($c->registration_date) : $now;
                $tenureMonths = max(0, $regDate->diffInMonths($now));

                $monthlyArpu = (float) \DB::table('contracts')
                    ->join('plans', 'contracts.plan_id', '=', 'plans.id')
                    ->where('contracts.customer_id', $c->id)
                    ->where('contracts.status', 'active')
                    ->sum('plans.price');

                $paidInvoicesCount = \DB::table('invoices')
                    ->join('contracts', 'invoices.contract_id', '=', 'contracts.id')
                    ->where('contracts.customer_id', $c->id)
                    ->where('invoices.status', 'paid')
                    ->count();

                $pendingInvoicesCount = \DB::table('invoices')
                    ->join('contracts', 'invoices.contract_id', '=', 'contracts.id')
                    ->where('contracts.customer_id', $c->id)
                    ->whereIn('invoices.status', ['pending', 'partial'])
                    ->count();

                $overdueInvoicesCount = \DB::table('invoices')
                    ->join('contracts', 'invoices.contract_id', '=', 'contracts.id')
                    ->where('contracts.customer_id', $c->id)
                    ->where('invoices.status', 'overdue')
                    ->count();

                $totalInvoices = $paidInvoicesCount + $pendingInvoicesCount + $overdueInvoicesCount;
                $punctualityRate = $totalInvoices > 0 ? ($paidInvoicesCount / $totalInvoices) : 0.5;

                $lastPaymentDate = \DB::table('payments')
                    ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
                    ->join('contracts', 'invoices.contract_id', '=', 'contracts.id')
                    ->where('contracts.customer_id', $c->id)
                    ->where('payments.status', 'completed')
                    ->max('payments.payment_date');

                $recencyDays = $lastPaymentDate ? max(0, \Carbon\Carbon::parse($lastPaymentDate)->diffInDays($now)) : 999;

                if ($tenureMonths < 3) {
                    $segment = 'new';
                    $score = 60.0 + ($punctualityRate * 20.0);
                } elseif ($overdueInvoicesCount > 0 || ($pendingInvoicesCount > 0 && $recencyDays > 30)) {
                    $segment = 'churn_risk';
                    $score = max(10.0, 50.0 - ($overdueInvoicesCount * 15.0) - ($recencyDays / 5.0));
                } elseif ($tenureMonths >= 6 && $punctualityRate >= 0.90 && $overdueInvoicesCount === 0 && $monthlyArpu >= 50.0) {
                    $segment = 'vip';
                    $score = min(100.0, 85.0 + ($monthlyArpu / 10.0) + ($tenureMonths / 2.0));
                } else {
                    $segment = 'regular';
                    $score = 70.0 + ($punctualityRate * 15.0);
                }

                $c->update([
                    'bi_segment' => $segment,
                    'bi_score' => round($score, 2),
                    'bi_updated_at' => $now,
                ]);

                $updated++;
            }

            return redirect()->back()->with('success', "Clasificación BI ejecutada exitosamente en {$updated} clientes.");
        } catch (\Throwable $e) {
            \Log::error('Error ejecutando clasificación BI de clientes', [
                'action' => 'customer.runBiClassification',
                'updated_before_failure' => $updated ?? 0,
                'exception' => $e,
            ]);

            return redirect()->back()->with('error', 'No se pudo ejecutar la clasificación. Inténtelo nuevamente o contacte al administrador.');
        }
    }
}
