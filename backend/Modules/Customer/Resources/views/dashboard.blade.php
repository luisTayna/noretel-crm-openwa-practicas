@extends('core::layouts.master')

@section('title', 'Dashboard de Clientes')
@section('page-title', 'Dashboard de Clientes')

@section('content')
<div class="row">
    <div class="col-12 mb-4">
        <p class="lead">Resumen de la gestión de clientes, leads, documentos e interacciones.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Estadísticas de Clientes -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-receivables h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Total Clientes</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $totalCustomers }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="small-trend text-primary">
                    <i class="fas fa-users me-1"></i> Registrados en el sistema
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas de Clientes Activos -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-mrr h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Clientes Activos</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $activeCustomers }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="small-trend text-success">
                    <i class="fas fa-check-circle me-1"></i> Con servicio activo
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas de Clientes Inactivos -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-morosidad h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Clientes Inactivos</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $inactiveCustomers }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
                <div class="small-trend text-danger">
                    <i class="fas fa-user-times me-1"></i> Sin servicio o suspendidos
                </div>
            </div>
        </div>
    </div>

    <!-- Estadísticas de Clientes Nuevos -->
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card metric-card card-customers h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Nuevos (este mes)</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $newCustomersThisMonth }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-purple">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
                <div class="small-trend text-purple" style="color: #7c3aed;">
                    <i class="fas fa-user-plus me-1"></i> Nuevas altas
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Leads Totales -->
    <div class="col-12 col-md-4">
        <div class="card metric-card card-info-light h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Total de Leads</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $totalLeads }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-info">
                        <i class="fas fa-user-tag"></i>
                    </div>
                </div>
                <div class="small-trend text-info">
                    <i class="fas fa-user-tag me-1"></i> Clientes potenciales
                </div>
            </div>
        </div>
    </div>

    <!-- Leads No Convertidos -->
    <div class="col-12 col-md-4">
        <div class="card metric-card card-warning-light h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Leads no Convertidos</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $unconvertedLeads }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-warning">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
                <div class="small-trend text-warning" style="color: #d97706;">
                    <i class="fas fa-user-clock me-1"></i> En seguimiento
                </div>
            </div>
        </div>
    </div>

    <!-- Nuevos Leads -->
    <div class="col-12 col-md-4">
        <div class="card metric-card card-customers h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Nuevos Leads (este mes)</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $newLeadsThisMonth }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-purple">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="small-trend text-purple" style="color: #7c3aed;">
                    <i class="fas fa-star me-1"></i> Registrados este mes
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Documentos Pendientes -->
    <div class="col-12 col-md-6">
        <div class="card metric-card card-warning-light h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Documentos Pendientes</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $pendingDocuments }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-warning">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="small-trend text-warning" style="color: #d97706;">
                    <i class="fas fa-file-alt me-1"></i> Requieren validación
                </div>
            </div>
        </div>
    </div>

    <!-- Interacciones por Seguir -->
    <div class="col-12 col-md-6">
        <div class="card metric-card card-info-light h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="metric-title mb-1">Interacciones por Seguir</h6>
                        <h3 class="metric-value mb-0 fw-bold">{{ $followUpInteractions }}</h3>
                    </div>
                    <div class="icon-box bg-gradient-info">
                        <i class="fas fa-comments"></i>
                    </div>
                </div>
                <div class="small-trend text-info">
                    <i class="fas fa-comments me-1"></i> Acciones de seguimiento
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Gráfico de Clientes por Tipo -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i> Clientes por Tipo
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px; width:100%">
                    <canvas id="customerTypeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Clientes por Segmento -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-pie"></i> Clientes por Segmento
            </div>
            <div class="card-body">
                <div class="chart-container" style="position: relative; height:300px; width:100%">
                    <canvas id="customerSegmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Últimas Interacciones -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-comments"></i> Últimas Interacciones
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Tipo</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentInteractions as $interaction)
                            <tr>
                                <td>{{ $interaction->customer->full_name }}</td>
                                <td>{{ $interaction->interaction_type }}</td>
                                <td>{{ $interaction->date->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('customer.interactions.show', $interaction->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No hay interacciones recientes</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="{{ route('customer.interactions.index') }}" class="btn btn-primary btn-sm">
                        Ver Todas
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Últimos Leads -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-tag"></i> Últimos Leads
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLeads as $lead)
                            <tr>
                                <td>{{ $lead->name }}</td>
                                <td>{{ $lead->contact }}</td>
                                <td>
                                    @if($lead->status == 'new')
                                        <span class="badge bg-primary">Nuevo</span>
                                    @elseif($lead->status == 'contacted')
                                        <span class="badge bg-info">Contactado</span>
                                    @elseif($lead->status == 'qualified')
                                        <span class="badge bg-success">Calificado</span>
                                    @elseif($lead->status == 'converted')
                                        <span class="badge bg-warning">Convertido</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $lead->status }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('customer.leads.show', $lead->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No hay leads recientes</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="{{ route('customer.leads.index') }}" class="btn btn-primary btn-sm">
                        Ver Todos
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Datos para el gráfico de tipos de cliente
    const typeData = {
        labels: [
            @foreach($customersByType as $type)
                '{{ ucfirst($type->customer_type) }}',
            @endforeach
        ],
        datasets: [{
            data: [
                @foreach($customersByType as $type)
                    {{ $type->total }},
                @endforeach
            ],
            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
            hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617'],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }]
    };

    // Datos para el gráfico de segmentos
    const segmentData = {
        labels: [
            @foreach($customersBySegment as $segment)
                '{{ ucfirst($segment->segment ?? "Sin segmento") }}',
            @endforeach
        ],
        datasets: [{
            data: [
                @foreach($customersBySegment as $segment)
                    {{ $segment->total }},
                @endforeach
            ],
            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
            hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617'],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }]
    };

    // Configuración de los gráficos
    document.addEventListener('DOMContentLoaded', function() {
        // Gráfico de tipos
        const typeChart = document.getElementById("customerTypeChart");
        new Chart(typeChart, {
            type: 'doughnut',
            data: typeData,
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de segmentos
        const segmentChart = document.getElementById("customerSegmentChart");
        new Chart(segmentChart, {
            type: 'doughnut',
            data: segmentData,
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    });
</script>
@endpush

<style>
    /* Ajustes específicos para el Dashboard de Clientes */
    .chart-container {
        padding: 0.5rem;
    }
</style>