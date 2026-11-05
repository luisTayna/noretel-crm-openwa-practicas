@extends('core::layouts.master')

@section('title', 'Dashboard de Cobranza')
@section('page-title', 'Dashboard de Cobranza')

@section('content')
<div class="row">
    <!-- Resumen de Recibos -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Recibos pendientes
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            {{ $pendingInvoicesCount }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted bg-light py-2">
                <a href="{{ route('billing.invoices.index', ['status' => 'pending']) }}" class="text-decoration-none">
                    <small>Ver todas <i class="fas fa-chevron-right ml-1"></i></small>
                </a>
            </div>
        </div>
    </div>

    <!-- Monto Pendiente -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Monto Pendiente
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            {{ number_format($pendingTotal, 2) }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted bg-light py-2">
                <a href="{{ route('billing.reports', ['report_type' => 'aging']) }}" class="text-decoration-none">
                    <small>Ver análisis <i class="fas fa-chevron-right ml-1"></i></small>
                </a>
            </div>
        </div>
    </div>

    <!-- Recibos vencidos -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Recibos vencidos
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            {{ $overdueInvoicesCount }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted bg-light py-2">
                <a href="{{ route('billing.invoices.index', ['status' => 'pending', 'to' => now()->subDay()->format('Y-m-d')]) }}" class="text-decoration-none">
                    <small>Ver vencidas <i class="fas fa-chevron-right ml-1"></i></small>
                </a>
            </div>
        </div>
    </div>

    <!-- Ingresos Mensuales -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Ingresos Mensuales
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            {{ number_format($monthlyPaymentsTotal, 2) }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted bg-light py-2">
                <small>{{ now()->format('F Y') }}</small>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos y Tendencias -->
<div class="row">
    <!-- Recibos vs Cobros -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recibos vs Cobros (Últimos 6 meses)</h6>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="billingPaymentsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Estado de Recibos -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Estado de Recibos</h6>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="invoiceStatusChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    @foreach($invoicesByStatus as $statusGroup)
                        <span class="mr-2">
                            <i class="fas fa-circle
                                @if($statusGroup->status == 'paid') text-success
                                @elseif($statusGroup->status == 'partial') text-info
                                @elseif($statusGroup->status == 'pending') text-warning
                                @elseif($statusGroup->status == 'overdue') text-danger
                                @else text-secondary
                                @endif"></i>
                            {{ ucfirst($statusGroup->status) }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recibos y pagos recientes -->
<div class="row">
    <!-- Recibos pendientes -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recibos pendientes recientes</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Recibo</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingInvoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('billing.invoices.show', $invoice->id) }}">
                                        {{ $invoice->invoice_number }}
                                    </a>
                                </td>
                                <td>{{ $invoice->contract->customer->full_name }}</td>
                                <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                                <td>{{ number_format($invoice->total_amount, 2) }}</td>
                                <td>
                                    @if($invoice->isOverdue())
                                        <span class="badge bg-danger">Vencida</span>
                                    @else
                                        <span class="badge bg-warning">Pendiente</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($pendingInvoicesCount > 5)
                <div class="text-center mt-3">
                    <a href="{{ route('billing.invoices.index', ['status' => 'pending']) }}" class="btn btn-sm btn-primary">
                        Ver todas las recibos pendientes
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Pagos Recientes -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Pagos Recientes</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Recibo</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Monto</th>
                                <th>Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentPayments as $payment)
                            <tr>
                                <td>
                                    <a href="{{ route('billing.invoices.show', $payment->invoice_id) }}">
                                        {{ $payment->invoice->invoice_number }}
                                    </a>
                                </td>
                                <td>{{ $payment->invoice->contract->customer->full_name }}</td>
                                <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                <td>{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->payment_method_name }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="{{ route('billing.payments.index') }}" class="btn btn-sm btn-primary">
                        Ver todos los pagos
                    </a>
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
    // Datos para el gráfico de recibos vs cobros
    const billingLabels = @json(array_keys($monthlyComparison));
    const invoiceData = @json(array_column($monthlyComparison, 'invoiced'));
    const paymentData = @json(array_column($monthlyComparison, 'collected'));

    // Gráfico de recibos vs cobros
    const ctx1 = document.getElementById('billingPaymentsChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: billingLabels,
            datasets: [
                {
                    label: 'Emitido',
                    lineTension: 0.3,
                    backgroundColor: "rgba(78, 115, 223, 0.05)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointBorderColor: "rgba(78, 115, 223, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                    pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: invoiceData,
                },
                {
                    label: 'Cobrado',
                    lineTension: 0.3,
                    backgroundColor: "rgba(28, 200, 138, 0.05)",
                    borderColor: "rgba(28, 200, 138, 1)",
                    pointRadius: 3,
                    pointBackgroundColor: "rgba(28, 200, 138, 1)",
                    pointBorderColor: "rgba(28, 200, 138, 1)",
                    pointHoverRadius: 3,
                    pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
                    pointHoverBorderColor: "rgba(28, 200, 138, 1)",
                    pointHitRadius: 10,
                    pointBorderWidth: 2,
                    data: paymentData,
                }
            ],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                xAxes: [{
                    time: {
                        unit: 'month'
                    },
                    gridLines: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                }],
                yAxes: [{
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value) {
                            return '$' + number_format(value, 2);
                        }
                    },
                    gridLines: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }],
            },
            legend: {
                display: true
            },
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ': $' + number_format(tooltipItem.yLabel, 2);
                    }
                }
            }
        }
    });

    // Datos para el gráfico de estado de recibos
    const statusLabels = @json($invoicesByStatus->pluck('status')->toArray());
    const statusCounts = @json($invoicesByStatus->pluck('count')->toArray());
    const statusColors = [
        'paid' ? '#1cc88a' : '', // verde
        'partial' ? '#36b9cc' : '', // celeste
        'pending' ? '#f6c23e' : '', // amarillo
        'overdue' ? '#e74a3b' : '', // rojo
        'void' ? '#858796' : '', // gris
        'draft' ? '#858796' : '' // gris
    ];

    // Gráfico de estado de recibos
    const ctx2 = document.getElementById('invoiceStatusChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusCounts,
                backgroundColor: statusColors,
                hoverBackgroundColor: statusColors,
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                caretPadding: 10,
            },
            legend: {
                display: false
            },
            cutoutPercentage: 80,
        },
    });

    // Helper function for formatting numbers
    function number_format(number, decimals, dec_point, thousands_sep) {
        number = (number + '').replace(',', '').replace(' ', '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
            dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
            s = '',
            toFixedFix = function(n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }
});
</script>
@endpush
