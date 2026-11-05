@extends('core::layouts.master')

@section('title', 'Dashboard de Red')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-network-wired"></i> Dashboard de Red
        </h1>
        <div>
            <a href="{{ route('network.routers.index') }}" class="btn btn-primary">
                <i class="fas fa-router"></i> Gestionar Routers
            </a>
        </div>
    </div>

    {{-- Estadísticas principales --}}
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Routers Activos
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['active_routers'] }} / {{ $stats['total_routers'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-router fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Clientes Conectados
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['total_clients'] }}
                            </div>
                            <small class="text-muted">
                                Capacidad: {{ $stats['capacity_percentage'] }}%
                            </small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Solicitudes Pendientes
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['pending_requests'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Routers con Problemas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $stats['offline_routers'] }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Routers con problemas --}}
    @if($problematicRouters->count() > 0)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Routers con Problemas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Router</th>
                                    <th>Zona</th>
                                    <th>Estado</th>
                                    <th>CPU</th>
                                    <th>Memoria</th>
                                    <th>Clientes</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($problematicRouters as $router)
                                <tr>
                                    <td>
                                        <a href="{{ route('network.routers.show', $router) }}">
                                            <strong>{{ $router->name }}</strong>
                                        </a>
                                        <br>
                                        <small class="text-muted">{{ $router->ip_address }}</small>
                                    </td>
                                    <td>{{ $router->zone }}</td>
                                    <td>
                                        <span class="badge badge-{{ $router->status === 'active' ? 'success' : 'danger' }}">
                                            {{ $router->status_label }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($router->cpu_usage)
                                            <span class="badge badge-{{ $router->cpu_usage > 80 ? 'danger' : 'warning' }}">
                                                {{ number_format($router->cpu_usage, 1) }}%
                                            </span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($router->memory_usage)
                                            <span class="badge badge-{{ $router->memory_usage > 85 ? 'danger' : 'warning' }}">
                                                {{ number_format($router->memory_usage, 1) }}%
                                            </span>
                                        @else
                                            <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $router->connected_clients }} / {{ $router->max_clients }}
                                        <small class="text-muted">({{ $router->load_percentage }}%)</small>
                                    </td>
                                    <td>
                                        <a href="{{ route('network.routers.show', $router) }}"
                                           class="btn btn-sm btn-primary"
                                           title="Ver detalles">
                                            <i class="fas fa-eye"></i>
                                        </a>
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
    @endif

    <div class="row">
        {{-- Solicitudes recientes --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-ticket-alt"></i> Solicitudes Recientes
                    </h6>
                    <a href="{{ route('network.service-requests.index') }}" class="btn btn-sm btn-primary">
                        Ver Todas
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentRequests as $request)
                                <tr>
                                    <td>
                                        <a href="{{ route('network.service-requests.show', $request) }}">
                                            {{ $request->ticket_number }}
                                        </a>
                                    </td>
                                    <td>
                                        <small>{{ $request->type_label }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $request->status === 'completed' ? 'success' : ($request->status === 'failed' ? 'danger' : 'warning') }}">
                                            {{ $request->status_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ $request->created_at->diffForHumans() }}</small>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">
                                        No hay solicitudes recientes
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Distribución por zona --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-map-marker-alt"></i> Distribución por Zona
                    </h6>
                </div>
                <div class="card-body">
                    @if($routersByZone->count() > 0)
                        @foreach($routersByZone as $zone)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="font-weight-bold">{{ $zone->zone }}</span>
                                <span class="badge badge-primary">{{ $zone->total }} routers</span>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar" role="progressbar"
                                     style="width: {{ ($zone->total / $stats['total_routers']) * 100 }}%"
                                     aria-valuenow="{{ $zone->total }}"
                                     aria-valuemin="0"
                                     aria-valuemax="{{ $stats['total_routers'] }}">
                                    {{ number_format(($zone->total / $stats['total_routers']) * 100, 1) }}%
                                </div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center">No hay routers registrados</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Distribución por marca --}}
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie"></i> Distribución por Marca
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($routersByBrand as $brand)
                        <div class="col-md-3 mb-3">
                            <div class="card border-left-info">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $brand->brand }}</h5>
                                    <p class="card-text">
                                        <span class="h3">{{ $brand->total }}</span>
                                        <small class="text-muted">routers</small>
                                    </p>
                                    <small class="text-muted">
                                        {{ number_format(($brand->total / $stats['total_routers']) * 100, 1) }}% del total
                                    </small>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Auto-refresh cada 30 segundos (opcional)
/*
setInterval(function() {
    location.reload();
}, 30000);
*/
</script>
@endpush
