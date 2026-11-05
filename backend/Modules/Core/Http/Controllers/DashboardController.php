<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Repositories\UserRepository;
use Modules\Core\Repositories\RoleRepository;
use Modules\Core\Repositories\NotificationRepository;
use Modules\Core\Repositories\WorkflowRepository;
use Modules\Core\Entities\AuditLog;
use Illuminate\Support\Facades\Auth;
//importa log
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class DashboardController extends Controller
{
    /**
     * @var UserRepository
     */
    protected $userRepository;

    /**
     * @var RoleRepository
     */
    protected $roleRepository;

    /**
     * @var NotificationRepository
     */
    protected $notificationRepository;

    /**
     * @var WorkflowRepository
     */
    protected $workflowRepository;

    /**
     * DashboardController constructor.
     *
     * @param UserRepository $userRepository
     * @param RoleRepository $roleRepository
     * @param NotificationRepository $notificationRepository
     * @param WorkflowRepository $workflowRepository
     */
    public function __construct(
        UserRepository $userRepository,
        RoleRepository $roleRepository,
        NotificationRepository $notificationRepository,
        WorkflowRepository $workflowRepository
    ) {
        $this->userRepository = $userRepository;
        $this->roleRepository = $roleRepository;
        $this->notificationRepository = $notificationRepository;
        $this->workflowRepository = $workflowRepository;
    }

    /**
     * Display the dashboard.
     *
     * @return Renderable
     */
    public function index()
    {
        // Obtener estadísticas
        $stats = $this->getStats();
        
        // Obtener estadísticas económicas
        $economicStats = $this->getEconomicStats();

        // Obtener actividad reciente
        $recentActivities = AuditLog::with('user')
            ->orderBy('action_date', 'desc')
            ->limit(5)
            ->get();

        // Obtener notificaciones recientes
        $recentNotifications = $this->notificationRepository->getNotificationsByRecipient(
            Auth::user()->email,
            5
        );

        // Obtener estado de componentes del sistema
        $systemComponents = $this->getSystemComponents();
       
        return view('core::dashboard', compact(
            'stats',
            'economicStats',
            'recentActivities',
            'recentNotifications',
            'systemComponents'
        ));
    }

    /**
     * Get economic statistics.
     *
     * @return array
     */
    protected function getEconomicStats()
    {
        return \Illuminate\Support\Facades\Cache::remember('dashboard_economic_stats', 120, function () {
            $currentMonth = \Carbon\Carbon::now()->month;
            $currentYear = \Carbon\Carbon::now()->year;

        // MRR: Ingresos del mes actual
        $mrr = \Illuminate\Support\Facades\DB::table('payments')
            ->where('status', 'completed')
            ->whereMonth('payment_date', $currentMonth)
            ->whereYear('payment_date', $currentYear)
            ->sum('amount');

        // Cuentas por cobrar (Facturas pendientes, vencidas o pagos parciales - pagos realizados para esas facturas)
        $pendingInvoicesTotal = \Illuminate\Support\Facades\DB::table('invoices')
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->sum('total_amount');
            
        $pendingInvoicesPaid = \Illuminate\Support\Facades\DB::table('payments')
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->whereIn('invoices.status', ['pending', 'overdue', 'partial'])
            ->where('payments.status', 'completed')
            ->sum('payments.amount');
            
        $receivables = max(0, $pendingInvoicesTotal - $pendingInvoicesPaid);

        // Tasa de morosidad
        $totalInvoicesCount = \Illuminate\Support\Facades\DB::table('invoices')->whereIn('status', ['paid', 'pending', 'overdue'])->count();
        $overdueInvoicesCount = \Illuminate\Support\Facades\DB::table('invoices')->where('status', 'overdue')->count();
        $defaultRate = $totalInvoicesCount > 0 ? round(($overdueInvoicesCount / $totalInvoicesCount) * 100, 2) : 0;

        // Nuevos clientes este mes
        $newCustomers = \Illuminate\Support\Facades\DB::table('customers')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        // Datos para gráfico (Últimos 6 meses)
        $chartData = [
            'labels' => [],
            'data' => []
        ];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = \Carbon\Carbon::now()->subMonths($i);
            $sum = \Illuminate\Support\Facades\DB::table('payments')
                ->where('status', 'completed')
                ->whereMonth('payment_date', $date->month)
                ->whereYear('payment_date', $date->year)
                ->sum('amount');
            
            $monthName = ucfirst($date->translatedFormat('F'));
            $chartData['labels'][] = $monthName;
            $chartData['data'][] = (float) $sum;
        }

        // Últimos pagos
        $recentPayments = \Illuminate\Support\Facades\DB::table('payments')
            ->join('invoices', 'payments.invoice_id', '=', 'invoices.id')
            ->join('contracts', 'invoices.contract_id', '=', 'contracts.id')
            ->join('customers', 'contracts.customer_id', '=', 'customers.id')
            ->where('payments.status', 'completed')
            ->select('payments.id', 'payments.amount', 'payments.payment_date', 'payments.payment_method', 'customers.first_name', 'customers.last_name', 'invoices.invoice_number')
            ->orderBy('payments.payment_date', 'desc')
            ->limit(5)
            ->get();

        return [
            'mrr' => $mrr,
            'receivables' => $receivables,
            'default_rate' => $defaultRate,
            'new_customers' => $newCustomers,
            'chart_data' => $chartData,
            'recent_payments' => $recentPayments
        ];
        });
    }

    /**
     * Get system statistics.
     *
     * @return array
     */
    protected function getStats()
    {
        $users = $this->userRepository->count();

        // Usuarios nuevos en el último mes
        $newUsers = $this->userRepository->query()
            ->where('created_at', '>=', now()->subMonth())
            ->count();

        $roles = $this->roleRepository->count();

        // Total de permisos Spatie en el sistema
        $permissions = Permission::count();

        // Notificaciones
        $notifications = $this->notificationRepository->getModel()->count();

        // Notificaciones sin leer
        $unreadNotifications = $this->notificationRepository->getModel()
            ->where('recipient', Auth::user()->email)
            ->whereRaw("NOT (JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.read')) = 'true')")
            ->count();

        // Workflows
        $workflows = $this->workflowRepository->count();

        // Workflows activos
        $activeWorkflows = $this->workflowRepository->getActiveWorkflows()->count();

        return [
            'users' => $users,
            'new_users' => $newUsers,
            'roles' => $roles,
            'permissions' => $permissions,
            'notifications' => $notifications,
            'unread_notifications' => $unreadNotifications,
            'workflows' => $workflows,
            'active_workflows' => $activeWorkflows
        ];
    }

    /**
     * Get system components status.
     *
     * @return array
     */
    protected function getSystemComponents()
    {
        // Esta función podría ser mucho más compleja y verificar
        // realmente el estado de diversos componentes del sistema

        return [
            [
                'name' => 'Core',
                'status' => 'active',
                'description' => 'Módulo principal del sistema'
            ],
            [
                'name' => 'Autenticación',
                'status' => 'active',
                'description' => 'Sistema de autenticación y autorización'
            ],
            [
                'name' => 'Notificaciones',
                'status' => 'active',
                'description' => 'Sistema de notificaciones'
            ],
            [
                'name' => 'Workflows',
                'status' => 'active',
                'description' => 'Motor de flujos de trabajo'
            ],
            [
                'name' => 'Base de datos',
                'status' => 'active',
                'description' => 'Conexión y funcionamiento de la base de datos'
            ],
            [
                'name' => 'Integración de correo',
                'status' => 'active',
                'description' => 'Integración con el servidor de correo'
            ]
        ];
    }
}
