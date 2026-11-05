@extends('core::layouts.master')

@section('title', 'Detalle de Recibo')
@section('page-title', 'Detalle de Recibo')

@section('content')
@php
    $statusBadgeClass = [
        'draft' => 'bg-secondary',
        'pending' => $invoice->isOverdue() ? 'bg-danger' : 'bg-warning',
        'partial' => 'bg-info',
        'paid' => 'bg-success',
        'void' => 'bg-secondary',
        'cancelled' => 'bg-secondary',
    ][$invoice->status] ?? 'bg-secondary';

    $statusLabel = [
        'draft' => 'Borrador',
        'pending' => $invoice->isOverdue() ? 'Vencida' : 'Pendiente',
        'partial' => 'Pago parcial',
        'paid' => 'Pagada',
        'void' => 'Anulada',
        'cancelled' => 'Cancelada',
    ][$invoice->status] ?? ucfirst($invoice->status);
@endphp

<style>
    .invoice-shell .card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    }

    .invoice-header {
        padding: 1.35rem 1.5rem;
        border-bottom: 1px solid #eef2f7;
        background: #fff;
        border-radius: 18px 18px 0 0;
    }

    .invoice-title {
        display: flex;
        align-items: center;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .invoice-title h5 {
        font-size: 1.25rem;
        font-weight: 800;
        color: #1f2937;
    }

    .invoice-action-bar {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .invoice-alert {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .95rem 1.1rem;
        border: 1px solid #fbbf24;
        border-radius: 14px;
        background: #fffbeb;
        color: #92400e;
    }

    .invoice-info-box {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 1rem;
        background: #fbfdff;
        height: 100%;
    }

    .invoice-info-box h6,
    .invoice-section-title {
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: .75rem;
    }

    .invoice-table th {
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #475569;
        background: #f8fafc;
    }

    .invoice-table td,
    .invoice-table th {
        padding: .9rem .75rem;
        vertical-align: middle;
    }

    .invoice-totals {
        max-width: 360px;
        margin-left: auto;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }

    .invoice-totals .row-total {
        display: flex;
        justify-content: space-between;
        padding: .8rem 1rem;
        border-bottom: 1px solid #eef2f7;
    }

    .invoice-totals .row-total:last-child {
        border-bottom: 0;
    }

    .invoice-totals .grand-total {
        background: #f8fafc;
        font-size: 1.05rem;
        font-weight: 800;
    }

    .invoice-side-card .card-header {
        background: #fff;
        border-bottom: 1px solid #eef2f7;
        padding: 1.1rem 1.25rem;
        border-radius: 18px 18px 0 0;
    }

    .invoice-side-card h5 {
        font-weight: 800;
        color: #1f2937;
    }

    .invoice-meta-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .75rem 0;
        border-bottom: 1px solid #eef2f7;
    }

    .invoice-meta-row:last-child {
        border-bottom: 0;
    }
</style>

<div class="row invoice-shell">
    <div class="col-md-8">
        <!-- Información de recibo -->
        <div class="card mb-4">
            <div class="invoice-header">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div class="invoice-title">
                        <span class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                            <i class="fas fa-file-invoice"></i>
                        </span>
                        <div>
                            <h5 class="mb-1">Recibo {{ $invoice->invoice_number }}</h5>
                            <div class="text-muted small">
                                {{ $invoice->contract->customer->full_name }} · Contrato #{{ $invoice->contract->id }}
                            </div>
                        </div>
                        <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                    </div>

                    <div class="invoice-action-bar">
                        <a href="{{ route('operations.customers.flow', $invoice->contract->customer_id) }}" class="btn btn-info text-white" title="Ver flujo operativo" style="border-radius: 10px; font-weight: 700;">
                            <i class="fas fa-route me-1"></i> Flujo Operativo
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 10px;">
                                Acciones
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="{{ route('billing.invoices.print', $invoice->id) }}" target="_blank">
                                        <i class="fas fa-print me-2"></i> Imprimir
                                    </a>
                                </li>
                                @if(!$invoice->sent)
                                <li>
                                    <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('send-invoice-form').submit();">
                                        <i class="fas fa-envelope me-2"></i> Enviar por correo
                                    </a>
                                    <form id="send-invoice-form" action="{{ route('billing.invoices.email', $invoice->id) }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </li>
                                @endif
                                @if($invoice->status == 'draft')
                                <li>
                                    <a class="dropdown-item" href="{{ route('billing.invoices.edit', $invoice->id) }}">
                                        <i class="fas fa-edit me-2"></i> Editar
                                    </a>
                                </li>
                                @endif
                                @if(in_array($invoice->status, ['pending', 'partial']))
                                <li>
                                    <a class="dropdown-item" href="{{ route('billing.credit-notes.create', ['invoice_id' => $invoice->id]) }}">
                                        <i class="fas fa-receipt me-2"></i> Crear nota de crédito
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" data-bs-target="#voidInvoiceModal">
                                        <i class="fas fa-ban me-2"></i> Anular recibo
                                    </a>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                @if(in_array($invoice->status, ['pending', 'partial', 'overdue']))
                <div class="invoice-alert mb-4">
                    <div>
                        <strong><i class="fas fa-exclamation-circle me-1"></i> Recibo pendiente de pago</strong>
                        <span class="ms-2 text-muted">Continúa la cobranza desde el flujo operativo.</span>
                    </div>
                    <div class="fw-bold fs-5 text-nowrap">S/ {{ number_format($invoice->pending_amount, 2) }}</div>
                </div>
                @endif
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="invoice-info-box">
                            <h6>Emitido a</h6>
                            <p class="mb-1 fw-bold">{{ $invoice->billing_name }}</p>
                            <p class="mb-1 text-muted">{{ $invoice->billing_address ?: 'Dirección no registrada' }}</p>
                            <p class="mb-1">{{ $invoice->billing_document }}</p>
                            <p class="mb-0">{{ $invoice->billing_email }}</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="invoice-info-box">
                            <h6>Detalles del recibo</h6>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Número</span><strong>{{ $invoice->invoice_number }}</strong></div>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Emisión</span><span>{{ $invoice->issue_date->format('d/m/Y') }}</span></div>
                            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Vencimiento</span><span>{{ $invoice->due_date->format('d/m/Y') }}</span></div>
                            @if($invoice->billing_period)
                            <div class="d-flex justify-content-between"><span class="text-muted">Período</span><span>{{ $invoice->billing_period }}</span></div>
                            @endif
                        </div>
                    </div>
                </div>

                <h6 class="invoice-section-title">Detalle de conceptos</h6>
                <div class="table-responsive">
                    <table class="table invoice-table border">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unitario</th>
                                <th class="text-end">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $item)
                            <tr>
                                <td>{{ $item->description }}</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">{{ number_format($item->amount, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="invoice-totals mt-4">
                    <div class="row-total">
                        <span class="text-muted">Subtotal</span>
                        <strong>S/ {{ number_format($invoice->amount, 2) }}</strong>
                    </div>
                    <div class="row-total grand-total">
                        <span>Total (IGV incl.)</span>
                        <span>S/ {{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    @if(in_array($invoice->status, ['partial', 'paid', 'pending', 'overdue']))
                    <div class="row-total">
                        <span class="text-muted">Pagado</span>
                        <strong>S/ {{ number_format($invoice->paid_amount, 2) }}</strong>
                    </div>
                    <div class="row-total">
                        <span class="text-muted">Pendiente</span>
                        <strong>S/ {{ number_format($invoice->pending_amount, 2) }}</strong>
                    </div>
                    @endif
                </div>

                @if($invoice->notes)
                <div class="mt-4">
                    <h6 class="text-uppercase text-muted">Notas</h6>
                    <p class="text-muted">{{ $invoice->notes }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Pagos Relacionados -->
        @if(count($invoice->payments) > 0)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave text-primary me-2"></i> Pagos Registrados
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Método</th>
                                <th>Referencia</th>
                                <th>Estado</th>
                                <th class="text-end">Monto</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                <td>{{ $payment->payment_method_name }}</td>
                                <td>{{ $payment->reference }}</td>
                                <td>
                                    @if($payment->status == 'completed')
                                        <span class="badge bg-success">Completado</span>
                                    @elseif($payment->status == 'pending')
                                        <span class="badge bg-warning">Pendiente</span>
                                    @elseif($payment->status == 'refunded')
                                        <span class="badge bg-secondary">Anulado</span>
                                    @else
                                        <span class="badge bg-danger">{{ $payment->status }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($payment->amount, 2) }}</td>
                                <td>
                                    <a href="{{ route('billing.payments.show', $payment->id) }}" class="btn btn-sm btn-outline-primary">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Notas de Crédito -->
        @if(count($invoice->creditNotes) > 0)
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-receipt text-primary me-2"></i> Notas de Crédito
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Motivo</th>
                                <th>Estado</th>
                                <th class="text-end">Monto</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->creditNotes as $creditNote)
                            <tr>
                                <td>{{ $creditNote->credit_note_number }}</td>
                                <td>{{ $creditNote->issue_date->format('d/m/Y') }}</td>
                                <td>{{ Str::limit($creditNote->reason, 30) }}</td>
                                <td>
                                    @if($creditNote->status == 'applied')
                                        <span class="badge bg-success">Aplicada</span>
                                    @elseif($creditNote->status == 'active')
                                        <span class="badge bg-info">Activa</span>
                                    @elseif($creditNote->status == 'draft')
                                        <span class="badge bg-secondary">Borrador</span>
                                    @elseif($creditNote->status == 'void')
                                        <span class="badge bg-secondary">Anulada</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($creditNote->amount, 2) }}</td>
                                <td>
                                    <a href="{{ route('billing.credit-notes.show', $creditNote->id) }}" class="btn btn-sm btn-outline-primary">
                                        Ver
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-4">
        <!-- Información del Cliente y Contrato -->
        <div class="card invoice-side-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user text-primary me-2"></i> Cliente y Contrato
                </h5>
            </div>
            <div class="card-body">
                <h6 class="invoice-section-title">Cliente</h6>
                <p class="mb-1 fw-bold">
                    <a href="{{ route('customer.customers.show', $invoice->contract->customer->id) }}">
                        {{ $invoice->contract->customer->full_name }}
                    </a>
                </p>
                <p class="mb-4 text-muted">
                    <i class="fas fa-envelope text-muted me-2"></i> {{ $invoice->contract->customer->email }}<br>
                    <i class="fas fa-phone text-muted me-2"></i> {{ $invoice->contract->customer->phone }}
                </p>

                <h6 class="invoice-section-title">Contrato</h6>
                <p class="mb-1">
                    <a href="{{ route('contract.contracts.show', $invoice->contract->id) }}">
                        Contrato #{{ $invoice->contract->id }}
                    </a>
                </p>
                <p class="mb-0">
                    <strong>Plan:</strong> {{ $invoice->contract->plan->name }}<br>
                    <strong>Estado:</strong>
                    @if($invoice->contract->status == 'active')
                        <span class="badge bg-success">Activo</span>
                    @else
                        <span class="badge bg-secondary">{{ $invoice->contract->status }}</span>
                    @endif
                </p>
            </div>
        </div>

        <!-- Estado de recibo -->
        <div class="card invoice-side-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle text-primary me-2"></i> Estado del recibo
                </h5>
            </div>
            <div class="card-body">
                <div class="invoice-meta-row">
                    <span class="text-muted">Estado</span>
                    <span>
                        <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
                    </span>
                </div>
                @if(in_array($invoice->status, ['pending', 'partial']))
                <div class="invoice-meta-row">
                    <span class="text-muted">Vencimiento</span>
                    <span class="text-end">
                        {{ $invoice->due_date->format('d/m/Y') }}
                        @if($invoice->isOverdue())
                            <span class="badge bg-danger ms-1">{{ $invoice->daysOverdue() }} días</span>
                        @else
                            <span class="badge bg-success ms-1">Faltan {{ $invoice->daysUntilDue() }} días</span>
                        @endif
                    </span>
                </div>
                @endif
                <div class="invoice-meta-row">
                    <span class="text-muted">Generada el</span>
                    <span class="text-end">{{ $invoice->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="invoice-meta-row">
                    <span class="text-muted">Tipo</span>
                    <span>
                        @if($invoice->generation_type == 'manual')
                            <span class="badge bg-primary">Manual</span>
                        @elseif($invoice->generation_type == 'automatic')
                            <span class="badge bg-info">Automática</span>
                        @elseif($invoice->generation_type == 'recurring')
                            <span class="badge bg-warning">Recurrente</span>
                        @else
                            <span class="badge bg-secondary">{{ $invoice->generation_type }}</span>
                        @endif
                    </span>
                </div>
                <div class="invoice-meta-row">
                    <span class="text-muted">Enviada</span>
                    <span class="text-end">
                        @if($invoice->sent)
                            <span class="badge bg-success">Sí</span> {{ $invoice->sent_at->format('d/m/Y H:i') }}
                        @else
                            <span class="badge bg-danger">No</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <!-- Registros de Auditoría -->
        <div class="card invoice-side-card mb-4">
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

<!-- Modal para anular recibo -->
<div class="modal fade" id="voidInvoiceModal" tabindex="-1" aria-labelledby="voidInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('billing.invoices.void', $invoice->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="voidInvoiceModalLabel">Anular Recibo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Esta acción no se puede deshacer.
                    </div>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motivo de anulación <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Anular Recibo</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
