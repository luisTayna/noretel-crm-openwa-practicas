<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Billing\Entities\Payment;
use Modules\Core\Entities\User;
use Modules\Core\Services\NotificationService;
use Modules\Network\Entities\ServiceRequest;
use Modules\Network\Services\RouterService;

class CustomerAPIController extends Controller
{
    protected $routerService;

    public function __construct(RouterService $routerService)
    {
        $this->routerService = $routerService;
    }

    /**
     * Dashboard del cliente
     */
    public function dashboard(Request $request)
    {
        $customer = $this->customerFromRequest($request);

        $contract = $customer->contracts()
            ->where('status', 'active')
            ->with('plan')
            ->first();

        $pendingInvoices = $customer->invoices()
            ->whereIn('invoices.status', ['pending', 'overdue'])
            ->count();

        if (!$contract) {
            return response()->json([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->full_name,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,
                    'identity_document' => $customer->identity_document,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
                'service' => null,
                'pending_invoices' => $pendingInvoices,
                'message' => 'No tiene servicio activo'
            ]);
        }

        $plan = $contract->plan;
        $downloadSpeed = $plan?->download_speed ?? $plan?->speed ?? null;
        $uploadSpeed = $plan?->upload_speed ?? null;

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->full_name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'identity_document' => $customer->identity_document,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
            'service' => [
                'contract_number' => 'CTR-' . str_pad((string) $contract->id, 6, '0', STR_PAD_LEFT),
                'plan_name' => $plan?->name,
                'speed' => $downloadSpeed,
                'download_speed' => $downloadSpeed,
                'upload_speed' => $uploadSpeed,
                'price' => $contract->final_price ?? $plan?->price,
                'status' => $contract->status,
                'next_payment' => $customer->invoices()
                    ->whereIn('invoices.status', ['pending', 'overdue'])
                    ->orderBy('due_date')
                    ->value('due_date'),
            ],
            'pending_invoices' => $pendingInvoices,
        ]);
    }

    /**
     * Reiniciar línea del cliente
     */
    public function restartLine(Request $request)
    {
        $customer = $this->customerFromRequest($request);

        ServiceRequest::create([
            'customer_id' => $customer->id,
            'contract_id' => $customer->contracts()->where('status', 'active')->value('id'),
            'type' => 'router_reboot',
            'priority' => 'medium',
            'status' => 'pending',
            'description' => 'Solicitud de reinicio de línea generada desde el portal del cliente.',
            'customer_notes' => 'El cliente solicitó reiniciar su línea desde Mi Cuenta.',
            'is_automated' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud registrada. Soporte revisará el reinicio de tu línea.',
        ]);
    }

    /**
     * Subir comprobante de pago
     */
    public function uploadPayment(Request $request)
    {
        $request->validate([
            'voucher' => 'required_without:receipt|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'receipt' => 'required_without:voucher|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:yape,plin,transferencia,efectivo'
        ]);

        $customer = $this->customerFromRequest($request);

        try {
            $invoice = $customer->invoices()
                ->whereIn('invoices.status', ['pending', 'overdue'])
                ->orderBy('due_date')
                ->first();

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes boletas pendientes para asociar este comprobante.',
                ], 422);
            }

            $file = $request->file('voucher') ?: $request->file('receipt');
            $path = $file->store('payment-receipts', 'public');

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $request->amount,
                'payment_method' => $request->method,
                'status' => 'pending',
                'reference' => 'PORTAL-' . now()->format('YmdHis'),
                'payment_gateway' => 'customer_portal',
                'payment_details' => [
                    'receipt_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'customer_id' => $customer->id,
                ],
                'payment_date' => now(),
                'notes' => 'Comprobante subido por el cliente',
            ]);

            $this->notifyAdminsOfPendingPayment($customer, $payment);

            return response()->json([
                'success' => true,
                'message' => 'Comprobante recibido. Verificaremos tu pago pronto.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al subir comprobante de pago (portal): ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo procesar el comprobante. Intenta nuevamente en unos minutos.'
            ], 500);
        }
    }

    /**
     * Notify admin and sales (payments.edit) users that a customer uploaded
     * a payment receipt awaiting approval. A notification failure is logged
     * and must never fail the upload itself.
     *
     * @param \Modules\Customer\Entities\Customer $customer
     * @param Payment $payment
     * @return void
     */
    protected function notifyAdminsOfPendingPayment($customer, Payment $payment)
    {
        try {
            $recipients = User::role(['admin', 'sales'])
                ->pluck('email')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($recipients)) {
                return;
            }

            $customerName = trim($customer->first_name . ' ' . $customer->last_name);
            $content = sprintf(
                'El cliente %s subio un comprobante de pago de S/ %s (pago #%d) pendiente de aprobacion.',
                $customerName !== '' ? $customerName : "#{$customer->id}",
                number_format((float) $payment->amount, 2),
                $payment->id
            );

            app(NotificationService::class)->notifySystemUsers(
                $recipients,
                'payment_pending_approval',
                $content,
                [
                    'payment_id' => $payment->id,
                    'customer_id' => $customer->id,
                    'url' => route('billing.payments.show', $payment->id),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Error notificando administradores sobre pago pendiente: ' . $e->getMessage());
        }
    }

    public function invoices(Request $request)
    {
        $customer = $this->customerFromRequest($request);

        $invoices = $customer->invoices()
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'period' => $invoice->billing_period,
                'amount' => (float) $invoice->total_amount,
                'due_date' => $invoice->due_date,
                'status' => $invoice->status,
            ]);

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function payments(Request $request)
    {
        $customer = $this->customerFromRequest($request);
        $invoiceIds = $customer->invoices()->pluck('invoices.id');

        $payments = Payment::whereIn('invoice_id', $invoiceIds)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'date' => $payment->payment_date,
                'amount' => (float) $payment->amount,
                'method' => $payment->payment_method,
                'status' => $payment->status === 'completed' ? 'confirmed' : $payment->status,
                'reference' => $payment->reference,
            ]);

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function tickets(Request $request)
    {
        $customer = $this->customerFromRequest($request);

        $tickets = ServiceRequest::where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($ticket) => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->customer_notes ?: $ticket->type_label,
                'type' => $ticket->type,
                'priority' => $ticket->priority,
                'status' => match ($ticket->status) {
                    'pending' => 'open',
                    'completed' => 'resolved',
                    'cancelled', 'failed' => 'closed',
                    default => $ticket->status,
                },
                'description' => $ticket->description,
                'created_at' => $ticket->created_at,
            ]);

        return response()->json(['success' => true, 'data' => $tickets]);
    }

    public function createTicket(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'type' => 'required|string|in:technical,billing,general',
            'priority' => 'required|string|in:low,medium,high,critical',
            'description' => 'required|string|max:2000',
        ]);

        $customer = $this->customerFromRequest($request);

        $ticket = ServiceRequest::create([
            'customer_id' => $customer->id,
            'contract_id' => $customer->contracts()->where('status', 'active')->value('id'),
            'type' => match ($data['type']) {
                'technical' => 'connection_issue',
                default => 'other',
            },
            'priority' => $data['priority'],
            'status' => 'pending',
            'description' => $data['description'],
            'customer_notes' => $data['title'],
            'is_automated' => false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->customer_notes,
                'type' => $ticket->type,
                'priority' => $ticket->priority,
                'status' => 'open',
                'description' => $ticket->description,
                'created_at' => $ticket->created_at,
            ],
        ], 201);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $customer = $this->customerFromRequest($request);
        $customer->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'data' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'identity_document' => $customer->identity_document,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'customer_status' => $customer->customer_status,
            ],
        ]);
    }

    /**
     * Sincronizar clasificación BI desde el servicio Python en AlmaLinux.
     */
    public function syncBiClassification(Request $request)
    {
        $token = $request->header('X-BI-TOKEN');
        $expectedToken = config('services.bi.token');

        if (!is_string($expectedToken) || $expectedToken === '' || !is_string($token) || $token === '' || !hash_equals($expectedToken, $token)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized BI token'], 401);
        }

        $customers = $request->input('customers', []);
        $now = \Carbon\Carbon::now();
        $updated = 0;

        foreach ($customers as $item) {
            if (isset($item['customer_id'], $item['bi_segment'])) {
                \Illuminate\Support\Facades\DB::table('customers')
                    ->where('id', $item['customer_id'])
                    ->update([
                        'bi_segment' => $item['bi_segment'],
                        'bi_score' => $item['bi_score'] ?? null,
                        'bi_updated_at' => $now,
                    ]);
                $updated++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Sincronizados {$updated} clientes correctamente.",
            'updated_count' => $updated,
        ]);
    }

    private function customerFromRequest(Request $request)
    {
        $customer = $request->user()?->customer;

        abort_if(!$customer, 404, 'Cliente no encontrado');

        return $customer;
    }
}
