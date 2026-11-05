<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Entities\Lead;
use Modules\Customer\Entities\Document;
use Modules\Customer\Entities\Interaction;
use Illuminate\Support\Facades\DB;

class CustomerDashboardController extends Controller
{
    /**
     * Display the dashboard.
     * @return Renderable
     */
    public function index()
    {
        // Estadísticas de clientes
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('active', true)->count();
        $inactiveCustomers = $totalCustomers - $activeCustomers;
        $newCustomersThisMonth = Customer::whereMonth('registration_date', now()->month)
            ->whereYear('registration_date', now()->year)
            ->count();
        
        // Estadísticas de leads
        $totalLeads = Lead::count();
        $unconvertedLeads = Lead::whereDoesntHave('customers')->count();
        $newLeadsThisMonth = Lead::whereMonth('capture_date', now()->month)
            ->whereYear('capture_date', now()->year)
            ->count();
        
        // Documentos pendientes de verificación
        $pendingDocuments = Document::where('status', 'pending')->count();
        
        // Interacciones que requieren seguimiento
        $followUpInteractions = Interaction::where('follow_up_required', true)->count();
        
        // Distribución de clientes por tipo
        $customersByType = Customer::select('customer_type', DB::raw('count(*) as total'))
            ->groupBy('customer_type')
            ->get();
        
        // Distribución de clientes por segmento
        $customersBySegment = Customer::select('segment', DB::raw('count(*) as total'))
            ->groupBy('segment')
            ->get();
        
        // Últimas interacciones
        $recentInteractions = Interaction::with(['customer', 'createdBy'])
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();
        
        // Últimos leads
        $recentLeads = Lead::orderBy('capture_date', 'desc')
            ->limit(5)
            ->get();
        
        return view('customer::dashboard', compact(
            'totalCustomers',
            'activeCustomers',
            'inactiveCustomers',
            'newCustomersThisMonth',
            'totalLeads',
            'unconvertedLeads',
            'newLeadsThisMonth',
            'pendingDocuments',
            'followUpInteractions',
            'customersByType',
            'customersBySegment',
            'recentInteractions',
            'recentLeads'
        ));
    }
}
