@extends('operations::layouts.master')

@section('title', 'Flujo operativo')
@section('page-title', 'Operaciones · Flujo operativo')

@push('styles')
<style>
    /* Noretel Next-Gen Styles for Operations Flow */
    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03);
    }
    
    .glass-card .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 700;
        color: #1e293b;
        padding: 1.25rem 1.5rem;
    }

    /* Soft Badges */
    .badge-soft {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.3px;
    }
    .badge-soft-primary { background: #eff6ff; color: #2563eb; }
    .badge-soft-success { background: #ecfdf5; color: #059669; }
    .badge-soft-warning { background: #fffbeb; color: #d97706; }
    .badge-soft-danger { background: #fef2f2; color: #dc2626; }
    .badge-soft-info { background: #ecfeff; color: #0891b2; }
    .badge-soft-secondary { background: #f8fafc; color: #64748b; }

    /* Timeline Next-Gen */
    .operation-step {
        border-radius: 16px;
        padding: 1.25rem;
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.8);
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    
    .operation-step::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 6px;
        background: #cbd5e1;
    }

    .operation-step.done { 
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        border-color: #bbf7d0;
    }
    .operation-step.done::before { background: #10b981; }
    
    .operation-step.current { 
        background: #ffffff;
        border-color: #bfdbfe;
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.1);
        transform: translateY(-2px);
    }
    .operation-step.current::before { background: #3b82f6; }
    
    .operation-step.pending { 
        background: transparent;
        border-color: rgba(203, 213, 225, 0.5);
        opacity: 0.7;
    }

    .operation-step.disabled {
        background: #f8fafc;
        border-color: rgba(203, 213, 225, 0.7);
        opacity: .75;
    }

    .operation-step.disabled::before { background: #94a3b8; }

    .operation-step .step-icon {
        width: 2.75rem;
        height: 2.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        font-size: 1.2rem;
    }
    
    .operation-step.done .step-icon { background: #d1fae5; color: #059669; }
    .operation-step.current .step-icon { background: #eff6ff; color: #2563eb; }
    .operation-step.pending .step-icon { background: #f1f5f9; color: #94a3b8; }
    .operation-step.disabled .step-icon { background: #e2e8f0; color: #64748b; }

    /* Siguiente Acción Card (Gradient) */
    .next-action-card {
        border-radius: 20px;
        border: none;
        overflow: hidden;
        color: white;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }
    
    /* Dependiendo del estado, coloreamos la tarjeta de siguiente accion con gradientes vibrantes Premium */
    .next-action-primary { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
    .next-action-success { background: linear-gradient(135deg, #10b981 0%, #065f46 100%); }
    .next-action-warning { background: linear-gradient(135deg, #f59e0b 0%, #92400e 100%); }
    .next-action-danger  { background: linear-gradient(135deg, #ef4444 0%, #991b1b 100%); }
    .next-action-info    { background: linear-gradient(135deg, #0ea5e9 0%, #075985 100%); }
    /* Secondary se usa para "Sin Contrato". Le daremos el color de acento morado vibrante en lugar de gris aburrido */
    .next-action-secondary { background: linear-gradient(135deg, #8b5cf6 0%, #4c1d95 100%); }

    /* Estilo del Botón de la Tarjeta Siguiente Acción */
    .btn-action-glow {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        color: white;
        border-radius: 12px;
        font-weight: 600;
        padding: 0.75rem 1rem;
        transition: all 0.2s;
    }
    .btn-action-glow:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        transform: translateY(-2px);
    }

    /* Tablas Transparentes */
    .table-glass {
        --bs-table-bg: transparent;
        margin-bottom: 0;
    }
    .table-glass th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        border-bottom: 2px solid rgba(0,0,0,0.03);
        background: transparent;
    }
    .table-glass td {
        vertical-align: middle;
        border-bottom: 1px solid rgba(0,0,0,0.03);
        background: transparent;
    }

    .communication-action {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.9rem 1rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.65);
        text-decoration: none;
        color: #1e293b;
        transition: all 0.18s ease;
    }

    .communication-action:hover {
        transform: translateY(-1px);
        border-color: #bbf7d0;
        background: #f0fdf4;
        color: #0f172a;
    }

    .communication-action .communication-icon {
        width: 2.4rem;
        height: 2.4rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: #dcfce7;
        color: #16a34a;
        flex: 0 0 auto;
    }

    .communication-action.disabled-action {
        opacity: .55;
        pointer-events: none;
        background: rgba(248, 250, 252, .7);
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold" style="color: #0f172a;">{{ $customer->full_name }}</h1>
            <p class="text-muted mb-0 fw-medium">
                {{ $customer->identity_document ?: 'Sin documento' }}
                <span class="mx-1 text-slate-300">|</span> {{ $customer->phone ?: 'Sin teléfono' }}
                <span class="mx-1 text-slate-300">|</span> {{ $customer->email ?: 'Sin correo' }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('customer.customers.show', $customer->id) }}" class="btn btn-light shadow-sm" style="border-radius: 12px; font-weight: 600; color: #475569;">
                <i class="fas fa-user me-1 text-primary"></i> Ver ficha
            </a>
            <a href="{{ route('operations.onboarding') }}" class="btn shadow-sm text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 12px; border: none; font-weight: 600;">
                <i class="fas fa-arrow-left me-1"></i> Regresar
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card glass-card border-0 mb-4">
                <div class="card-header">
                    <i class="fas fa-route me-2 text-primary"></i> Timeline operativo
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach($flow['steps'] as $step)
                            <div class="col-md-6">
                                <div class="operation-step {{ $step['state'] }} h-100">
                                    <div class="d-flex gap-3">
                                        <div class="step-icon shadow-sm">
                                            <i class="fas {{ $step['icon'] }}"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1 fw-bold" style="color: #1e293b;">{{ $step['label'] }}</h6>
                                            <p class="text-muted mb-0 small">{{ $step['description'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card glass-card border-0" id="contract-panel">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-2 text-primary"></i> Auditoría reciente
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-glass mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Fecha</th>
                                    <th>Módulo</th>
                                    <th>Acción</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($auditLogs as $log)
                                    <tr>
                                        <td class="ps-4 text-muted small">{{ optional($log->action_date)->format('d/m/Y H:i') }}</td>
                                        <td><span class="badge bg-light text-secondary rounded-pill border">{{ $log->module }}</span></td>
                                        <td class="fw-medium text-dark">{{ $log->action_type }}</td>
                                        <td class="text-muted small">{{ $log->action_detail }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="fas fa-box-open fa-2x mb-2 opacity-25"></i><br>
                                            Sin auditoría relacionada todavía.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @php
                $statusMap = [
                    'primary' => 'next-action-primary',
                    'success' => 'next-action-success',
                    'warning' => 'next-action-warning',
                    'danger' => 'next-action-danger',
                    'info' => 'next-action-info',
                    'secondary' => 'next-action-secondary'
                ];
                $actionClass = $statusMap[$flow['status_class']] ?? 'next-action-secondary';
                
                $softBadgeMap = [
                    'primary' => 'badge-soft-primary',
                    'success' => 'badge-soft-success',
                    'warning' => 'badge-soft-warning',
                    'danger' => 'badge-soft-danger',
                    'info' => 'badge-soft-info',
                    'secondary' => 'badge-soft-secondary'
                ];
                $badgeClass = $softBadgeMap[$flow['status_class']] ?? 'badge-soft-secondary';

                $order = $flow['onboarding_order'] ?? null;
                $contract = $flow['contract'];
                $invoice = $flow['invoice'];
                $installation = $flow['installation'] ?? null;
                $activationRequest = $flow['activation_request'] ?? null;
                $billingSummary = $flow['billing_summary'] ?? ['pending_count' => 0, 'total_pending' => 0, 'paid_until' => null];
                $rawPhone = (string) ($customer->phone ?? '');
                $whatsappPhone = preg_replace('/\D+/', '', $rawPhone);

                if (strlen($whatsappPhone) === 9 && str_starts_with($whatsappPhone, '9')) {
                    $whatsappPhone = '51' . $whatsappPhone;
                }

                $hasWhatsappPhone = strlen($whatsappPhone) >= 10;
                $customerName = $customer->full_name;
                $planName = optional(optional($contract)->plan)->name ?: optional(optional($order)->plan)->name ?: 'Plan contratado';
                $contractStartDate = optional(optional($contract)->start_date)->format('d/m/Y') ?: optional(optional($contract)->created_at)->format('d/m/Y') ?: 'Sin fecha registrada';
                $invoiceDueDate = optional(optional($invoice)->due_date)->format('d/m/Y') ?: 'Sin vencimiento';

                $contractMessage = $contract
                    ? "Hola {$customerName}, le compartimos el PDF de su contrato de servicio N° {$contract->id}.\n\nPlan: {$planName}\nFecha de inicio: {$contractStartDate}\n\nPor favor revise el documento adjunto y consérvelo como constancia de las condiciones del servicio.\n\nGracias por confiar en NorETEL."
                    : '';

                $invoiceMessage = $invoice
                    ? "Hola {$customerName}, le compartimos el PDF de su recibo de servicio {$invoice->invoice_number} por el servicio contratado.\n\nMonto: S/ " . number_format((float) $invoice->total_amount, 2) . "\nVencimiento: {$invoiceDueDate}\n\nPor favor revise el documento adjunto. Gracias por su preferencia."
                    : '';

                $paymentReminderMessage = $invoice && ($billingSummary['pending_count'] ?? 0) > 1
                    ? "Hola {$customerName}, le recordamos que tiene {$billingSummary['pending_count']} recibos pendientes por un total de S/ " . number_format((float) $billingSummary['total_pending'], 2) . ".\n\nPuede realizar su pago por los medios disponibles. Gracias."
                    : ($invoice
                        ? "Hola {$customerName}, le recordamos que tiene un saldo pendiente de S/ " . number_format((float) $invoice->pending_amount, 2) . " correspondiente al recibo {$invoice->invoice_number}.\n\nFecha de vencimiento: {$invoiceDueDate}\n\nPuede realizar su pago por los medios disponibles. Gracias."
                        : '');

                $contractWhatsappUrl = $hasWhatsappPhone && $contract ? 'https://wa.me/' . $whatsappPhone . '?text=' . rawurlencode($contractMessage) : null;
                $invoiceWhatsappUrl = $hasWhatsappPhone && $invoice ? 'https://wa.me/' . $whatsappPhone . '?text=' . rawurlencode($invoiceMessage) : null;
                $reminderWhatsappUrl = $hasWhatsappPhone && $invoice ? 'https://wa.me/' . $whatsappPhone . '?text=' . rawurlencode($paymentReminderMessage) : null;
                $contractPdfUrl = $contract ? route('contract.contracts.pdf', $contract->id) : null;
                $invoicePdfUrl = $invoice ? route('billing.invoices.print', $invoice->id) : null;
                $canSendReminder = $invoice && in_array($invoice->status, ['pending', 'partial', 'overdue']);
            @endphp
            
            <div class="card next-action-card {{ $actionClass }} mb-4">
                <div class="card-header border-0 bg-transparent pt-4 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small fw-semibold text-white-50 text-uppercase tracking-wider">
                            <i class="fas {{ $flow['next_action']['icon'] }} me-1"></i> Siguiente acción
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <span class="badge bg-white text-dark rounded-pill px-3 py-2 mb-3 shadow-sm fw-bold">
                        <i class="fas fa-circle me-1" style="font-size:0.5rem; color:var(--bs-{{$flow['status_class']}})"></i> {{ $flow['status_label'] }}
                    </span>
                    <h4 class="fw-bold mb-2">{{ $flow['next_action']['title'] }}</h4>
                    <p class="text-white-50 small mb-4">{{ $flow['next_action']['description'] }}</p>

                    @if($flow['status_key'] === 'up_to_date' && $contract && $contract->status === 'active')
                        <a href="{{ route('billing.payments.create', ['contract_id' => $contract->id, 'mode' => 'multi_month']) }}" class="btn btn-action-glow w-100 mb-2" style="background: rgba(255, 255, 255, 0.35); font-weight: 700;">
                            <i class="fas fa-calendar-plus me-1"></i>
                            Registrar pago adelantado
                        </a>
                    @endif

                    @if($flow['next_action']['method'] === 'POST')
                        <form method="POST" action="{{ $flow['next_action']['route'] }}">
                            @csrf
                            @foreach(($flow['next_action']['payload'] ?? []) as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <button type="submit" class="btn btn-action-glow w-100">
                                <i class="fas {{ $flow['next_action']['icon'] }} me-1"></i>
                                Ejecutar: {{ $flow['next_action']['title'] }}
                            </button>
                        </form>
                    @else
                        <a href="{{ $flow['next_action']['route'] }}" class="btn btn-action-glow w-100">
                            <i class="fas {{ $flow['next_action']['icon'] }} me-1"></i>
                            Ir a: {{ $flow['next_action']['title'] }}
                        </a>
                    @endif
                </div>
            </div>

            @if($order)
                <div class="card glass-card border-0 mb-4" id="activation-panel">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-network-wired me-2 text-secondary"></i> Habilitación de equipo</span>
                        <span class="badge bg-secondary rounded-pill">Próximamente</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light border mb-3">
                            <i class="fas fa-lock me-1 text-secondary"></i>
                            Esta etapa queda visible para el flujo técnico, pero está deshabilitada por ahora. Después de completar la instalación, el siguiente paso operativo es <strong>Contrato día 30</strong>.
                        </div>
                        <button type="button" class="btn btn-light border w-100" disabled>
                            <i class="fas fa-network-wired me-1"></i> Habilitar equipo deshabilitado
                        </button>
                    </div>
                </div>
            @endif

            <div class="card glass-card border-0 mb-4">
                <div class="card-header">
                    <i class="fab fa-whatsapp me-2 text-success"></i> Comunicaciones
                </div>
                <div class="card-body">
                    @if(!$hasWhatsappPhone)
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Cliente sin teléfono registrado para WhatsApp.
                        </div>
                    @endif

                    <div class="d-grid gap-3">
                        @if($contract)
                            <a
                                href="{{ $contractWhatsappUrl ?: '#' }}"
                                class="communication-action {{ !$contractWhatsappUrl ? 'disabled-action' : '' }}"
                                @if($contractPdfUrl) onclick="window.open('{{ $contractPdfUrl }}', '_blank')" @endif
                                target="_blank"
                                rel="noopener"
                            >
                                <span class="d-flex align-items-center gap-3">
                                    <span class="communication-icon"><i class="fab fa-whatsapp"></i></span>
                                    <span>
                                        <span class="d-block fw-bold">Enviar contrato PDF</span>
                                        <small class="text-muted">Abre el PDF y prepara WhatsApp</small>
                                    </span>
                                </span>
                                <i class="fas fa-external-link-alt text-muted"></i>
                            </a>

                            <a href="{{ $contractPdfUrl }}" class="btn btn-light border text-start" style="border-radius: 14px;" target="_blank" rel="noopener">
                                <i class="fas fa-file-pdf me-2 text-danger"></i> Descargar contrato PDF
                            </a>

                            @if($contract->status === 'active')
                                <a href="{{ route('billing.payments.create', ['contract_id' => $contract->id, 'mode' => 'multi_month']) }}" class="btn btn-success text-start" style="border-radius: 14px;">
                                    <i class="fas fa-calendar-plus me-2"></i> Registrar pago adelantado
                                </a>
                            @endif
                        @endif

                        @if($invoice)
                            <a
                                href="{{ $invoiceWhatsappUrl ?: '#' }}"
                                class="communication-action {{ !$invoiceWhatsappUrl ? 'disabled-action' : '' }}"
                                @if($invoicePdfUrl) onclick="window.open('{{ $invoicePdfUrl }}', '_blank')" @endif
                                target="_blank"
                                rel="noopener"
                            >
                                <span class="d-flex align-items-center gap-3">
                                    <span class="communication-icon"><i class="fab fa-whatsapp"></i></span>
                                    <span>
                                        <span class="d-block fw-bold">Enviar recibo PDF</span>
                                        <small class="text-muted">Abre el PDF y prepara WhatsApp · S/ {{ number_format((float) $invoice->total_amount, 2) }}</small>
                                    </span>
                                </span>
                                <i class="fas fa-external-link-alt text-muted"></i>
                            </a>

                            @if($canSendReminder)
                                <a
                                    href="{{ $reminderWhatsappUrl ?: '#' }}"
                                    class="communication-action {{ !$reminderWhatsappUrl ? 'disabled-action' : '' }}"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <span class="d-flex align-items-center gap-3">
                                        <span class="communication-icon"><i class="fab fa-whatsapp"></i></span>
                                        <span>
                                            <span class="d-block fw-bold">Recordatorio de pago</span>
                                            <small class="text-muted">Saldo pendiente: S/ {{ number_format((float) ($billingSummary['total_pending'] ?: $invoice->pending_amount), 2) }}</small>
                                        </span>
                                    </span>
                                    <i class="fas fa-external-link-alt text-muted"></i>
                                </a>
                            @endif

                            <a href="{{ $invoicePdfUrl }}" class="btn btn-light border text-start" style="border-radius: 14px;" target="_blank" rel="noopener">
                                <i class="fas fa-file-pdf me-2 text-danger"></i> Descargar recibo PDF
                            </a>
                        @endif

                        @if(!$contract && !$invoice)
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-comment-slash fa-2x mb-2 opacity-25"></i>
                                <p class="mb-0 small">Aún no hay documentos para comunicar.</p>
                            </div>
                        @endif
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        <i class="fas fa-info-circle me-1"></i>
                        WhatsApp se abre con el mensaje preparado. El PDF se abre en otra pestaña para descargarlo y adjuntarlo manualmente.
                    </p>
                </div>
            </div>

            <div class="card glass-card border-0">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2 text-primary"></i> Resumen
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted fw-medium small mb-3">Orden de instalación</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">
                            @if($order)
                                {{ $order->order_number }} <span class="badge bg-light text-dark border ms-1">{{ $order->status_label }}</span>
                            @else
                                <span class="text-muted">Sin orden</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted fw-medium small mb-3">Contrato</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">
                            @if($flow['contract'])
                                #{{ $flow['contract']->id }} <span class="badge bg-light text-dark border ms-1">{{ $flow['contract']->status }}</span>
                            @else
                                <span class="text-muted">Sin contrato</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted fw-medium small mb-3">Plan</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">{{ optional(optional($flow['contract'])->plan)->name ?: optional(optional($order)->plan)->name ?: 'Sin plan' }}</dd>

                        <dt class="col-5 text-muted fw-medium small mb-3">Instalación</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">
                            @if(optional($flow['installation'])->status)
                                <span class="badge-soft badge-soft-primary"><i class="fas fa-tools me-1"></i> {{ $flow['installation']->status }}</span>
                            @else
                                <span class="text-muted">Sin instalación</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted fw-medium small mb-3">Habilitación</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">
                            @if($order && $order->activation_data)
                                <span class="badge-soft badge-soft-info">
                                    IP {{ $order->activation_data['assigned_ip'] ?? 'N/D' }} · VLAN {{ $order->activation_data['vlan'] ?? 'N/D' }}
                                </span>
                            @elseif($activationRequest)
                                <span class="badge-soft badge-soft-warning">{{ $activationRequest->status }}</span>
                            @else
                                <span class="text-muted">Deshabilitada por ahora</span>
                            @endif
                        </dd>

                        <dt class="col-5 text-muted fw-medium small mb-3">Recibo</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">{{ optional($flow['invoice'])->invoice_number ?: 'Sin recibo' }}</dd>

                        <dt class="col-5 text-muted fw-medium small mb-3">Meses pendientes</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">{{ $billingSummary['pending_count'] ?? 0 }}</dd>

                        <dt class="col-5 text-muted fw-medium small mb-3">Pagado hasta</dt>
                        <dd class="col-7 text-dark fw-semibold mb-3">{{ $billingSummary['paid_until'] ?? 'Sin adelantos' }}</dd>

                        <dt class="col-5 text-muted fw-medium small mb-0">Saldo total</dt>
                        <dd class="col-7 text-danger fw-bold mb-0" style="font-size: 1.1rem;">
                            S/ {{ number_format((float) ($billingSummary['total_pending'] ?? 0), 2) }}
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
