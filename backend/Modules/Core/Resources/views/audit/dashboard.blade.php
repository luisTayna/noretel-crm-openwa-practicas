@extends('core::layouts.master')

@section('title', 'Dashboard de Auditoría')

@section('page-title', 'Dashboard de Auditoría')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Estadísticas de Auditoría</h5>
        <div>
            <form class="d-inline" method="GET" action="{{ route('core.audit.dashboard') }}">
                <div class="input-group">
                    <select name="days" class="form-select" onchange="this.form.submit()">
                        <option value="7" {{ $days == 7 ? 'selected' : '' }}>Últimos 7 días</option>
                        <option value="30" {{ $days == 30 ? 'selected' : '' }}>Últimos 30 días</option>
                        <option value="90" {{ $days == 90 ? 'selected' : '' }}>Últimos 90 días</option>
                        <option value="365" {{ $days == 365 ? 'selected' : '' }}>Último año</option>
                    </select>
                </div>
            </form>
            <a href="{{ route('core.audit.index') }}" class="btn btn-secondary ms-2">
                <i class="fa fa-list"></i> Ver Logs
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Actividad por Módulo</h6>
                    </div>
                    <div class="card-body">
                        @if($moduleStats->isEmpty())
                            <div class="alert alert-info mb-0">
                                No hay datos disponibles.
                            </div>
                        @else
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="moduleChart"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Actividad por Tipo de Acción</h6>
                    </div>
                    <div class="card-body">
                        @if($actionStats->isEmpty())
                            <div class="alert alert-info mb-0">
                                No hay datos disponibles.
                            </div>
                        @else
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="actionChart"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Actividad por Usuario (Top 10)</h6>
                    </div>
                    <div class="card-body">
                        @if($userStats->isEmpty())
                            <div class="alert alert-info mb-0">
                                No hay datos disponibles.
                            </div>
                        @else
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="userChart"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Actividad Diaria</h6>
                    </div>
                    <div class="card-body">
                        @if($dailyStats->isEmpty())
                            <div class="alert alert-info mb-0">
                                No hay datos disponibles.
                            </div>
                        @else
                            <div class="chart-container" style="position: relative; height:300px;">
                                <canvas id="dailyChart"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Colores para los gráficos
        const colors = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
            '#6f42c1', '#5a5c69', '#fd7e14', '#20c997', '#6610f2'
        ];

        // Gráfico de actividad por módulo
        @if(!$moduleStats->isEmpty())
        const moduleCtx = document.getElementById('moduleChart').getContext('2d');
        const moduleChart = new Chart(moduleCtx, {
            type: 'pie',
            data: {
                labels: [
                    @foreach($moduleStats as $stat)
                        '{{ ucfirst($stat->module) }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'Actividad por Módulo',
                    data: [
                        @foreach($moduleStats as $stat)
                            {{ $stat->total }},
                        @endforeach
                    ],
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        @endif

        // Gráfico de actividad por tipo de acción
        @if(!$actionStats->isEmpty())
        const actionCtx = document.getElementById('actionChart').getContext('2d');
        const actionChart = new Chart(actionCtx, {
            type: 'pie',
            data: {
                labels: [
                    @foreach($actionStats as $stat)
                        '{{ ucfirst(str_replace("_", " ", $stat->action_type)) }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'Actividad por Tipo de Acción',
                    data: [
                        @foreach($actionStats as $stat)
                            {{ $stat->total }},
                        @endforeach
                    ],
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        @endif

        // Gráfico de actividad por usuario
        @if(!$userStats->isEmpty())
        const userCtx = document.getElementById('userChart').getContext('2d');
        const userChart = new Chart(userCtx, {
            type: 'bar',
            data: {
                labels: [
                    @foreach($userStats as $stat)
                        '{{ $stat->user ? $stat->user->username : "N/A" }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'Número de Acciones',
                    data: [
                        @foreach($userStats as $stat)
                            {{ $stat->total }},
                        @endforeach
                    ],
                    backgroundColor: '#4e73df'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        @endif

        // Gráfico de actividad diaria
        @if(!$dailyStats->isEmpty())
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: [
                    @foreach($dailyStats as $stat)
                        '{{ \Carbon\Carbon::parse($stat->date)->format("d/m/Y") }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'Acciones por Día',
                    data: [
                        @foreach($dailyStats as $stat)
                            {{ $stat->total }},
                        @endforeach
                    ],
                    borderColor: '#4e73df',
                    tension: 0.1,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        @endif
    });
</script>
@endpush
