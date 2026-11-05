<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Customer\Services\DocumentService;
use Modules\Customer\Http\Requests\StoreDocumentRequest;
use Modules\Customer\Entities\Document;
use Modules\Customer\Entities\DocumentType;
use Modules\Customer\Entities\Customer;
use Modules\Contract\Entities\Contract;
use Modules\Billing\Entities\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * @var DocumentService
     */
    protected $documentService;

    /**
     * DocumentController constructor.
     *
     * @param DocumentService $documentService
     */
    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     */
    public function index(Request $request)
    {
        $this->ensureDefaultDocumentTypes();

        $customerId = $request->input('customer_id');
        $typeId = $request->input('type_id');
        $status = $request->input('status');
        $commercialType = $request->input('commercial_type', 'all');
        $commercialStatus = $request->input('commercial_status');
        $contractStatuses = ['active', 'inactive', 'suspended', 'cancelled'];
        $invoiceStatuses = ['paid', 'pending', 'partial', 'overdue', 'void', 'cancelled'];
        
        $query = Document::query()->with(['customer', 'documentType']);
        
        if ($customerId) {
            $query->where('customer_id', $customerId);
        }
        
        if ($typeId) {
            $query->where('document_type_id', $typeId);
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $documents = $query->orderBy('upload_date', 'desc')->paginate(15);
        
        $customers = Customer::orderBy('first_name')->get();
        $documentTypes = DocumentType::orderBy('name')->get();
        
        $commercialContracts = Contract::with(['customer', 'plan'])
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->when($commercialStatus && in_array($commercialStatus, $contractStatuses, true), fn ($query) => $query->where('status', $commercialStatus))
            ->orderByDesc('start_date')
            ->paginate(10, ['*'], 'contracts_page');

        $commercialInvoices = Invoice::with(['contract.customer'])
            ->when($customerId, fn ($query) => $query->whereHas('contract', fn ($contractQuery) => $contractQuery->where('customer_id', $customerId)))
            ->when($commercialStatus && in_array($commercialStatus, $invoiceStatuses, true), fn ($query) => $query->where('status', $commercialStatus))
            ->orderByDesc('issue_date')
            ->paginate(10, ['*'], 'invoices_page');

        $summary = [
            'attachments' => Document::when($customerId, fn ($query) => $query->where('customer_id', $customerId))->count(),
            'contracts' => Contract::when($customerId, fn ($query) => $query->where('customer_id', $customerId))->count(),
            'invoices' => Invoice::when($customerId, fn ($query) => $query->whereHas('contract', fn ($contractQuery) => $contractQuery->where('customer_id', $customerId)))->count(),
        ];

        return view('customer::documents.index', compact(
            'documents',
            'customers',
            'documentTypes',
            'customerId',
            'typeId',
            'status',
            'commercialContracts',
            'commercialInvoices',
            'commercialType',
            'commercialStatus',
            'summary'
        ));
    }

    /**
     * Show the form for creating a new resource.
     * @param Request $request
     * @return Renderable
     */
    public function create(Request $request)
    {
        $this->ensureDefaultDocumentTypes();

        $customerId = $request->input('customer_id');
        $customer = null;
        
        if ($customerId) {
            $customer = Customer::findOrFail($customerId);
        }
        
        $customers = Customer::orderBy('first_name')->get();
        $documentTypes = DocumentType::orderBy('name')->get();
        
        return view('customer::documents.create', compact('customers', 'documentTypes', 'customer'));
    }

    /**
     * Store a newly created resource in storage.
     * @param StoreDocumentRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDocumentRequest $request)
    {
        $documentData = $request->except('file');
        $file = $request->file('file');
        
        $result = $this->documentService->uploadDocument(
            $documentData,
            $file,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.documents.show', $result['document']->id)
            ->with('success', 'Documento subido exitosamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $document = Document::with(['customer', 'documentType', 'versions'])
            ->findOrFail($id);
        
        return view('customer::documents.show', compact('document'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $document = Document::with(['customer', 'documentType'])
            ->findOrFail($id);
        
        $documentTypes = DocumentType::orderBy('name')->get();
        
        return view('customer::documents.edit', compact('document', 'documentTypes'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'document_type_id' => 'required|exists:document_types,id',
            'classification' => 'nullable|string|max:100',
        ]);
        
        $documentData = $request->only(['name', 'document_type_id', 'classification']);
        
        $result = $this->documentService->updateDocument(
            $id,
            $documentData,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.documents.show', $id)
            ->with('success', 'Documento actualizado exitosamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        $result = $this->documentService->deleteDocument(
            $id,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.documents.index')
            ->with('success', $result['message']);
    }

    /**
     * Upload a new version of a document.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function uploadVersion(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'changes' => 'nullable|string',
        ]);
        
        $file = $request->file('file');
        $changes = $request->input('changes');
        
        $result = $this->documentService->uploadNewVersion(
            $id,
            $file,
            $changes,
            Auth::id(),
            $request->ip()
        );
        
        if (!$result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }
        
        return redirect()->route('customer.documents.show', $id)
            ->with('success', 'Nueva versión del documento subida exitosamente.');
    }

    /**
     * Change document status.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,verified,rejected',
        ]);
        
        $status = $request->input('status');
        
        $result = $this->documentService->changeStatus(
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

    /**
     * Download document.
     * @param int $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($id)
    {
        $document = Document::findOrFail($id);
        
        if (!Storage::disk('public')->exists($document->file_path)) {
            return redirect()->back()
                ->with('error', 'El archivo no existe.');
        }
        
        return Storage::disk('public')->download($document->file_path, $document->name);
    }

    /**
     * Download document version.
     * @param int $id
     * @param int $versionId
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadVersion($id, $versionId)
    {
        $document = Document::findOrFail($id);
        $version = $document->versions()->findOrFail($versionId);
        
        if (!Storage::disk('public')->exists($version->file_path)) {
            return redirect()->back()
                ->with('error', 'El archivo no existe.');
        }
        
        return Storage::disk('public')->download(
            $version->file_path, 
            $document->name . ' (v' . $version->version_number . ')'
        );
    }

    /**
     * Ensure the module has usable document types even when the seeder was not run.
     */
    private function ensureDefaultDocumentTypes(): void
    {
        if (DocumentType::query()->exists()) {
            return;
        }

        $documentTypes = [
            ['name' => 'Documento de Identidad', 'description' => 'Documento nacional de identidad del cliente', 'requires_verification' => true, 'allowed_format' => ['pdf', 'jpg', 'jpeg', 'png']],
            ['name' => 'Contrato de Servicio', 'description' => 'Contrato firmado de prestación de servicios', 'requires_verification' => true, 'allowed_format' => ['pdf']],
            ['name' => 'Comprobante de Domicilio', 'description' => 'Documento que acredita el domicilio del cliente', 'requires_verification' => true, 'allowed_format' => ['pdf', 'jpg', 'jpeg', 'png']],
            ['name' => 'Reporte Técnico', 'description' => 'Reporte de instalación o servicio técnico', 'requires_verification' => false, 'allowed_format' => ['pdf', 'jpg', 'jpeg', 'png']],
            ['name' => 'Otro', 'description' => 'Otro tipo de documento', 'requires_verification' => false, 'allowed_format' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx']],
        ];

        foreach ($documentTypes as $type) {
            DocumentType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
