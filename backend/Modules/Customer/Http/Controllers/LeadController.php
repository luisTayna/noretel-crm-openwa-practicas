<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\LeadService;
use Modules\Customer\Http\Requests\StoreLeadRequest;
use Modules\Customer\Entities\Lead;
use Illuminate\Support\Facades\Auth;

class LeadController extends Controller
{
    /**
     * @var LeadService
     */
    protected $leadService;

    /**
     * LeadController constructor.
     *
     * @param LeadService $leadService
     */
    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     */
    public function index(Request $request)
    {
        $status = $request->input('status');
        $source = $request->input('source');
        $search = $request->input('search');
        $unconverted = $request->input('unconverted');
        
        $filters = [];
        
        if ($status) {
            $filters['status'] = $status;
        }
        
        if ($source) {
            $filters['source'] = $source;
        }
        
        if ($search) {
            $filters['search'] = $search;
        }
        
        if ($unconverted) {
            $filters['unconverted'] = true;
        }
        
        $leads = $this->leadService->searchLeads($filters);
        
        $leadSources = Lead::distinct()->pluck('source')->filter();
        $leadStatuses = Lead::distinct()->pluck('status')->filter();
        
        return view('customer::leads.index', compact(
            'leads', 
            'leadSources', 
            'leadStatuses', 
            'status', 
            'source', 
            'search',
            'unconverted'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        $leadSources = $this->leadSources();
        
        $leadStatuses = $this->leadStatuses();
        
        return view('customer::leads.create', compact('leadSources', 'leadStatuses'));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreLeadRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreLeadRequest $request)
    {
        $data = $this->prepareLeadData($request->validated());
        
        $result = $this->leadService->createLead(
            $data,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.leads.show', $result['lead']->id)
            ->with('success', 'Lead creado exitosamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $lead = Lead::with(['customers'])
            ->findOrFail($id);
        
        return view('customer::leads.show', compact('lead'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $lead = Lead::findOrFail($id);
        
        $leadSources = $this->leadSources();
        
        $leadStatuses = $this->leadStatuses();
        
        return view('customer::leads.edit', compact('lead', 'leadSources', 'leadStatuses'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact' => 'nullable|required_without_all:phone,email|string|max:255',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:255',
            'source' => 'nullable|string|max:100',
            'status' => 'required|string|max:50',
            'potential_value' => 'nullable|numeric|min:0',
            'message' => 'nullable|string|max:1000',
            'interest' => 'nullable|string|max:80',
        ]);
        
        $data = $this->prepareLeadData($validated);
        
        $result = $this->leadService->updateLead(
            $id,
            $data,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.leads.show', $id)
            ->with('success', 'Lead actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $result = $this->leadService->deleteLead(
            $id,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.leads.index')
            ->with('success', $result['message']);
    }

    /**
     * Show form to convert lead to customer.
     * @param int $id
     * @return Renderable
     */
    public function showConvertForm($id)
    {
        $lead = Lead::findOrFail($id);
        
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
        
        return view('customer::leads.convert', compact('lead', 'customerTypes', 'segments'));
    }

    /**
     * Convert lead to customer.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function convert(Request $request, $id)
    {
        $request->validate([
            'customer_type' => 'required|string|in:individual,business',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'identity_document' => 'nullable|string|max:50|unique:customers,identity_document',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'segment' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            
            // Address validation
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);
        
        // Prepare customer data
        $customerData = $request->only([
            'customer_type', 'first_name', 'last_name', 'identity_document', 
            'email', 'phone', 'segment'
        ]);
        
        // Set defaults
        $customerData['active'] = true;
        $customerData['registration_date'] = now();
        
        // Addresses to be created after customer
        $customerData['addresses'] = [
            [
                'address_type' => 'main',
                'street' => $request->input('street'),
                'number' => $request->input('number'),
                'city' => $request->input('city'),
                'state' => $request->input('state'),
                'postal_code' => $request->input('postal_code'),
                'is_primary' => true
            ]
        ];
        
        $notes = $request->input('notes');
        
        $result = $this->leadService->convertToCustomer(
            $id,
            $customerData,
            $notes,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.customers.show', $result['customer']->id)
            ->with('success', $result['message']);
    }

    /**
     * Change lead status.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:new,contacted,qualified,proposal,negotiation,converted,lost',
        ]);
        
        $status = $request->input('status');
        
        $result = $this->leadService->changeStatus(
            $id,
            $status,
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

    protected function prepareLeadData(array $data): array
    {
        if (empty($data['contact'])) {
            $data['contact'] = $data['phone'] ?? $data['email'] ?? '';
        }

        $data['capture_date'] = $data['capture_date'] ?? now();
        $data['status'] = $data['status'] ?? 'new';

        return $data;
    }

    protected function leadSources(): array
    {
        return [
            'website_call_request' => 'Solicitud de llamada web',
            'website_whatsapp_click' => 'Click WhatsApp web',
            'whatsapp_openwa' => 'WhatsApp OpenWA',
            'website' => 'Sitio web',
            'phone' => 'Llamada telefónica',
            'email' => 'Correo electrónico',
            'referral' => 'Referido',
            'social_media' => 'Redes sociales',
            'event' => 'Evento',
            'other' => 'Otro',
        ];
    }

    protected function leadStatuses(): array
    {
        return [
            'new' => 'Nuevo',
            'contacted' => 'Contactado',
            'qualified' => 'Calificado',
            'proposal' => 'Propuesta enviada',
            'negotiation' => 'En negociación',
            'converted' => 'Convertido',
            'lost' => 'Perdido',
        ];
    }

}