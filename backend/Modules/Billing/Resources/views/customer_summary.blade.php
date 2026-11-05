@extends('core::layouts.master')

@section('title', 'Estado de Cuenta')
@section('page-title', 'Estado de Cuenta de Cliente')

@section('content')
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-user text-primary me-2"></i> Cliente: {{ $invoices->first()->contract->customer->full_name }}
                    </h5>
                    <a href="{{ route('customer.customers.show', $customerId) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i> Volver al perfil
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="text-uppercase text-muted">Total emitido</h6>
                                <h3 class="mb-0">${{ number_format($invoices->sum('total_amount'), 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="text-uppercase text-muted">Total Pagado</h6>
                                <h3 class="mb-0 text-success">${{ number_format($paidTotal, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="text-uppercase text-muted">Saldo Pendiente</h6>
                                <h3 class="mb-0 {{ $pendingTotal > 0 ? 'text-danger' : 'text-success' }}">${{ number_format($pendingTotal, 2) }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                @if(count($overdueInvoices) > 0)
                <div class="alert alert-danger mt-3">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-1">Recibos vencidos</h6>
                            <p class="mb-0">El cliente tiene {{ count($overdueInvoices) }} recibos vencidos por un total de ${{ number_format($overdueInvoices->sum('pending_amount'), 2) }}.</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Recibos -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice text-primary me-2"></i> Recibos
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Vencimiento</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Pagado</th>
                                <th>Pendiente</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices->sortByDesc('issue_date') as $invoice)
                            <tr class="{{ $invoice->status == 'pending' && $invoice->isOverdue() ? 'table-danger' : '' }}">
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                                <td>
                                    {{ $invoice->due_date->format('d/m/Y') }}
                                    @if($invoice->status == 'pending' && $invoice->isOverdue())
                                    <span class="badge bg-danger ms-1">{{ $invoice->daysOverdue() }} días</span>
                                    @endif
                                </td>
                                <td>
                                    @if($invoice->status == 'paid')
                                    <span class="badge bg-success">Pagada</span>
                                    @elseif($invoice->status == 'partial')
                                    <span class="badge bg-info">Pago Parcial</span>
                                    @elseif($invoice->status == 'pending' && $invoice->isOverdue())
                                    <span class="badge bg-danger">Vencida</span>
                                    @elseif($invoice->status == 'pending')
                                    <span class="badge bg-warning">Pendiente</span>
                                    @elseif($invoice->status == 'void')
                                    <span class="badge bg-secondary">Anulada</span>
                                    @else
                                    <span class="badge bg-secondary">{{ $invoice->status }}</span>
                                    @endif
                                </td>
                                <td>${{ number_format($invoice->total_amount, 2) }}</td>
                                <td>${{ number_format($invoice->paid_amount, 2) }}</td>
                                <td>${{ number_format($invoice->pending_amount, 2) }}</td>
                                <td>
                                    <div class="action-btn-group">
                                        <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="action-btn" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        @if(in_array($invoice->status, ['pending', 'partial']))
                                        <a href="{{ route('billing.payments.create', ['invoice_id' => $invoice->id]) }}" class="action-btn primary" title="Registrar pago">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Pagos Recientes -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave text-primary me-2"></i> Pagos Recientes
                </h5>
            </div>
            <div class="card-body">
                @if(count($payments) > 0)
                <div class="list-group">
                    @foreach($payments->sortByDesc('payment_date')->take(5) as $payment)
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">{{ $payment->invoice->invoice_number }}</h6>
                            <small>${{ number_format($payment->amount, 2) }}</small>
                        </div>
                        <p class="mb-1">
                            {{ $payment->payment_date->format('d/m/Y') }} - {{ $payment->payment_method_name }}
                        </p>
                        <small class="text-muted">{{ $payment->reference ? 'Ref: ' . $payment->reference : '' }}</small>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted mb-0">No hay pagos registrados.</p>
                @endif
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie text-primary me-2"></i> Estadísticas
                </h5>
            </div>
            <div class="card-body">
                <canvas id="invoiceStatusChart" height="200"></canvas>
            </div>
        </div>

        @if(count($overdueInvoices) > 0)
        <!-- Recibos vencidos -->
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i> Recibos vencidos
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    @foreach($overdueInvoices as $overdueInvoice)
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">{{ $overdueInvoice->invoice_number }}</h6>
                            <small class="text-danger">{{ $overdueInvoice->daysOverdue() }} días</small>
                        </div>
                        <p class="mb-1">
                            Vencimiento: {{ $overdueInvoice->due_date->format('d/m/Y') }}
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>${{ number_format($overdueInvoice->pending_amount, 2) }}</span>
                            <a href="{{ route('billing.payments.create', ['invoice_id' => $overdueInvoice->id]) }}" class="btn btn-sm btn-danger">
                                <i class="fas fa-money-bill-wave me-1"></i> Pagar
                            </a>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Acciones -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-cogs text-primary me-2"></i> Acciones
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3 mb-md-0">
                        <a href="{{ route('billing.invoices.create') }}" class="btn btn-primary w-100">
                            <i class="fas fa-file-invoice me-1"></i> Generar Recibo
                        </a>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <button type="button" class="btn btn-info w-100" id="print-statement-btn">
                            <i class="fas fa-print me-1"></i> Imprimir Estado de Cuenta
                        </button>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <button type="button" class="btn btn-success w-100" id="email-statement-btn">
                            <i class="fas fa-envelope me-1"></i> Enviar por Email
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-secondary w-100" id="export-csv-btn">
                            <i class="fas fa-download me-1"></i> Exportar a CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de estado de recibos
    const statuses = {
        paid: 0,
        partial: 0,
        pending: 0,
        overdue: 0,
        void: 0
    };

    const amounts = {
        paid: 0,
        partial: 0,
        pending: 0,
        overdue: 0,
        void: 0
    };

    // Contar recibos por estado
    @foreach($invoices as $invoice)
        @if($invoice->status == 'pending' && $invoice->isOverdue())
            statuses.overdue += 1;
            amounts.overdue += {{ $invoice->pending_amount }};
        @elseif($invoice->status == 'pending')
            statuses.pending += 1;
            amounts.pending += {{ $invoice->pending_amount }};
        @elseif($invoice->status == 'partial')
            statuses.partial += 1;
            amounts.partial += {{ $invoice->pending_amount }};
        @elseif($invoice->status == 'paid')
            statuses.paid += 1;
            amounts.paid += {{ $invoice->total_amount }};
        @elseif($invoice->status == 'void')
            statuses.void += 1;
            amounts.void += {{ $invoice->total_amount }};
        @endif
    @endforeach

    // Preparar datos para el gráfico
    const labels = ['Pagadas', 'Pago Parcial', 'Pendientes', 'Vencidas', 'Anuladas'];
    const data = [statuses.paid, statuses.partial, statuses.pending, statuses.overdue, statuses.void];
    const backgroundColor = ['#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'];

    // Crear gráfico
    new Chart(document.getElementById('invoiceStatusChart'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColor,
                hoverBackgroundColor: backgroundColor,
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }]
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                callbacks: {
                    label: function(tooltipItem, data) {
                        const index = tooltipItem.index;
                        const count = data.datasets[0].data[index];
                        return data.labels[index] + ': ' + count + ' recibo(s)';
                    }
                }
            },
            legend: {
                position: 'bottom'
            }
        }
    });

    // Exportar a CSV
    document.getElementById('export-csv-btn').addEventListener('click', function() {
        const customerId = {{ $customerId }};
        const customerName = '{{ $invoices->first()->contract->customer->full_name }}';

        let csvContent = 'data:text/csv;charset=utf-8,';

        // Encabezado
        csvContent += 'Estado de Cuenta - ' + customerName + '\r\n\r\n';

        // Cabeceras de columnas
        csvContent += 'Número,Fecha,Vencimiento,Estado,Total,Pagado,Pendiente\r\n';

        // Datos de recibos
        @foreach($invoices->sortByDesc('issue_date') as $invoice)
            let state = '';
            @if($invoice->status == 'paid')
                state = 'Pagada';
            @elseif($invoice->status == 'partial')
                state = 'Pago Parcial';
            @elseif($invoice->status == 'pending' && $invoice->isOverdue())
                state = 'Vencida';
            @elseif($invoice->status == 'pending')
                state = 'Pendiente';
            @elseif($invoice->status == 'void')
                state = 'Anulada';
            @endif

            csvContent += '{{ $invoice->invoice_number }},{{ $invoice->issue_date->format("d/m/Y") }},{{ $invoice->due_date->format("d/m/Y") }},' +
                          state + ',{{ number_format($invoice->total_amount, 2) }},{{ number_format($invoice->paid_amount, 2) }},{{ number_format($invoice->pending_amount, 2) }}\r\n';
        @endforeach

        // Totales
        csvContent += '\r\nTotal emitido,${{ number_format($invoices->sum("total_amount"), 2) }}\r\n';
        csvContent += 'Total Pagado,${{ number_format($paidTotal, 2) }}\r\n';
        csvContent += 'Saldo Pendiente,${{ number_format($pendingTotal, 2) }}\r\n';

        // Crear enlace y descargar
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', 'estado_cuenta_' + customerName.replace(/\s+/g, '_') + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // Imprimir estado de cuenta
    document.getElementById('print-statement-btn').addEventListener('click', function() {
        window.print();
    });

    // Enviar por email (sería un modal o redirección en una implementación real)
    document.getElementById('email-statement-btn').addEventListener('click', function() {
        alert('Esta funcionalidad estará disponible próximamente.');
    });
});
</script>
@endpush
