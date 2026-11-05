@extends('core::layouts.master')

@section('title', 'Gestión de Cobranzas')
@section('page-title', 'Panel de Deudores y Cobranza')

@section('content')
<div class="row">
    <div class="col-lg-12 mb-3">
        <div class="d-sm-flex align-items-center justify-content-between">
            <h1 class="h3 mb-0 text-gray-800">Cobranzas por WhatsApp</h1>
        </div>
        <p class="text-muted mt-2">Envía recordatorios de pago a todos los clientes con saldo pendiente.</p>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-users text-warning me-2"></i> Clientes con deuda pendiente</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="collectionsTable" width="100%" cellspacing="0">
                <thead class="bg-light">
                    <tr>
                        <th>N° Recibo</th>
                        <th>Cliente</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Monto Deuda</th>
                        <th class="text-center">Acción (WhatsApp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    <tr>
                        <td>
                            <a href="{{ route('billing.invoices.show', $invoice->id) }}">
                                {{ $invoice->invoice_number }}
                            </a>
                        </td>
                        <td>
                            <strong>{{ $invoice->contract->customer->full_name ?? $invoice->billing_name }}</strong>
                            <br>
                            <small class="text-muted">{{ $invoice->contract->customer->phone ?? 'Sin teléfono' }}</small>
                        </td>
                        <td>
                            {{ $invoice->due_date->format('d/m/Y') }}
                            <br>
                            @if($invoice->isOverdue())
                                <small class="text-danger fw-bold"><i class="fas fa-exclamation-triangle"></i> Vencida hace {{ $invoice->daysOverdue() }} días</small>
                            @else
                                <small class="text-success">Vence en {{ $invoice->daysUntilDue() }} días</small>
                            @endif
                        </td>
                        <td>
                            @if($invoice->status == 'pending')
                                @if($invoice->isOverdue())
                                    <span class="badge bg-danger">Vencida</span>
                                @else
                                    <span class="badge bg-warning">Pendiente</span>
                                @endif
                            @elseif($invoice->status == 'partial')
                                <span class="badge bg-info">Pago Parcial</span>
                            @endif
                        </td>
                        <td class="fw-bold">
                            S/ {{ number_format($invoice->pending_amount, 2) }}
                        </td>
                        <td class="text-center">
                            @php
                                $phone = preg_replace('/[^0-9]/', '', $invoice->contract->customer->phone ?? '');
                                if (strlen($phone) == 9) $phone = '51' . $phone;
                                
                                $customerNameArray = explode(' ', trim($invoice->contract->customer->first_name ?? 'Cliente'));
                                $customerName = $customerNameArray[0];
                                $amountStr = number_format($invoice->pending_amount, 2);
                                $dueDateStr = $invoice->due_date->format('d/m/Y');
                                
                                if ($invoice->isOverdue()) {
                                    $waMessage = "Hola {$customerName}, te saludamos de Noretel. Tu recibo de S/ {$amountStr} venció el {$dueDateStr}. Por favor realiza el pago para evitar corte de servicio.";
                                } else {
                                    $waMessage = "Hola {$customerName}, te saludamos de Noretel. Te recordamos que tu recibo de S/ {$amountStr} vence el {$dueDateStr}. Evita recargos pagando a tiempo.";
                                }
                                $waUrl = "https://wa.me/{$phone}?text=" . urlencode($waMessage);
                            @endphp
                            
                            @if(!empty($phone))
                            <a href="{{ $waUrl }}" target="_blank" class="btn btn-sm btn-success" title="Enviar recordatorio por WhatsApp">
                                <i class="fab fa-whatsapp me-1"></i> Notificar
                            </a>
                            @else
                            <span class="badge bg-secondary">Sin número</span>
                            @endif
                            
                            <a href="{{ route('billing.payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-sm btn-outline-primary ms-1" title="Registrar Pago">
                                <i class="fas fa-money-bill-wave"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="text-muted">
                                <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                                <h5>¡Felicidades!</h5>
                                <p>No hay recibos pendientes ni vencidas actualmente.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof jQuery !== 'undefined' && $.fn.DataTable) {
            $('#collectionsTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
                },
                "order": [[ 2, "asc" ]] // Ordenar por fecha de vencimiento
            });
        }
    });
</script>
@endpush