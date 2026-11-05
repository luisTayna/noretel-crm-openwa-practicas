@extends('core::layouts.master')

@section('title', 'Dashboard de Contratos')
@section('page-title', 'Dashboard de Contratos')

@push('styles')
<style>
    /* Estilos personalizados para Dashboard de Contratos */
    .table-glass {
        margin-bottom: 0;
    }
    .table-glass th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        font-weight: 600;
        border-bottom: 2px solid rgba(0, 0, 0, 0.03);
        padding: 1rem;
        background: transparent;
    }
    .table-glass td {
        vertical-align: middle;
        border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        padding: 1rem;
        background: transparent;
        color: var(--text-color);
    }
    .table-glass tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.4) !important;
    }

    .badge-pill {
        border-radius: 9999px;
        padding: 0.35em 0.85em;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-plan {
        background-color: rgba(0, 153, 255, 0.1) !important;
        color: var(--secondary-color) !important;
        border: 1px solid rgba(0, 153, 255, 0.2) !important;
    }

    .badge-status-paid {
        background-color: rgba(16, 185, 129, 0.1) !important;
        color: #10b981 !important;
        border: 1px solid rgba(16, 185, 129, 0.2) !important;
    }

    .badge-status-pending {
        background-color: rgba(245, 158, 11, 0.1) !important;
        color: #d97706 !important;
        border: 1px solid rgba(245, 158, 11, 0.2) !important;
    }

    .badge-status-overdue {
        background-color: rgba(239, 68, 68, 0.1) !important;
        color: #ef4444 !important;
        border: 1px solid rgba(239, 68, 68, 0.2) !important;
    }

    .btn-glass-action {
        background-color: rgba(255, 255, 255, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        color: var(--text-muted) !important;
        border-radius: 12px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        text-decoration: none;
    }

    .btn-glass-action:hover {
        background-color: white !important;
        color: var(--text-color) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;
    }
</style>
@endpush

@section('content')
<div class="row g-4 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-mrr h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Contratos Activos</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $contractsByStatus['active'] ?? 0 }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-success">
                        <i class="fas fa-file-signature"></i>
                    </div>
                </div>
                <div class="small-trend text-success">
                    <i class="fas fa-check-circle me-1"></i> Líneas activas
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-warning-light h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Por Vencer</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ count($nearExpirationContracts) }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-warning">
                        <i class="fas fa-tools"></i>
                    </div>
                </div>
                <div class="small-trend text-warning" style="color: #d97706;">
                    <i class="fas fa-exclamation-triangle me-1"></i> Requieren renovación
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-morosidad h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Vencidos</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ count($expiredContracts) }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-danger">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
                <div class="small-trend text-danger">
                    <i class="fas fa-times-circle me-1"></i> Contratos caducados
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-info-light h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Instalaciones Pendientes</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $contractsByStatus['pending_installation'] ?? 0 }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-info">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                </div>
                <div class="small-trend text-info">
                    <i class="fas fa-clock me-1"></i> En espera de técnicos
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-chart-line text-muted me-2"></i> Contratos por Mes</h5>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="contractsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-chart-pie text-muted me-2"></i> Contratos por Estado</h5>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="contractStatusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header pt-4 pb-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-exclamation-circle text-muted me-2"></i> Contratos por Vencer</h5>
                <a href="{{ route('contract.contracts.near-expiration') }}" class="btn btn-sm btn-glass-action px-3 py-1">Ver Todos</a>
            </div>
            <div class="card-body p-0 mt-2">
                <div class="table-responsive">
                    <table class="table table-glass">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Vence</th>
                                <th>Días</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($nearExpirationContracts->take(5) as $contract)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold" style="color: var(--text-color);">{{ $contract->customer->first_name }} {{ $contract->customer->last_name }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-pill badge-plan">{{ $contract->plan->name }}</span>
                                    </td>
                                    <td class="text-muted">{{ $contract->end_date->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge badge-pill badge-status-pending">{{ $contract->remaining_time }} días</span>
                                    </td>
                                    <td class="pe-3">
                                        <a href="{{ route('contract.contracts.show', $contract->id) }}" class="btn btn-sm btn-glass-action px-2 py-1 me-1" title="Ver">
                                            <i class="fas fa-eye text-info"></i>
                                        </a>
                                        @if($contract->canBeRenewed())
                                            <a href="{{ route('contract.contracts.renew-form', $contract->id) }}" class="btn btn-sm btn-glass-action px-2 py-1" title="Renovar">
                                                <i class="fas fa-sync-alt text-success"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No hay contratos por vencer</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header pt-4 pb-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-clipboard-list text-muted me-2"></i> Contratos Recientes</h5>
                <a href="{{ route('contract.contracts.index') }}" class="btn btn-sm btn-glass-action px-3 py-1">Ver Todos</a>
            </div>
            <div class="card-body p-0 mt-2">
                <div class="table-responsive">
                    <table class="table table-glass">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentContracts as $contract)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold" style="color: var(--text-color);">{{ $contract->customer->first_name }} {{ $contract->customer->last_name }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-pill badge-plan">{{ $contract->plan->name }}</span>
                                    </td>
                                    <td>
                                        @if($contract->status == 'active')
                                            <span class="badge badge-pill badge-status-paid">Activo</span>
                                        @elseif($contract->status == 'pending_installation')
                                            <span class="badge badge-pill badge-status-pending">Pendiente</span>
                                        @elseif($contract->status == 'expired')
                                            <span class="badge badge-pill badge-status-overdue">Vencido</span>
                                        @elseif($contract->status == 'cancelled')
                                            <span class="badge badge-pill bg-light text-secondary border">Cancelado</span>
                                        @elseif($contract->status == 'renewed')
                                            <span class="badge badge-pill badge-plan">Renovado</span>
                                        @else
                                            <span class="badge badge-pill bg-light text-secondary">{{ $contract->status }}</span>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $contract->created_at->format('d/m/Y') }}</td>
                                    <td class="pe-3">
                                        <a href="{{ route('contract.contracts.show', $contract->id) }}" class="btn btn-sm btn-glass-action px-2 py-1" title="Ver">
                                            <i class="fas fa-eye text-info"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No hay contratos recientes</td>
                                </tr>
                            @endforelse
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
    // Datos para el gráfico de contratos por mes
    const monthsData = @json($monthlyContractsData);
    const months = Object.keys(monthsData);
    const contractCounts = Object.values(monthsData);
    
    const ctx1 = document.getElementById('contractsChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Contratos por Mes',
                data: contractCounts,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Datos para el gráfico de contratos por estado
    const statusData = @json($contractsByStatus);
    const statuses = Object.keys(statusData).map(key => {
        switch(key) {
            case 'active': return 'Activos';
            case 'pending_installation': return 'Pendientes';
            case 'expired': return 'Vencidos';
            case 'cancelled': return 'Cancelados';
            case 'renewed': return 'Renovados';
            case 'suspended': return 'Suspendidos';
            default: return key;
        }
    });
    const statusCounts = Object.values(statusData);
    
    const ctx2 = document.getElementById('contractStatusChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: statuses,
            datasets: [{
                data: statusCounts,
                backgroundColor: [
                    'rgba(40, 167, 69, 0.7)',  // success - active
                    'rgba(255, 193, 7, 0.7)',  // warning - pending
                    'rgba(220, 53, 69, 0.7)',  // danger - expired
                    'rgba(108, 117, 125, 0.7)', // secondary - cancelled
                    'rgba(23, 162, 184, 0.7)',  // info - renewed
                    'rgba(0, 123, 255, 0.7)'    // primary - suspended
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(220, 53, 69, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(0, 123, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
});
</script>
@endpush