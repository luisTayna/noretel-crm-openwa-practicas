@extends('core::layouts.master')

@section('title', 'Reportes Financieros')
@section('page-title', 'Reportes Financieros')

@section('content')
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar text-primary me-2"></i> Reportes Financieros
        </h5>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form action="{{ route('billing.reports') }}" method="GET" class="mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Tipo de Reporte</label>
                    <select class="form-select" id="report_type" name="report_type">
                        <option value="payments" {{ $reportType == 'payments' ? 'selected' : '' }}>Pagos</option>
                        <option value="invoices" {{ $reportType == 'invoices' ? 'selected' : '' }}>Recibos</option>
                        <option value="aging" {{ $reportType == 'aging' ? 'selected' : '' }}>Envejecimiento de Cartera</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>

        <!-- Resultados del Reporte -->
        @if($reportType == 'payments')
            <div id="payments-report">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Resumen de Pagos</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-center">
                                    <div class="text-center">
                                        <h1 class="h2 mb-0">${{ number_format($data['total'], 2) }}</h1>
                                        <p class="text-muted">Total recaudado</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h5>{{ $data['payments']->count() }}</h5>
                                        <p class="text-muted">Pagos registrados</p>
                                    </div>
                                    <div class="col-6">
                                        <h5>{{ $data['by_method']->count() }}</h5>
                                        <p class="text-muted">Métodos de pago</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Pagos por Método</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="paymentMethodChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Pagos por Día</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="paymentsByDateChart" height="100"></canvas>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Listado de Pagos</h6>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('pagos.csv')">
                                <i class="fas fa-download me-1"></i> Exportar a CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="payments-table">
                                <thead>
                                    <tr>
                                        <th>Recibo</th>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Método</th>
                                        <th>Referencia</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['payments'] as $payment)
                                    <tr>
                                        <td>{{ $payment->invoice->invoice_number }}</td>
                                        <td>{{ $payment->invoice->contract->customer->full_name }}</td>
                                        <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                                        <td>{{ $payment->payment_method_name }}</td>
                                        <td>{{ $payment->reference ?? '-' }}</td>
                                        <td class="text-end">${{ number_format($payment->amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        @elseif($reportType == 'invoices')
            <div id="invoices-report">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Resumen de Cobranza</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-center">
                                    <div class="text-center">
                                        <h1 class="h2 mb-0">${{ number_format($data['total'], 2) }}</h1>
                                        <p class="text-muted">Total emitido</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h5>{{ $data['invoices']->count() }}</h5>
                                        <p class="text-muted">Recibos emitidos</p>
                                    </div>
                                    <div class="col-6">
                                        <h5>${{ number_format($data['invoices']->where('status', 'paid')->sum('total_amount'), 2) }}</h5>
                                        <p class="text-muted">Total cobrado</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Recibos por estado</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="invoiceStatusChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Recibos por día</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="invoicesByDateChart" height="100"></canvas>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Listado de Recibos</h6>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('recibos.csv')">
                                <i class="fas fa-download me-1"></i> Exportar a CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="invoices-table">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                        <th class="text-end">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['invoices'] as $invoice)
                                    <tr>
                                        <td>{{ $invoice->invoice_number }}</td>
                                        <td>{{ $invoice->contract->customer->full_name }}</td>
                                        <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                                        <td>{{ $invoice->due_date->format('d/m/Y') }}</td>
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
                                        <td class="text-end">${{ number_format($invoice->total_amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        @elseif($reportType == 'aging')
            <div id="aging-report">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Resumen de Cartera Vencida</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-center">
                                    <div class="text-center">
                                        <h1 class="h2 mb-0 text-danger">${{ number_format($data['total'], 2) }}</h1>
                                        <p class="text-muted">Total de cartera vencida</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h5>{{ $data['overdueInvoices']->count() }}</h5>
                                        <p class="text-muted">Recibos vencidas</p>
                                    </div>
                                    <div class="col-6">
                                        <h5>{{ $data['by_customer']->count() }}</h5>
                                        <p class="text-muted">Clientes con saldos vencidos</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Cartera Vencida por Antigüedad</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="agingChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Top 10 Clientes con Mayor Cartera Vencida</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Cliente</th>
                                        <th class="text-center">Recibos vencidos</th>
                                        <th class="text-end">Monto Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['by_customer']->take(10) as $customer)
                                    <tr>
                                        <td>{{ $customer['customer_name'] }}</td>
                                        <td class="text-center">{{ $customer['count'] }}</td>
                                        <td class="text-end">${{ number_format($customer['amount'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Listado de Recibos vencidos</h6>
                            <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('cartera_vencida.csv')">
                                <i class="fas fa-download me-1"></i> Exportar a CSV
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped" id="aging-table">
                                <thead>
                                    <tr>
                                        <th>Número</th>
                                        <th>Cliente</th>
                                        <th>Vencimiento</th>
                                        <th>Días Vencida</th>
                                        <th class="text-end">Monto Pendiente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['overdueInvoices'] as $invoice)
                                    <tr>
                                        <td>{{ $invoice->invoice_number }}</td>
                                        <td>{{ $invoice->contract->customer->full_name }}</td>
                                        <td>{{ $invoice->due_date->format('d/m/Y') }}</td>
                                        <td>{{ $invoice->daysOverdue() }}</td>
                                        <td class="text-end">${{ number_format($invoice->pending_amount, 2) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>


        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Función para exportar tablas a CSV
    window.exportTableToCSV = function(filename) {
        let csv = [];
        let reportType = '{{ $reportType }}';
        let tableId = '';

        if (reportType === 'payments') {
            tableId = 'payments-table';
        } else if (reportType === 'invoices') {
            tableId = 'invoices-table';
        } else if (reportType === 'aging') {
            tableId = 'aging-table';
        }

        const rows = document.querySelectorAll('#' + tableId + ' tr');

        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');

            for (let j = 0; j < cols.length; j++) {
                // Remover HTML tags
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s)/gm, ' ');
                // Escapar comillas
                data = data.replace(/"/g, '""');
                // Añadir comillas
                row.push('"' + data + '"');
            }

            csv.push(row.join(','));
        }

        // Crear enlace
        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
        const downloadLink = document.createElement('a');

        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';

        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    };

    // Renderizar gráficos según el tipo de reporte
    const reportType = '{{ $reportType }}';

    if (reportType === 'payments') {
        @if($reportType === 'payments')
        // Gráfico de métodos de pago
        const methodsData = @json($data['by_method']->map(function($item) { return $item['amount']; }));
        const methodsLabels = @json($data['by_method']->map(function($item) { return $item['method']; }));

        new Chart(document.getElementById('paymentMethodChart'), {
            type: 'pie',
            data: {
                labels: methodsLabels,
                datasets: [{
                    data: methodsData,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#eba11f', '#e02d1b', '#6e707e'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            return data.labels[tooltipItem.index] + ': $' +
                                   parseFloat(data.datasets[0].data[tooltipItem.index]).toFixed(2);
                        }
                    }
                }
            }
        });

        // Gráfico de pagos por día
        const dateLabels = @json($data['by_date']->pluck('date'));
        const dateValues = @json($data['by_date']->pluck('amount'));

        new Chart(document.getElementById('paymentsByDateChart'), {
            type: 'bar',
            data: {
                labels: dateLabels,
                datasets: [{
                    label: 'Monto recaudado',
                    backgroundColor: '#36b9cc',
                    data: dateValues
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }]
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            return data.datasets[tooltipItem.datasetIndex].label + ': $' +
                                   parseFloat(tooltipItem.yLabel).toFixed(2);
                        }
                    }
                }
            }
        });
        @endif
    }
    else if (reportType === 'invoices') {
        @if($reportType === 'invoices')
        // Gráfico de estado de recibos
        const statusLabels = @json($data['by_status']->pluck('status'));
        const statusCounts = @json($data['by_status']->pluck('count'));
        const statusAmounts = @json($data['by_status']->pluck('amount'));

        new Chart(document.getElementById('invoiceStatusChart'), {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusAmounts,
                    backgroundColor: [
                        '#1cc88a', // paid
                        '#36b9cc', // partial
                        '#f6c23e', // pending
                        '#e74a3b', // overdue
                        '#858796'  // void
                    ],
                    hoverBackgroundColor: [
                        '#17a673',
                        '#2c9faf',
                        '#eba11f',
                        '#e02d1b',
                        '#6e707e'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            const index = tooltipItem.index;
                            return data.labels[index] + ': $' +
                                   parseFloat(data.datasets[0].data[index]).toFixed(2) +
                                   ' (' + statusCounts[index] + ' recibos)';
                        }
                    }
                }
            }
        });

        // Gráfico de recibos por día
        const dateLabels = @json($data['by_date']->pluck('date'));
        const dateValues = @json($data['by_date']->pluck('amount'));

        new Chart(document.getElementById('invoicesByDateChart'), {
            type: 'bar',
            data: {
                labels: dateLabels,
                datasets: [{
                    label: 'Monto emitido',
                    backgroundColor: '#4e73df',
                    data: dateValues
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }]
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            return data.datasets[tooltipItem.datasetIndex].label + ': $' +
                                   parseFloat(tooltipItem.yLabel).toFixed(2);
                        }
                    }
                }
            }
        });
        @endif
    }
    else if (reportType === 'aging') {
        @if($reportType === 'aging')
        // Gráfico de envejecimiento
        const agingData = @json($data['aging']);
        const agingLabels = Object.keys(agingData);
        const agingCounts = agingLabels.map(key => agingData[key].count);
        const agingAmounts = agingLabels.map(key => agingData[key].amount);

        new Chart(document.getElementById('agingChart'), {
            type: 'bar',
            data: {
                labels: agingLabels,
                datasets: [{
                    label: 'Monto vencido',
                    backgroundColor: [
                        '#f6c23e', // 1-15 días
                        '#f89c32', // 16-30 días
                        '#f57c26', // 31-60 días
                        '#f45c1a', // 61-90 días
                        '#e74a3b'  // 90+ días
                    ],
                    data: agingAmounts
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }]
                },
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem, data) {
                            const index = tooltipItem.index;
                            return data.datasets[tooltipItem.datasetIndex].label + ': $' +
                                   parseFloat(tooltipItem.yLabel).toFixed(2) +
                                   ' (' + agingCounts[index] + ' recibos)';
                        }
                    }
                }
            }
        });
        @endif
    }
});
</script>
@endpush
