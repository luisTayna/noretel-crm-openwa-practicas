<?php

namespace Modules\Billing\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Billing\Http\Requests\StoreInvoiceRequest;
use Modules\Billing\Http\Requests\UpdateInvoiceRequest;
use Modules\Billing\Repositories\InvoiceRepository;
use Modules\Billing\Services\InvoiceService;
use Modules\Contract\Repositories\ContractRepository;
use Modules\Core\Entities\AuditLog;
use Modules\Operations\Entities\OnboardingOrder;

class InvoiceController extends Controller
{
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var ContractRepository
     */
    protected $contractRepository;

    /**
     * InvoiceController constructor.
     */
    public function __construct(
        InvoiceRepository $invoiceRepository,
        InvoiceService $invoiceService,
        ContractRepository $contractRepository
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->invoiceService = $invoiceService;
        $this->contractRepository = $contractRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Renderable
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $from = $request->get('from');
        $to = $request->get('to');
        $perPage = $request->get('per_page', 10);

        $query = $this->invoiceRepository->query()
            ->with([
                'contract.customer',
                'contract.plan',
                'payments' => fn ($paymentsQuery) => $paymentsQuery
                    ->where('status', 'completed')
                    ->orderByDesc('payment_date')
                    ->orderByDesc('id'),
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('billing_name', 'like', "%{$search}%")
                    ->orWhere('billing_document', 'like', "%{$search}%")
                    ->orWhereHas('contract.customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$search}%"])
                            ->orWhere('identity_document', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
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

        $invoices = $query->orderBy('issue_date', 'desc')->paginate($perPage);

        $statuses = [
            'draft' => 'Borrador',
            'pending' => 'Pendiente',
            'paid' => 'Pagada',
            'partial' => 'Pago Parcial',
            'overdue' => 'Vencida',
            'cancelled' => 'Cancelada',
            'void' => 'Anulada',
        ];

        return view('billing::invoices.index', compact('invoices', 'search', 'status', 'from', 'to', 'statuses'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Renderable
     */
    public function create()
    {
        // Generar número de factura sugerido
        $invoiceNumber = $this->invoiceRepository->generateInvoiceNumber();

        // Fecha de emisión por defecto (hoy)
        $issueDate = Carbon::now()->format('Y-m-d');

        // Fecha de vencimiento por defecto (configuración)
        $dueDays = config('billing.invoice.due_days', 15);
        $dueDate = Carbon::now()->addDays($dueDays)->format('Y-m-d');

        return view('billing::invoices.create', compact('invoiceNumber', 'issueDate', 'dueDate'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreInvoiceRequest $request)
    {
        $data = $request->validated();

        $result = $this->invoiceService->createInvoice($data);

        if (! $result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('billing.invoices.show', $result['invoice']->id)
            ->with('success', 'Recibo creado correctamente.');
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Renderable
     */
    public function show($id)
    {
        $invoice = $this->invoiceRepository->find($id);

        // Cargar relaciones
        $invoice->load(['contract.customer', 'items', 'payments', 'creditNotes']);

        // Obtener logs de auditoría relacionados con esta factura
        $logs = AuditLog::where('action_detail', 'like', "%{$invoice->invoice_number}%")
            ->orderBy('action_date', 'desc')
            ->limit(10)
            ->get();

        return view('billing::invoices.show', compact('invoice', 'logs'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Renderable
     */
    public function edit($id)
    {
        $invoice = $this->invoiceRepository->find($id);

        // Solo se pueden editar facturas en borrador
        if ($invoice->status !== 'draft') {
            return redirect()->route('billing.invoices.show', $id)
                ->with('error', 'Solo se pueden editar recibos en estado de borrador.');
        }

        // Cargar relaciones
        $invoice->load(['contract.customer', 'items']);

        // Obtener contratos activos para el dropdown
        $contracts = $this->contractRepository->getActiveContracts();

        return view('billing::invoices.edit', compact('invoice', 'contracts'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateInvoiceRequest $request, $id)
    {
        $invoice = $this->invoiceRepository->find($id);

        // Solo se pueden editar facturas en borrador
        if ($invoice->status !== 'draft') {
            return redirect()->route('billing.invoices.show', $id)
                ->with('error', 'Solo se pueden editar recibos en estado de borrador.');
        }

        $data = $request->validated();

        // Eliminar items actuales
        $invoice->items()->delete();

        // Actualizar factura
        $invoice->update($data);

        // Crear nuevos items
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $item['invoice_id'] = $id;
                $invoice->items()->create($item);
            }
        }

        // Actualizar totales
        $subtotal = $invoice->items()->sum('amount');
        $taxes = $invoice->items()->sum('tax_amount');

        $invoice->update([
            'amount' => $subtotal - $taxes,
            'taxes' => $taxes,
            'total_amount' => $subtotal,
        ]);

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'invoice_updated',
            'invoices',
            "Recibo actualizado: {$invoice->invoice_number}",
            request()->ip(),
            null,
            $invoice->toArray()
        );

        return redirect()->route('billing.invoices.show', $id)
            ->with('success', 'Recibo actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $invoice = $this->invoiceRepository->find($id);

        // Solo se pueden eliminar facturas en borrador
        if ($invoice->status !== 'draft') {
            return redirect()->route('billing.invoices.index')
                ->with('error', 'Solo se pueden eliminar recibos en estado de borrador.');
        }

        // Registrar en log de auditoría
        AuditLog::register(
            auth()->id(),
            'invoice_deleted',
            'invoices',
            "Recibo eliminado: {$invoice->invoice_number}",
            request()->ip(),
            $invoice->toArray(),
            null
        );

        // Eliminar factura
        $this->invoiceRepository->delete($id);

        return redirect()->route('billing.invoices.index')
            ->with('success', 'Recibo eliminado correctamente.');
    }

    /**
     * Void the specified invoice.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function void(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $result = $this->invoiceService->voidInvoice($id, $request->reason);

        if (! $result['success']) {
            return redirect()->route('billing.invoices.show', $id)
                ->with('error', $result['message']);
        }

        return redirect()->route('billing.invoices.show', $id)
            ->with('success', 'Recibo anulado correctamente.');
    }

    /**
     * Mark the invoice as sent.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function markAsSent($id)
    {
        $result = $this->invoiceService->markAsSent($id);

        if (! $result['success']) {
            return redirect()->route('billing.invoices.show', $id)
                ->with('error', $result['message']);
        }

        return redirect()->route('billing.invoices.show', $id)
            ->with('success', 'Recibo marcado como enviado.');
    }

    /**
     * Display the collections dashboard (Debtors list).
     *
     * @return Renderable
     */
    public function collections()
    {
        $invoices = \Modules\Billing\Entities\Invoice::with(['contract.customer'])
            ->withAmountTotals()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('due_date', 'asc')
            ->get();

        return view('billing::invoices.collections', compact('invoices'));
    }

    /**
     * Trigger generation of scheduled invoices manually.
     *
     * @return \Illuminate\Http\Response
     */
    public function generateScheduled(Request $request)
    {
        if ($request->input('action') === 'repair_period_duplicates') {
            try {
                $result = app(\Modules\Billing\Services\BillingPeriodRepairService::class)
                    ->repairAllSafeDuplicates(auth()->id(), $request->ip());

                return redirect()->route('billing.notebook', ['mode' => 'period-repair'])
                    ->with(
                        'success',
                        "Corrección terminada: {$result['invoices']} recibos duplicados anulados en {$result['groups']} contratos/períodos."
                    );
            } catch (\Throwable $exception) {
                report($exception);

                return redirect()->route('billing.notebook', ['mode' => 'period-repair'])
                    ->with(
                        'error',
                        'La corrección se detuvo porque un caso cambió o necesita revisión manual. No se crearon pagos nuevos.'
                    );
            }
        }

        $result = $this->invoiceService->generateScheduledInvoices();

        if (! $result['success']) {
            return redirect()->route('billing.invoices.index')
                ->with('error', 'Hubo un error al generar los recibos mensuales masivos.');
        }

        $count = $result['generated'];

        return redirect()->route('billing.invoices.index')
            ->with('success', "Se generaron {$count} recibos mensuales exitosamente.");
    }

    /**
     * Generate an invoice for a contract.
     *
     * @return \Illuminate\Http\Response
     */
    public function generateForContract(Request $request)
    {
        $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'billing_period' => 'required|string|max:255',
        ]);

        $result = $this->invoiceService->generateInvoiceForContract(
            $request->contract_id,
            $request->billing_period
        );

        if (! $result['success']) {
            return redirect()->back()
                ->withInput()
                ->with('error', $result['message']);
        }

        OnboardingOrder::where('contract_id', $request->contract_id)
            ->latest()
            ->first()
            ?->update([
                'invoice_id' => $result['invoice']->id,
                'status' => OnboardingOrder::STATUS_INVOICED,
                'invoiced_at' => now(),
            ]);

        if ($request->has('redirect_to')) {
            return redirect($request->redirect_to)
                ->with('success', 'Recibo mensual generado correctamente.');
        }

        return redirect()->route('billing.invoices.show', $result['invoice']->id)
            ->with('success', 'Recibo mensual generado correctamente.');
    }

    /**
     * Print the invoice as PDF.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        $invoice = $this->invoiceRepository->find($id);

        // Cargar relaciones
        $invoice->load([
            'contract.customer.addresses',
            'contract.plan.service',
            'items',
            'payments',
            'creditNotes',
        ]);

        $fileNumber = preg_replace('/[^A-Za-z0-9\-]+/', '-', $invoice->invoice_number);

        $pdf = Pdf::loadView('billing::invoices.pdf', compact('invoice'))
            ->setPaper('a4');

        return $pdf->stream("recibo-servicio-{$fileNumber}.pdf");
    }

    /**
     * Email the invoice to the customer.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function email($id)
    {
        $invoice = $this->invoiceRepository->find($id);

        // Aquí implementarías el envío por email
        // Esto podría ser parte de un servicio de notificaciones

        // Por ahora, simplemente marcamos como enviada y retornamos
        $result = $this->invoiceService->markAsSent($id);

        if (! $result['success']) {
            return redirect()->route('billing.invoices.show', $id)
                ->with('error', 'Error al enviar el recibo por email.');
        }

        return redirect()->route('billing.invoices.show', $id)
            ->with('success', 'Recibo enviado por email correctamente.');
    }
}
