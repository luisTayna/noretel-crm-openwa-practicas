@extends('core::layouts.master')

@section('title', 'Detalle de Pago')
@section('page-title', 'Detalle de Pago')

@section('content')
@php
    $appliedInvoices = collect(data_get($payment->payment_details, 'applied_invoices', []))
        ->filter(fn ($item) => is_array($item) && !empty($item['invoice_id']))
        ->values();
    $hasAppliedInvoices = $appliedInvoices->isNotEmpty();
    $displayAmount = $hasAppliedInvoices
        ? round((float) $appliedInvoices->sum(fn ($item) => (float) data_get($item, 'amount', 0)), 2)
        : round((float) $payment->amount, 2);
    $creditAmount = round((float) data_get($payment->payment_details, 'credit_amount', 0), 2);
    $originalYapeAmount = round((float) data_get($payment->payment_details, 'original_yape_amount', $displayAmount + $creditAmount), 2);

    $cleanPeriodHelper = function (?string $billingPeriod) {
        $billingPeriod = trim((string) $billingPeriod);
        if ($billingPeriod === '') {
            return '';
        }
        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $billingPeriod, $matches, PREG_SET_ORDER)) {
            try {
                $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
                $day = (int) $targetMatch[1];
                $month = (int) $targetMatch[2];
                $year = (int) $targetMatch[3];
                $date = \Carbon\Carbon::create($year, $month, $day);
                $monthName = ucfirst($date->locale('es')->translatedFormat('F'));
                return "{$monthName} {$year}";
            } catch (\Throwable) {
                return $billingPeriod;
            }
        }
        return $billingPeriod;
    };
@endphp
<div class="row">
    <div class="col-md-8">
        <!-- Información del Pago -->
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-money-bill-wave text-primary me-2"></i> Pago #{{ $payment->id }}
                        @if($payment->status == 'completed')
                            <span class="badge bg-success ms-2">Completado</span>
                        @elseif($payment->status == 'pending')
                            <span class="badge bg-warning ms-2">Pendiente</span>
                        @elseif($payment->status == 'failed')
                            <span class="badge bg-danger ms-2">Fallido</span>
                        @elseif($payment->status == 'refunded')
                            <span class="badge bg-secondary ms-2">Anulado</span>
                        @endif
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('customer.customers.show', $payment->invoice->contract->customer->id) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                            <i class="fas fa-arrow-left me-1"></i> Volver al detalle del cliente
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Acciones
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                            @if($payment->status == 'completed')
                            <li>
                                <a class="dropdown-item" href="{{ route('billing.payments.print', $payment->id) }}" target="_blank">
                                    <i class="fas fa-print me-2"></i> Imprimir Recibo
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#sendWhatsAppModal">
                                    <i class="fab fa-whatsapp me-2 text-success"></i> Enviar a WhatsApp
                                </a>
                            </li>
                            @endif
                            @if($payment->status == 'pending')
                            <li>
                                <a class="dropdown-item" href="{{ route('billing.payments.edit', $payment->id) }}">
                                    <i class="fas fa-edit me-2"></i> Editar
                                </a>
                            </li>
                            @endif
                            @if($payment->status == 'completed' && optional(auth()->user())->can('payments.edit'))
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#voidPaymentModal">
                                    <i class="fas fa-ban me-2"></i> Anular pago
                                </a>
                            </li>
                            @endif
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-uppercase text-muted">Información del Pago</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Monto:</strong></td>
                                <td>S/ {{ number_format($displayAmount, 2) }}</td>
                            </tr>
                            @if($creditAmount > 0)
                            <tr>
                                <td><strong>Total recibido:</strong></td>
                                <td>S/ {{ number_format($originalYapeAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Aplicado a deuda:</strong></td>
                                <td>S/ {{ number_format((float) data_get($payment->payment_details, 'applied_amount', $displayAmount), 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Saldo a favor:</strong></td>
                                <td class="text-success fw-bold">S/ {{ number_format($creditAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Movimiento saldo:</strong></td>
                                <td>#{{ data_get($payment->payment_details, 'credit_movement_id', '-') }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td><strong>Fecha:</strong></td>
                                <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <td><strong>Método:</strong></td>
                                <td>{{ $payment->payment_method_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Referencia:</strong></td>
                                <td>{{ $payment->reference ?? '-' }}</td>
                            </tr>
                            @if($payment->payment_method === 'yape' && data_get($payment->payment_details, 'yape_intent_id'))
                            <tr>
                                <td><strong>Validación Yape:</strong></td>
                                <td>
                                    <a href="{{ route('billing.yape.show', data_get($payment->payment_details, 'yape_intent_id')) }}">
                                        Ver WhatsApp + receptor
                                    </a>
                                </td>
                            </tr>
                            @elseif($payment->payment_method === 'yape' && data_get($payment->payment_details, 'yape_voucher_url'))
                            <tr>
                                <td><strong>Evidencia Yape:</strong></td>
                                <td>
                                    <a href="{{ data_get($payment->payment_details, 'yape_voucher_url') }}" target="_blank" rel="noopener">
                                        Ver captura / voucher
                                    </a>
                                </td>
                            </tr>
                            @endif
                            @if($payment->transaction_id)
                            <tr>
                                <td><strong>ID Transacción:</strong></td>
                                <td>{{ $payment->transaction_id }}</td>
                            </tr>
                            @endif
                            @if($payment->payment_gateway)
                            <tr>
                                <td><strong>Pasarela:</strong></td>
                                <td>{{ $payment->payment_gateway }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-uppercase text-muted">Recibo asociado</h6>
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Número:</strong></td>
                                <td>
                                    <a href="{{ route('billing.invoices.show', $payment->invoice->id) }}">
                                        {{ $payment->invoice->invoice_number }}
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Cliente:</strong></td>
                                <td>{{ $payment->invoice->contract->customer->full_name }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total Recibo:</strong></td>
                                <td>S/ {{ number_format($payment->invoice->total_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Pagado:</strong></td>
                                <td>S/ {{ number_format($payment->invoice->paid_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Pendiente:</strong></td>
                                <td>S/ {{ number_format($payment->invoice->pending_amount, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($hasAppliedInvoices)
                <div class="mt-4">
                    <h6 class="text-uppercase text-muted">Recibos cubiertos por este pago Yape</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Recibo</th>
                                    <th>Periodo</th>
                                    <th>Vencimiento</th>
                                    <th class="text-end">Monto aplicado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($appliedInvoices as $appliedInvoice)
                                <tr>
                                    <td>
                                        @if(data_get($appliedInvoice, 'invoice_id'))
                                            <a href="{{ route('billing.invoices.show', data_get($appliedInvoice, 'invoice_id')) }}">
                                                {{ data_get($appliedInvoice, 'invoice_number', '#'.data_get($appliedInvoice, 'invoice_id')) }}
                                            </a>
                                        @else
                                            {{ data_get($appliedInvoice, 'invoice_number', '-') }}
                                        @endif
                                    </td>
                                    <td>{{ $cleanPeriodHelper(data_get($appliedInvoice, 'billing_period')) ?: '-' }}</td>
                                    <td>{{ data_get($appliedInvoice, 'due_date', '-') ?: '-' }}</td>
                                    <td class="text-end">S/ {{ number_format((float) data_get($appliedInvoice, 'amount', 0), 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total cubierto</th>
                                    <th class="text-end">S/ {{ number_format($displayAmount, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @endif

                @if($payment->notes)
                <div class="mt-4">
                    <h6 class="text-uppercase text-muted">Notas</h6>
                    <p class="text-muted">{{ $payment->notes }}</p>
                </div>
                @endif

                @if($payment->payment_details)
                <div class="mt-4">
                    <h6 class="text-uppercase text-muted">Detalles Adicionales</h6>
                    <pre class="bg-light p-3 rounded">{{ json_encode($payment->payment_details, JSON_PRETTY_PRINT) }}</pre>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Estado del Pago -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle text-primary me-2"></i> Estado del Pago
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Estado
                        <span>
                            @if($payment->status == 'completed')
                                <span class="badge bg-success">Completado</span>
                            @elseif($payment->status == 'pending')
                                <span class="badge bg-warning">Pendiente</span>
                            @elseif($payment->status == 'failed')
                                <span class="badge bg-danger">Fallido</span>
                            @elseif($payment->status == 'refunded')
                                <span class="badge bg-secondary">Anulado</span>
                            @endif
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Registrado el
                        <span>{{ $payment->created_at->format('d/m/Y H:i') }}</span>
                    </li>
                    @if($payment->user)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Registrado por
                        <span>{{ $payment->user->username }}</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        <!-- Información del Cliente -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user text-primary me-2"></i> Cliente
                </h5>
            </div>
            <div class="card-body">
                <h6>{{ $payment->invoice->contract->customer->full_name }}</h6>
                <p class="mb-1">
                    <i class="fas fa-envelope text-muted me-2"></i> {{ $payment->invoice->contract->customer->email }}<br>
                    <i class="fas fa-phone text-muted me-2"></i> {{ $payment->invoice->contract->customer->phone }}
                </p>
                <hr>
                <div class="d-grid">
                    <a href="{{ route('customer.customers.show', $payment->invoice->contract->customer->id) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-user me-1"></i> Ver Perfil
                    </a>
                </div>
            </div>
        </div>

        <!-- Historial -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history text-primary me-2"></i> Historial
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @foreach($logs as $log)
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">{{ $log->action_type }}</h6>
                            <small>{{ $log->action_date->format('d/m/Y H:i') }}</small>
                        </div>
                        <p class="mb-1">{{ $log->action_detail }}</p>
                        <small>Por: {{ $log->user->username ?? 'Sistema' }}</small>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para anular pago -->
<div class="modal fade" id="voidPaymentModal" tabindex="-1" aria-labelledby="voidPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('billing.payments.void', $payment->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="voidPaymentModalLabel">Anular Pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Esta acción no se puede deshacer y afectará el estado de la recibo asociada.
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motivo de anulación <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Anular Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para enviar recibo por WhatsApp -->
<div class="modal fade" id="sendWhatsAppModal" tabindex="-1" aria-labelledby="sendWhatsAppModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px;">
            <form action="{{ route('billing.payments.send-whatsapp', $payment->id) }}" method="POST" id="sendWhatsAppForm">
                @csrf
                <div class="modal-header border-0 bg-light" style="border-top-left-radius: 15px; border-top-right-radius: 15px;">
                    <h5 class="modal-title fw-bold text-success" id="sendWhatsAppModalLabel">
                        <i class="fab fa-whatsapp me-2"></i> Enviar Recibo por WhatsApp
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 bg-light text-info mb-4" style="border-radius: 10px;">
                        <i class="fas fa-info-circle me-2"></i> Se enviará la constancia de pago en formato <strong>ticket térmico (80mm)</strong> usando la sesión de OpenWA conectada.
                    </div>
                    <div class="mb-3">
                        <label for="whatsapp_phone" class="form-label fw-semibold text-muted">Número de Teléfono (WhatsApp)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0" style="border-radius: 10px 0 0 10px;"><i class="fas fa-phone-alt text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" id="whatsapp_phone" name="phone" 
                                   value="{{ old('phone', $payment->invoice->contract->whatsapp_phone ?: ($payment->invoice->contract->customer->phone ?: $payment->invoice->contract->customer->whatsapp_phone)) }}" 
                                   required placeholder="Ej: 000000000" style="border-radius: 0 10px 10px 0;" pattern="[0-9]{9,20}">
                        </div>
                        <div class="form-text mt-2">Introduce el número con código de país si es necesario (por defecto se asume +51 en Perú).</div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 8px;">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4" id="btnSendWhatsApp" style="border-radius: 8px;">
                        <i class="fas fa-paper-plane me-2"></i> Enviar Recibo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sendModal = document.getElementById('sendWhatsAppModal');
        if (sendModal) {
            document.body.appendChild(sendModal);
        }

        const sendForm = document.getElementById('sendWhatsAppForm');
        if (sendForm) {
            sendForm.addEventListener('submit', function() {
                const btn = document.getElementById('btnSendWhatsApp');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Enviando...';
            });
        }
    });
</script>
@endpush
@endsection
