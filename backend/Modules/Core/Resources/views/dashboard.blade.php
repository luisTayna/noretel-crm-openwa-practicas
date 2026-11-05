@extends('core::layouts.master')

@section('title', 'Dashboard')
@section('page-title', 'Resumen Económico y Operativo')

@push('styles')
<style>
    /* Estilos Noretel Next-Gen para Dashboard */
    .metric-card {
        border-radius: 20px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.03);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    
    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
    }
    
    /* Variaciones Pastel para las Tarjetas KPI */
    .card-mrr {
        background: linear-gradient(135deg, rgba(236, 253, 245, 0.9) 0%, rgba(209, 250, 229, 0.9) 100%);
        border: 1px solid rgba(52, 211, 153, 0.3);
    }
    .card-receivables {
        background: linear-gradient(135deg, rgba(239, 246, 255, 0.9) 0%, rgba(219, 234, 254, 0.9) 100%);
        border: 1px solid rgba(96, 165, 250, 0.3);
    }
    .card-morosidad {
        background: linear-gradient(135deg, rgba(254, 242, 242, 0.9) 0%, rgba(254, 226, 226, 0.9) 100%);
        border: 1px solid rgba(248, 113, 113, 0.3);
    }
    .card-customers {
        background: linear-gradient(135deg, rgba(250, 245, 255, 0.9) 0%, rgba(243, 232, 255, 0.9) 100%);
        border: 1px solid rgba(192, 132, 252, 0.3);
    }

    /* Iconos dentro de las tarjetas */
    .icon-box {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
    }
    
    .bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
    .bg-gradient-primary { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
    .bg-gradient-danger  { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
    .bg-gradient-purple  { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
    
    /* Tipografía interior */
    .metric-title {
        color: #475569;
        font-size: 0.85rem;
        font-weight: 600;
    }
    .metric-value {
        color: #0f172a;
        font-size: 1.8rem;
        letter-spacing: -0.5px;
    }

    .table-recent-payments th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        border-bottom: 2px solid #f1f5f9;
        background: transparent;
    }
    .table-recent-payments td {
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        background: transparent;
    }
    
    .small-trend {
        font-size: 0.8rem;
        font-weight: 600;
        margin-top: 0.5rem;
        display: flex;
        align-items: center;
    }
</style>
@endpush

@section('content')
<div class="row g-4 mb-4">
    @if(Auth::user()->hasRole('admin'))
    <!-- MRR Card -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-mrr h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Ingresos del Mes (MRR)</h6>
                        <h3 class="metric-value mb-0 fw-bold">S/ {{ number_format($economicStats['mrr'], 2) }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-success">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
                <div class="small-trend text-success">
                    <i class="fas fa-arrow-up me-1"></i> Pagos completados
                </div>
            </div>
        </div>
    </div>

    <!-- Receivables Card -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-receivables h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Por Cobrar</h6>
                        <h3 class="metric-value mb-0 fw-bold">S/ {{ number_format($economicStats['receivables'], 2) }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-primary">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
                <div class="small-trend text-primary">
                    <i class="fas fa-clock me-1"></i> Facturas pendientes
                </div>
            </div>
        </div>
    </div>

    <!-- Default Rate Card -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-morosidad h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Tasa de Morosidad</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $economicStats['default_rate'] }}%</h3>
                    </div>
                    <div class="icon-box bg-gradient-danger">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
                <div class="small-trend text-danger">
                    <i class="fas fa-exclamation-circle me-1"></i> % facturas vencidas
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- New Customers Card -->
    <div class="col-12 col-md-6 {{ Auth::user()->hasRole('admin') ? 'col-xl-3' : 'col-xl-6' }}">
        <div class="card metric-card card-customers h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Nuevos Clientes</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $economicStats['new_customers'] }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-purple">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
                <div class="small-trend text-purple" style="color: #7c3aed;">
                    <i class="fas fa-calendar-alt me-1"></i> Registrados este mes
                </div>
            </div>
        </div>
    </div>
</div>

@if(Auth::user()->hasRole('admin'))
<div class="row g-4 mb-4">
    <!-- Gráfico de Ingresos -->
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: #0f172a;">Flujo de Ingresos (Últimos 6 meses)</h5>
                <i class="fas fa-ellipsis-v text-muted"></i>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 280px; width: 100%;">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos Pagos -->
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: #0f172a;">Últimos Pagos Recibidos</h5>
                <a href="{{ route('billing.payments.index') ?? '#' }}" class="text-primary text-decoration-none small">Ver todos <i class="fas fa-chevron-right ms-1 small"></i></a>
            </div>
            <div class="card-body mt-3">
                <div class="table-responsive">
                    <table class="table table-borderless table-recent-payments mb-0">
                        <tbody>
                            @forelse($economicStats['recent_payments'] as $payment)
                            <tr>
                                <td class="ps-2">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-3" style="width: 36px; height: 36px; background: linear-gradient(135deg, #0ea5e9, #3b82f6);">
                                            {{ substr($payment->first_name, 0, 1) }}{{ substr($payment->last_name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="color: #1e293b; font-size: 0.9rem;">{{ $payment->first_name }} {{ $payment->last_name }}</div>
                                            <div class="small text-muted" style="font-size: 0.75rem;">Factura #{{ $payment->invoice_number }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end align-middle">
                                    <div class="fw-bold text-success" style="background: #ecfdf5; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; display: inline-block;">
                                        S/ {{ number_format($payment->amount, 2) }}
                                    </div>
                                    <div class="small text-muted mt-1" style="font-size: 0.7rem;">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted">No hay pagos recientes.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Segunda Fila: Actividad y Componentes -->
<div class="row g-4">
    <!-- Actividad del Sistema -->
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header pt-4 pb-3">
                <h5 class="mb-0 fw-bold" style="color: #0f172a;"><i class="fas fa-history text-muted me-2"></i> Registro de Auditoría (Reciente)</h5>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush" style="background: transparent;">
                    @forelse($recentActivities as $activity)
                    <li class="list-group-item px-4 py-3" style="background: transparent; border-color: rgba(0,0,0,0.05);">
                        <div class="d-flex w-100 justify-content-between mb-1">
                            <h6 class="mb-0 fw-semibold" style="color: #1e293b;">{{ $activity->action_type }}</h6>
                            <small class="text-muted">{{ $activity->action_date->diffForHumans() }}</small>
                        </div>
                        <p class="mb-1 small text-secondary">{{ $activity->action_detail }}</p>
                        <small class="text-primary"><i class="fas fa-user me-1"></i> {{ $activity->user->username ?? 'Sistema' }}</small>
                    </li>
                    @empty
                    <li class="list-group-item px-4 py-4 text-center text-muted" style="background: transparent;">No hay actividad reciente.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <!-- Estado del Sistema -->
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header pt-4 pb-3">
                <h5 class="mb-0 fw-bold" style="color: #0f172a;"><i class="fas fa-server text-muted me-2"></i> Estado de los Componentes</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="background: transparent;">
                        <tbody>
                            @foreach($systemComponents as $component)
                            <tr style="border-color: rgba(0,0,0,0.05);">
                                <td class="ps-4 py-3" style="background: transparent;">
                                    <div class="fw-semibold" style="color: #1e293b;">{{ $component['name'] }}</div>
                                    <div class="small text-muted">{{ $component['description'] }}</div>
                                </td>
                                <td class="py-3 text-end pe-4 align-middle" style="background: transparent;">
                                    @if($component['status'] === 'active')
                                        <span class="badge px-3 py-2 rounded-pill" style="background: #ecfdf5; color: #10b981;"><i class="fas fa-check-circle me-1 small"></i> En línea</span>
                                    @elseif($component['status'] === 'warning')
                                        <span class="badge px-3 py-2 rounded-pill" style="background: #fffbeb; color: #f59e0b;"><i class="fas fa-exclamation-triangle me-1 small"></i> Alerta</span>
                                    @else
                                        <span class="badge px-3 py-2 rounded-pill" style="background: #fef2f2; color: #ef4444;"><i class="fas fa-times-circle me-1 small"></i> Error</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
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
        const ctx = document.getElementById('incomeChart');
        
        if (ctx) {
            const chartData = @json($economicStats['chart_data']);
            
            // Crear el gradiente vibrante para las barras
            let chartCtx = ctx.getContext('2d');
            let gradientBar = chartCtx.createLinearGradient(0, 0, 0, 400);
            gradientBar.addColorStop(0, '#00e676'); // Teal/Green brillante (top)
            gradientBar.addColorStop(1, '#0099ff'); // Azul vibrante (bottom)
            
            new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: chartData.labels.reverse(), 
                    datasets: [{
                        label: 'Ingresos (S/)',
                        data: chartData.data.reverse(),
                        backgroundColor: gradientBar,
                        borderWidth: 0,
                        borderRadius: 8, // Bordes bien redondeados como en la imagen 2
                        barPercentage: 0.6, // Barras más delgadas y estilizadas
                        hoverBackgroundColor: '#00b359'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            padding: 12,
                            titleFont: { size: 13, family: "'Poppins', sans-serif" },
                            bodyFont: { size: 14, weight: 'bold', family: "'Poppins', sans-serif" },
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return 'S/ ' + context.parsed.y.toLocaleString('es-PE', {minimumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 1000,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.03)', // Líneas de grilla muy sutiles
                                drawBorder: false
                            },
                            ticks: {
                                font: { family: "'Poppins', sans-serif", size: 11 },
                                color: '#94a3b8',
                                padding: 10,
                                callback: function(value) { return 'S/ ' + value; }
                            }
                        },
                        x: {
                            grid: { display: false, drawBorder: false },
                            ticks: {
                                font: { family: "'Poppins', sans-serif", size: 11 },
                                color: '#94a3b8',
                                padding: 10,
                                callback: function(value) { return this.getLabelForValue(value).substring(0, 3); }
                            }
                        }
                    },
                    interaction: { mode: 'index', intersect: false },
                }
            });
        }
    });
</script>
@endpush