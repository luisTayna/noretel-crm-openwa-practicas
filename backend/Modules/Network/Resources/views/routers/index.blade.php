@extends('core::layouts.master')

@section('title', 'Gestión de Routers')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-router"></i> Gestión de Routers
        </h1>
        <a href="{{ route('network.routers.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Router
        </a>
    </div>

    {{-- Filtros --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('network.routers.index') }}" class="form-inline">
                <div class="form-group mr-2">
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar..." value="{{ request('search') }}">
                </div>

                <div class="form-group mr-2">
                    <select name="zone" class="form-control">
                        <option value="">Todas las zonas</option>
                        @foreach($zones as $zone)
                        <option value="{{ $zone }}" {{ request('zone') == $zone ? 'selected' : '' }}>
                            {{ $zone }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group mr-2">
                    <select name="brand" class="form-control">
                        <option value="">Todas las marcas</option>
                        @foreach($brands as $brand)
                        <option value="{{ $brand }}" {{ request('brand') == $brand ? 'selected' : '' }}>
                            {{ $brand }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group mr-2">
                    <select name="status" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Activo</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactivo</option>
                        <option value="maintenance" {{ request('status') == 'maintenance' ? 'selected' : '' }}>Mantenimiento</option>
                        <option value="error" {{ request('status') == 'error' ? 'selected' : '' }}>Error</option>
                        <option value="offline" {{ request('status') == 'offline' ? 'selected' : '' }}>Offline</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary mr-2">
                    <i class="fas fa-search"></i> Buscar
                </button>

                <a href="{{ route('network.routers.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </form>
        </div>
    </div>

    {{-- Lista de routers --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Marca</th>
                            <th>IP</th>
                            <th>Zona</th>
                            <th>Estado</th>
                            <th>Clientes</th>
                            <th>Salud</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routers as $router)
                        <tr>
                            <td>
                                <code>{{ $router->code }}</code>
                            </td>
                            <td>
                                <a href="{{ route('network.routers.show', $router) }}">
                                    <strong>{{ $router->name }}</strong>
                                </a>
                                @if($router->node)
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-sitemap"></i> {{ $router->node->name }}
                                </small>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $router->brand }}</span>
                                @if($router->model)
                                <br>
                                <small class="text-muted">{{ $router->model }}</small>
                                @endif
                            </td>
                            <td>
                                <code>{{ $router->ip_address }}</code>
                            </td>
                            <td>{{ $router->zone }}</td>
                            <td>
                                <span class="badge badge-{{ $router->status === 'active' ? 'success' : ($router->status === 'offline' ? 'danger' : 'warning') }}">
                                    {{ $router->status_label }}
                                </span>
                            </td>
                            <td>
                                {{ $router->connected_clients }} / {{ $router->max_clients }}
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar bg-{{ $router->load_percentage > 85 ? 'danger' : ($router->load_percentage > 70 ? 'warning' : 'success') }}"
                                         style="width: {{ $router->load_percentage }}%">
                                    </div>
                                </div>
                                <small class="text-muted">{{ $router->load_percentage }}%</small>
                            </td>
                            <td>
                                @if($router->health_status === 'good')
                                    <i class="fas fa-check-circle text-success"></i>
                                @elseif($router->health_status === 'warning')
                                    <i class="fas fa-exclamation-triangle text-warning"></i>
                                @else
                                    <i class="fas fa-times-circle text-danger"></i>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('network.routers.show', $router) }}"
                                       class="btn btn-sm btn-info"
                                       title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('network.routers.edit', $router) }}"
                                       class="btn btn-sm btn-primary"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('network.routers.reboot', $router) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Reiniciar router {{ $router->name }}?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-warning" title="Reiniciar">
                                            <i class="fas fa-sync"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No hay routers registrados</p>
                                <a href="{{ route('network.routers.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Crear Primer Router
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if($routers->hasPages())
            <div class="d-flex justify-content-center">
                {{ $routers->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
