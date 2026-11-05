<?php

namespace Modules\Billing\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Billing\Repositories\PaymentRepository;
use Modules\Contract\Entities\Contract;

class BillingController extends Controller
{
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * BillingController constructor.
     */
    public function __construct(
        InvoiceRepository $invoiceRepository,
        PaymentRepository $paymentRepository
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->paymentRepository = $paymentRepository;
    }

    /**
     * Display a dashboard for billing module.
     *
     * @return Renderable
     */
    public function dashboard(Request $request)
    {
        $pendingStatuses = ['pending', 'partial', 'overdue'];

        // Resumen de recibos. No cargamos toda la cartera en memoria:
        // los KPIs se calculan en SQL y el listado solo trae lo que se muestra.
        $pendingBaseQuery = $this->invoiceRepository->query()
            ->whereIn('status', $pendingStatuses);

        $pendingInvoicesCount = (clone $pendingBaseQuery)->count();
        $pendingTotal = (float) (clone $pendingBaseQuery)->sum('total_amount');

        $overdueBaseQuery = $this->invoiceRepository->query()
            ->where('status', 'pending')
            ->where('due_date', '<', now());

        $overdueInvoicesCount = (clone $overdueBaseQuery)->count();
        $overdueTotal = (float) (clone $overdueBaseQuery)->sum('total_amount');

        $pendingInvoices = (clone $pendingBaseQuery)
            ->with(['contract.customer'])
            ->orderBy('issue_date', 'desc')
            ->limit(5)
            ->get();

        // Pagos recientes
        $recentPayments = $this->paymentRepository->query()
            ->with(['invoice.contract.customer'])
            ->where('status', 'completed')
            ->orderBy('payment_date', 'desc')
            ->limit(10)
            ->get();

        // Estadísticas mensuales
        $currentMonth = Carbon::now()->format('m');
        $currentYear = Carbon::now()->format('Y');

        $monthlyInvoicesTotal = (float) $this->invoiceRepository->query()
            ->whereMonth('issue_date', $currentMonth)
            ->whereYear('issue_date', $currentYear)
            ->sum('total_amount');

        $monthlyPaymentsTotal = (float) $this->paymentRepository->query()
            ->whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->where('status', 'completed')
            ->sum('amount');

        // Facturas por estado
        $invoicesByStatus = $this->invoiceRepository->query()
            ->selectRaw('status, count(*) as count, sum(total_amount) as total')
            ->groupBy('status')
            ->get();

        $monthlyComparison = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthKey = $date->format('M Y');

            // Facturas del mes
            $monthlyInvoicesAmount = $this->invoiceRepository->query()
                ->whereMonth('issue_date', $date->month)
                ->whereYear('issue_date', $date->year)
                ->sum('total_amount');

            // Pagos del mes
            $monthlyPaymentsAmount = $this->paymentRepository->query()
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->where('status', 'completed')
                ->sum('amount');

            $monthlyComparison[$monthKey] = [
                'invoiced' => (float) $monthlyInvoicesAmount,
                'collected' => (float) $monthlyPaymentsAmount,
            ];
        }

        return view('billing::dashboard', compact(
            'pendingInvoices',
            'pendingInvoicesCount',
            'overdueInvoicesCount',
            'pendingTotal',
            'overdueTotal',
            'recentPayments',
            'monthlyInvoicesTotal',
            'monthlyPaymentsTotal',
            'invoicesByStatus',
            'monthlyComparison'
        ));
    }

    /**
     * Display a customer billing summary.
     *
     * @param  int  $customerId
     * @return Renderable
     */
    public function customerBillingSummary($customerId)
    {
        // Invoices for the customer
        $invoices = $this->invoiceRepository->getInvoicesByCustomer($customerId);

        // Payments for the customer
        $payments = $this->paymentRepository->getPaymentsByCustomer($customerId);

        // Summary stats
        $pendingTotal = $invoices->whereIn('status', ['pending', 'partial'])->sum('total_amount') -
                        $payments->where('status', 'completed')->sum('amount');

        $paidTotal = $payments->where('status', 'completed')->sum('amount');

        $overdueInvoices = $invoices->where('status', 'pending')
            ->where('due_date', '<', Carbon::now())
            ->sortBy('due_date');

        return view('billing::customer_summary', compact(
            'invoices',
            'payments',
            'pendingTotal',
            'paidTotal',
            'overdueInvoices',
            'customerId'
        ));
    }

    /**
     * Display financial reports
     *
     * @return Renderable
     */
    public function reports(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->format('Y-m-d'));
        $reportType = $request->get('report_type', 'payments');

        $data = [];

        if ($reportType === 'payments') {
            // Reporte de pagos
            $payments = $this->paymentRepository->query()
                ->with(['invoice.contract.customer'])
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->get();

            // Agrupar por método de pago
            $byMethod = $payments->groupBy('payment_method')
                ->map(function ($items, $key) {
                    return [
                        'method' => $key,
                        'count' => $items->count(),
                        'amount' => $items->sum('amount'),
                    ];
                })->values();

            // Agrupar por día
            $byDate = $payments->groupBy(function ($item) {
                return Carbon::parse($item->payment_date)->format('Y-m-d');
            })
                ->map(function ($items, $key) {
                    return [
                        'date' => $key,
                        'count' => $items->count(),
                        'amount' => $items->sum('amount'),
                    ];
                })->values();

            $data = [
                'payments' => $payments,
                'by_method' => $byMethod,
                'by_date' => $byDate,
                'total' => $payments->sum('amount'),
            ];
        } elseif ($reportType === 'invoices') {
            // Reporte de facturas
            $invoices = $this->invoiceRepository->query()
                ->with(['contract.customer'])
                ->whereBetween('issue_date', [$startDate, $endDate])
                ->get();

            // Agrupar por estado
            $byStatus = $invoices->groupBy('status')
                ->map(function ($items, $key) {
                    return [
                        'status' => $key,
                        'count' => $items->count(),
                        'amount' => $items->sum('total_amount'),
                    ];
                })->values();

            // Agrupar por día
            $byDate = $invoices->groupBy(function ($item) {
                return Carbon::parse($item->issue_date)->format('Y-m-d');
            })
                ->map(function ($items, $key) {
                    return [
                        'date' => $key,
                        'count' => $items->count(),
                        'amount' => $items->sum('total_amount'),
                    ];
                })->values();

            $data = [
                'invoices' => $invoices,
                'by_status' => $byStatus,
                'by_date' => $byDate,
                'total' => $invoices->sum('total_amount'),
            ];
        } elseif ($reportType === 'aging') {
            // Reporte de envejecimiento de cartera
            $overdueInvoices = $this->invoiceRepository->query()
                ->withAmountTotals()
                ->with(['contract.customer'])
                ->where('status', 'pending')
                ->where('due_date', '<', Carbon::now())
                ->get();

            // Agrupar por rango de días vencidos
            $aging = [
                '1-15' => ['count' => 0, 'amount' => 0],
                '16-30' => ['count' => 0, 'amount' => 0],
                '31-60' => ['count' => 0, 'amount' => 0],
                '61-90' => ['count' => 0, 'amount' => 0],
                '90+' => ['count' => 0, 'amount' => 0],
            ];

            foreach ($overdueInvoices as $invoice) {
                $daysOverdue = now()->diffInDays($invoice->due_date);

                if ($daysOverdue <= 15) {
                    $aging['1-15']['count']++;
                    $aging['1-15']['amount'] += $invoice->pending_amount;
                } elseif ($daysOverdue <= 30) {
                    $aging['16-30']['count']++;
                    $aging['16-30']['amount'] += $invoice->pending_amount;
                } elseif ($daysOverdue <= 60) {
                    $aging['31-60']['count']++;
                    $aging['31-60']['amount'] += $invoice->pending_amount;
                } elseif ($daysOverdue <= 90) {
                    $aging['61-90']['count']++;
                    $aging['61-90']['amount'] += $invoice->pending_amount;
                } else {
                    $aging['90+']['count']++;
                    $aging['90+']['amount'] += $invoice->pending_amount;
                }
            }

            // Agrupar por cliente
            $byCustomer = $overdueInvoices->groupBy(function ($item) {
                return $item->contract->customer_id;
            })
                ->map(function ($items, $customerId) {
                    $customer = $items->first()->contract->customer;

                    return [
                        'customer_id' => $customerId,
                        'customer_name' => $customer->full_name,
                        'count' => $items->count(),
                        'amount' => $items->sum('pending_amount'),
                    ];
                })->values()->sortByDesc('amount');

            $data = [
                'overdueInvoices' => $overdueInvoices,
                'aging' => $aging,
                'by_customer' => $byCustomer,
                'total' => $overdueInvoices->sum('pending_amount'),
            ];
        }

        return view('billing::reports', compact('data', 'reportType', 'startDate', 'endDate'));
    }

    /**
     * Parse the month number from the invoice's billing period or issue date.
     *
     * @param mixed $invoice
     * @return int
     */
    protected function parseMonthFromInvoice($invoice): int
    {
        $billingPeriod = trim((string) $invoice->billing_period);
        if ($billingPeriod === '') {
            return (int) Carbon::parse($invoice->issue_date)->month;
        }

        // Try date formats like dd/mm/yyyy
        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $billingPeriod, $matches, PREG_SET_ORDER)) {
            $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
            return (int) $targetMatch[2];
        }

        // Try format like mm/yyyy
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $billingPeriod, $matches)) {
            return (int) $matches[1];
        }

        // Normalize text
        $normalized = strtr(strtolower($billingPeriod), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ]);

        $monthsMap = [
            // Spanish
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
            // English
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
            'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];

        // Search for month names
        foreach ($monthsMap as $name => $num) {
            if (str_contains($normalized, $name)) {
                return $num;
            }
        }

        // Fallback to issue_date month
        return (int) Carbon::parse($invoice->issue_date)->month;
    }

    /**
     * Display annual payment notebook matrix.
     *
     * @return Renderable
     */
    public function notebook(Request $request)
    {
        if ($request->get('mode') === 'period-repair') {
            $service = app(\Modules\Billing\Services\BillingPeriodRepairService::class);
            $groups = $service->duplicateGroups();
            $safeGroups = $groups->where('safe', true);
            $manualGroups = $groups->where('safe', false);

            return view('billing::period-repair.index', compact('groups', 'safeGroups', 'manualGroups'));
        }

        $year = (int) $request->get('year', Carbon::now()->year);

        // Obtener todos los contratos con sus clientes, planes y facturas del año seleccionado
        $contracts = Contract::with([
            'customer',
            'plan',
            'invoices' => function ($q) use ($year) {
                $q->whereYear('issue_date', $year)
                    ->whereNotIn('status', ['cancelled', 'void']);
            },
        ])
            ->whereIn('status', ['active', 'suspended', 'inactive'])
            ->get();

        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        $matrix = [];
        foreach ($contracts as $contract) {
            if (! $contract->customer) {
                continue;
            }

            $customer = $contract->customer;
            $row = [
                'contract_id' => $contract->id,
                'full_name' => $customer->full_name,
                'identity_document' => $customer->identity_document ?: '-',
                'phone' => $customer->phone ?: $customer->whatsapp_phone ?: '-',
                'plan_name' => optional($contract->plan)->name ?: 'Sin Plan',
                'monthly_price' => $contract->final_price ?: optional($contract->plan)->price ?: 0,
                'months' => [],
                // Campos adicionales para el panel expansible
                'address' => $contract->installation_address ?: $customer->address ?: 'Sin dirección registrada',
                'speed' => optional($contract->plan)->speed ?: 'No especificado',
                'technology' => $contract->technology ?: 'Fibra Óptica',
                'status' => $contract->status,
                'notes' => $contract->notes ?: $customer->notes ?: 'Sin observaciones',
            ];

            $invoicesByMonth = [];
            foreach ($contract->invoices as $invoice) {
                $monthNum = $this->parseMonthFromInvoice($invoice);
                $invoicesByMonth[$monthNum] = $invoice;
            }

            for ($m = 1; $m <= 12; $m++) {
                $invoice = $invoicesByMonth[$m] ?? null;
                if ($invoice) {
                    $row['months'][$m] = [
                        'status' => $invoice->status,
                        'amount' => $invoice->total_amount,
                        'pending' => $invoice->pending_amount,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'is_overdue' => $invoice->isOverdue(),
                        'issue_date' => Carbon::parse($invoice->issue_date)->format('d/m/Y'),
                        'due_date' => Carbon::parse($invoice->due_date)->format('d/m/Y'),
                    ];
                } else {
                    $row['months'][$m] = null;
                }
            }

            $matrix[] = $row;
        }

        // Ordenar alfabéticamente por nombre de cliente para facilidad de búsqueda
        usort($matrix, function ($a, $b) {
            return strcmp($a['full_name'], $b['full_name']);
        });

        // Calcular KPIs agregados para el año seleccionado
        $totalPaidAmount = 0;
        $totalPendingAmount = 0;
        $totalInvoicesCount = 0;
        $paidInvoicesCount = 0;
        $pendingInvoicesCount = 0;
        $overdueInvoicesCount = 0;

        foreach ($contracts as $contract) {
            foreach ($contract->invoices as $invoice) {
                $totalInvoicesCount++;
                $totalPaidAmount += ($invoice->total_amount - $invoice->pending_amount);
                $totalPendingAmount += $invoice->pending_amount;

                if ($invoice->status === 'paid') {
                    $paidInvoicesCount++;
                } else {
                    $pendingInvoicesCount++;
                    if ($invoice->isOverdue()) {
                        $overdueInvoicesCount++;
                    }
                }
            }
        }

        $totalAmount = $totalPaidAmount + $totalPendingAmount;
        $collectionRate = $totalAmount > 0 ? round(($totalPaidAmount / $totalAmount) * 100, 1) : 100;

        $data = [
            'matrix' => $matrix,
            'months' => $months,
            'year' => $year,
            'kpis' => [
                'total_collected' => $totalPaidAmount,
                'total_pending' => $totalPendingAmount,
                'collection_rate' => $collectionRate,
                'counts' => [
                    'total' => $totalInvoicesCount,
                    'paid' => $paidInvoicesCount,
                    'pending' => $pendingInvoicesCount,
                    'overdue' => $overdueInvoicesCount,
                ],
            ],
        ];

        return view('billing::notebook', compact('data'));
    }
}
