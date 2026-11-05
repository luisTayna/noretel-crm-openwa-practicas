<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Billing\Repositories\CreditNoteRepository;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Core\Entities\AuditLog;
use Carbon\Carbon;

class CreditNoteController extends Controller
{
    /**
     * @var CreditNoteRepository
     */
    protected $creditNoteRepository;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * CreditNoteController constructor.
     *
     * @param CreditNoteRepository $creditNoteRepository
     * @param InvoiceRepository $invoiceRepository
     */
    public function __construct(
        CreditNoteRepository $creditNoteRepository,
        InvoiceRepository $invoiceRepository
    ) {
        $this->creditNoteRepository = $creditNoteRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $from = $request->get('from');
        $to = $request->get('to');
        $perPage = $request->get('per_page', 10);

        $query = $this->creditNoteRepository->query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('credit_note_number', 'like', "%{$search}%")
                  ->orWhereHas('invoice', function($q) use ($search) {
                      $q->where('invoice_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($from) {
            $query->where('issue_date', '>=', $from);
        }

        if ($to) {
            $query->where('issue_date', '<=', $to);
        }

        $creditNotes = $query->orderBy('issue_date', 'desc')->paginate($perPage);

        $statuses = [
            'draft' => 'Borrador',
            'active' => 'Activa',
            'applied' => 'Aplicada',
            'void' => 'Anulada'
        ];

        return view('billing::credit-notes.index', compact('creditNotes', 'search', 'status', 'from', 'to', 'statuses'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create(Request $request)
    {
        $invoiceId = $request->get('invoice_id');
        $invoice = null;

        if ($invoiceId) {
            $invoice = $this->invoiceRepository->find($invoiceId);
        }

        // Obtener lista de facturas para el dropdown (solo facturas no anuladas)
        $invoices = $this->invoiceRepository->query()
            ->whereNotIn('status', ['void'])
            ->get();

        // Generar número de nota de crédito
        $creditNoteNumber = $this->creditNoteRepository->generateCreditNoteNumber();

        // Fecha por defecto (hoy)
        $issueDate = Carbon::now()->format('Y-m-d');

        return view('billing::credit-notes.create', compact('invoice', 'invoices', 'creditNoteNumber', 'issueDate'));
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'credit_note_number' => 'required|string|max:255|unique:credit_notes',
            'amount' => 'required|numeric|min:0.01',
            'issue_date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        // Verificar que el monto no supere el pendiente del recibo
        $invoice = $this->invoiceRepository->find($request->invoice_id);
        if ($request->amount > $invoice->pending_amount) {
            return redirect()->back()->withInput()->with('error', 'El monto de la nota de crédito no puede superar el monto pendiente del recibo.');
        }

        $data = $request->all();
        $data['user_id'] = auth()->id();
        $data['status'] = 'active';

        // Crear nota de crédito
        $creditNote = $this->creditNoteRepository->create($data);

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'credit_note_created',
            'credit_notes',
            "Nota de crédito creada: {$creditNote->credit_note_number} para recibo: {$invoice->invoice_number}",
            request()->ip(),
            null,
            $creditNote->toArray()
        );

        // Si la nota de crédito se aplica directamente
        if ($request->has('apply_now') && $request->apply_now) {
            $this->creditNoteRepository->applyCreditNote($creditNote->id);

            // Registrar en log de auditoría
            AuditLog::register(
                auth()->id(),
                'credit_note_applied',
                'credit_notes',
                "Nota de crédito aplicada: {$creditNote->credit_note_number} para recibo: {$invoice->invoice_number}",
                request()->ip(),
                ['status' => 'active'],
                ['status' => 'applied']
            );
        }

        return redirect()->route('billing.credit-notes.show', $creditNote->id)
            ->with('success', 'Nota de crédito creada correctamente.');
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        $creditNote = $this->creditNoteRepository->find($id);

        // Cargar relaciones
        $creditNote->load(['invoice.contract.customer', 'user']);

        // Obtener logs de auditoría relacionados con esta nota de crédito
        $logs = AuditLog::where('action_detail', 'like', "%{$creditNote->credit_note_number}%")
            ->orderBy('action_date', 'desc')
            ->limit(5)
            ->get();

        return view('billing::credit-notes.show', compact('creditNote', 'logs'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $creditNote = $this->creditNoteRepository->find($id);

        // Solo se pueden editar notas de crédito en borrador
        if ($creditNote->status !== 'draft') {
            return redirect()->route('billing.credit-notes.show', $id)
                ->with('error', 'Solo se pueden editar notas de crédito en estado de borrador.');
        }

        // Cargar relaciones
        $creditNote->load(['invoice']);

        // Obtener lista de facturas para el dropdown (solo facturas no anuladas)
        $invoices = $this->invoiceRepository->query()
            ->whereNotIn('status', ['void'])
            ->get();

        return view('billing::credit-notes.edit', compact('creditNote', 'invoices'));
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
            'invoice_id' => 'required|exists:invoices,id',
            'credit_note_number' => 'required|string|max:255|unique:credit_notes,credit_note_number,' . $id,
            'amount' => 'required|numeric|min:0.01',
            'issue_date' => 'required|date',
            'reason' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        $creditNote = $this->creditNoteRepository->find($id);

        // Solo se pueden editar notas de crédito en borrador
        if ($creditNote->status !== 'draft') {
            return redirect()->route('billing.credit-notes.show', $id)
                ->with('error', 'Solo se pueden editar notas de crédito en estado de borrador.');
        }

        // Verificar que el monto no supere el pendiente del recibo
        $invoice = $this->invoiceRepository->find($request->invoice_id);
        if ($request->amount > $invoice->pending_amount) {
            return redirect()->back()->withInput()->with('error', 'El monto de la nota de crédito no puede superar el monto pendiente del recibo.');
        }

        // Guardar datos anteriores para auditoría
        $oldData = $creditNote->toArray();

        // Actualizar nota de crédito
        $this->creditNoteRepository->update($id, $request->all());

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'credit_note_updated',
            'credit_notes',
            "Nota de crédito actualizada: {$creditNote->credit_note_number}",
            request()->ip(),
            $oldData,
            $creditNote->refresh()->toArray()
        );

        return redirect()->route('billing.credit-notes.show', $id)
            ->with('success', 'Nota de crédito actualizada correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $creditNote = $this->creditNoteRepository->find($id);

        // Solo se pueden eliminar notas de crédito en borrador
        if ($creditNote->status !== 'draft') {
            return redirect()->route('billing.credit-notes.index')
                ->with('error', 'Solo se pueden eliminar notas de crédito en estado de borrador.');
        }

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'credit_note_deleted',
            'credit_notes',
            "Nota de crédito eliminada: {$creditNote->credit_note_number}",
            request()->ip(),
            $creditNote->toArray(),
            null
        );

        // Eliminar nota de crédito
        $this->creditNoteRepository->delete($id);

        return redirect()->route('billing.credit-notes.index')
            ->with('success', 'Nota de crédito eliminada correctamente.');
    }

    /**
     * Apply the credit note to the invoice.
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function apply($id)
    {
        $creditNote = $this->creditNoteRepository->find($id);

        // Verificar que la nota de crédito esté activa
        if ($creditNote->status !== 'active') {
            return redirect()->route('billing.credit-notes.show', $id)
                ->with('error', 'Solo se pueden aplicar notas de crédito en estado activo.');
        }

        // Guardar datos anteriores para auditoría
        $oldData = $creditNote->toArray();

        // Aplicar nota de crédito
        $this->creditNoteRepository->applyCreditNote($id);

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'credit_note_applied',
            'credit_notes',
            "Nota de crédito aplicada: {$creditNote->credit_note_number} para recibo: {$creditNote->invoice->invoice_number}",
            request()->ip(),
            $oldData,
            $creditNote->refresh()->toArray()
        );

        return redirect()->route('billing.credit-notes.show', $id)
            ->with('success', 'Nota de crédito aplicada correctamente.');
    }

    /**
     * Void the specified credit note.
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function void(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $creditNote = $this->creditNoteRepository->find($id);

        // Verificar que la nota de crédito no esté ya anulada o aplicada
        if (in_array($creditNote->status, ['void', 'applied'])) {
            return redirect()->route('billing.credit-notes.show', $id)
                ->with('error', 'No se puede anular una nota de crédito que ya está aplicada o anulada.');
        }

        // Guardar datos anteriores para auditoría
        $oldData = $creditNote->toArray();

        // Anular nota de crédito
        $creditNote->update([
            'status' => 'void',
            'notes' => ($creditNote->notes ? $creditNote->notes . "\n" : '') .
                       "[" . now() . "] Nota de crédito anulada. Motivo: " . $request->reason
        ]);

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'credit_note_voided',
            'credit_notes',
            "Nota de crédito anulada: {$creditNote->credit_note_number}. Motivo: {$request->reason}",
            request()->ip(),
            $oldData,
            $creditNote->toArray()
        );

        return redirect()->route('billing.credit-notes.show', $id)
            ->with('success', 'Nota de crédito anulada correctamente.');
    }

    /**
     * Print the credit note as PDF.
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        $creditNote = $this->creditNoteRepository->find($id);

        // Cargar relaciones
        $creditNote->load(['invoice.contract.customer', 'user']);

        // Aquí implementarías la generación del PDF con alguna librería como DOMPDF
        // Por ahora, solo mostraremos una vista simplificada

        return view('billing::credit-notes.print', compact('creditNote'));
    }
}
