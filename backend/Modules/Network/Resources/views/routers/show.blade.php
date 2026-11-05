@extends('core::layouts.master')

@section('title', 'Router: ' . $router->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-router"></i> {{ $router->name }}
        </h1>
        <div>
            <a href="{{ route('network.routers.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="{{ route('network.routers.edit', $router) }}" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            @can('routers.ssh')
            <a href="{{ route('network.ssh.show', $router) }}" class="btn btn-dark">
                <i class="fas fa-terminal"></i> Terminal SSH
            </a>
            @endcan
            <form action="{{ route('network.routers.reboot', $router) }}"
                  method="POST"
                  class="d-inline"
                  onsubmit="return confirm('¿Está seguro de reiniciar este router?')">
                @csrf
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-sync"></i> Reiniciar
                </button>
            </form>
        </div>
    </div>

    <div class="row">
        {{-- Información General --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información General</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Código:</th>
                            <td><code>{{ $router->code }}</code></td>
                        </tr>
                        <tr>
                            <th>Marca:</th>
                            <td>
                                <span class="badge badge-info">{{ $router->brand }}</span>
                                @if($router->model)
                                    <small class="text-muted">{{ $router->model }}</small>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>IP:</th>
                            <td><code>{{ $router->ip_address }}</code></td>
                        </tr>
                        <tr>
                            <th>MAC:</th>
                            <td><code>{{ $router->mac_address ?? 'N/A' }}</code></td>
                        </tr>
                        <tr>
                            <th>Zona:</th>
                            <td>{{ $router->zone }}</td>
                        </tr>
                        <tr>
                            <th>Ubicación:</th>
                            <td>{{ $router->location ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Nodo:</th>
                            <td>
                                @if($router->node)
                                    <a href="{{ route('network.nodes.show', $router->node) }}">
                                        {{ $router->node->name }}
                                    </a>
                                @else
                                    <span class="text-muted">Sin nodo asignado</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Estado:</th>
                            <td>
                                <span class="badge badge-{{ $router->status === 'active' ? 'success' : 'danger' }}">
                                    {{ $router->status_label }}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Métricas Actuales --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Métricas Actuales</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>CPU:</span>
                            <strong>{{ $router->cpu_usage ? number_format($router->cpu_usage, 1) . '%' : 'N/A' }}</strong>
                        </div>
                        @if($router->cpu_usage)
                        <div class="progress">
                            <div class="progress-bar bg-{{ $router->cpu_usage > 80 ? 'danger' : ($router->cpu_usage > 60 ? 'warning' : 'success') }}"
                                 style="width: {{ $router->cpu_usage }}%"></div>
                        </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Memoria:</span>
                            <strong>{{ $router->memory_usage ? number_format($router->memory_usage, 1) . '%' : 'N/A' }}</strong>
                        </div>
                        @if($router->memory_usage)
                        <div class="progress">
                            <div class="progress-bar bg-{{ $router->memory_usage > 85 ? 'danger' : ($router->memory_usage > 70 ? 'warning' : 'success') }}"
                                 style="width: {{ $router->memory_usage }}%"></div>
                        </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Clientes Conectados:</span>
                            <strong>{{ $router->connected_clients }} / {{ $router->max_clients }}</strong>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-{{ $router->load_percentage > 85 ? 'danger' : ($router->load_percentage > 70 ? 'warning' : 'success') }}"
                                 style="width: {{ $router->load_percentage }}%">
                                {{ $router->load_percentage }}%
                            </div>
                        </div>
                    </div>

                    <hr>

                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td>Último reinicio:</td>
                            <td class="text-right">
                                <strong>{{ $router->last_reboot ? $router->last_reboot->diffForHumans() : 'Nunca' }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>Último health check:</td>
                            <td class="text-right">
                                <strong>{{ $router->last_health_check ? $router->last_health_check->diffForHumans() : 'Nunca' }}</strong>
                            </td>
                        </tr>
                        <tr>
                            <td>Estado de salud:</td>
                            <td class="text-right">
                                @if($router->health_status === 'good')
                                    <span class="badge badge-success">Bueno</span>
                                @elseif($router->health_status === 'warning')
                                    <span class="badge badge-warning">Advertencia</span>
                                @else
                                    <span class="badge badge-danger">Crítico</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Clientes Asignados --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Clientes Asignados ({{ $activeCustomers }})
                    </h6>
                </div>
                <div class="card-body">
                    @if($router->customers->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Usuario PPPoE</th>
                                    <th>Límite Bajada</th>
                                    <th>Límite Subida</th>
                                    <th>Estado</th>
                                    <th>Asignado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($router->customers as $customer)
                                <tr>
                                    <td>
                                        {{ $customer->user->name ?? 'N/A' }}
                                        <br>
                                        <small class="text-muted">{{ $customer->user->email ?? '' }}</small>
                                    </td>
                                    <td><code>{{ $customer->pivot->pppoe_username ?? 'N/A' }}</code></td>
                                    <td>{{ $customer->pivot->bandwidth_limit_down ?? 0 }} Mbps</td>
                                    <td>{{ $customer->pivot->bandwidth_limit_up ?? 0 }} Mbps</td>
                                    <td>
                                        <span class="badge badge-{{ $customer->pivot->connection_status === 'active' ? 'success' : 'danger' }}">
                                            {{ ucfirst($customer->pivot->connection_status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <small>{{ $customer->pivot->assigned_at ? \Carbon\Carbon::parse($customer->pivot->assigned_at)->format('d/m/Y') : 'N/A' }}</small>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-center text-muted mb-0">No hay clientes asignados a este router</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Solicitudes de Servicio --}}
    @if($router->serviceRequests->count() > 0)
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Solicitudes de Servicio Recientes</h6>
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
                                @foreach($router->serviceRequests as $request)
                                <tr>
                                    <td>
                                        <a href="{{ route('network.service-requests.show', $request) }}">
                                            {{ $request->ticket_number }}
                                        </a>
                                    </td>
                                    <td>{{ $request->type_label }}</td>
                                    <td>
                                        <span class="badge badge-{{ $request->status === 'completed' ? 'success' : 'warning' }}">
                                            {{ $request->status_label }}
                                        </span>
                                    </td>
                                    <td>{{ $request->created_at->diffForHumans() }}</td>
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
</div>
@endsection
