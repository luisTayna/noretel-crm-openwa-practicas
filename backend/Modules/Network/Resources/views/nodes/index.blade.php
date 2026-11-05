@extends('core::layouts.master')

@section('title', 'Gestión de Nodos')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-sitemap"></i> Gestión de Nodos de Red
        </h1>
        <a href="{{ route('network.nodes.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Nodo
        </a>
    </div>

    {{-- Filtros --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('network.nodes.index') }}" class="form-inline">
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
                    <select name="type" class="form-control">
                        <option value="">Todos los tipos</option>
                        @foreach($types as $type)
                        <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>
                            {{ ucfirst($type) }}
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
                        <option value="planned" {{ request('status') == 'planned' ? 'selected' : '' }}>Planificado</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary mr-2">
                    <i class="fas fa-search"></i> Buscar
                </button>

                <a href="{{ route('network.nodes.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </form>
        </div>
    </div>

    {{-- Lista de nodos --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Zona</th>
                            <th>Ubicación</th>
                            <th>Capacidad</th>
                            <th>Carga</th>
                            <th>Routers</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($nodes as $node)
                        <tr>
                            <td><code>{{ $node->code }}</code></td>
                            <td>
                                <a href="{{ route('network.nodes.show', $node) }}">
                                    <strong>{{ $node->name }}</strong>
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $node->type_label }}</span>
                            </td>
                            <td>{{ $node->zone }}</td>
                            <td>
                                <small>{{ $node->location }}</small>
                            </td>
                            <td>{{ $node->capacity }}</td>
                            <td>
                                {{ $node->current_load }} / {{ $node->capacity }}
                                <div class="progress mt-1" style="height: 5px;">
                                    <div class="progress-bar bg-{{ $node->load_percentage > 85 ? 'danger' : ($node->load_percentage > 70 ? 'warning' : 'success') }}"
                                         style="width: {{ $node->load_percentage }}%">
                                    </div>
                                </div>
                                <small class="text-muted">{{ $node->load_percentage }}%</small>
                            </td>
                            <td>
                                <span class="badge badge-primary">{{ $node->routers->count() }}</span>
                            </td>
                            <td>
                                <span class="badge badge-{{ $node->status === 'active' ? 'success' : 'warning' }}">
                                    {{ ucfirst($node->status) }}
                                </span>
                                @if($node->is_operational)
                                    <i class="fas fa-check-circle text-success" title="Operacional"></i>
                                @else
                                    <i class="fas fa-times-circle text-danger" title="No operacional"></i>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('network.nodes.show', $node) }}"
                                       class="btn btn-sm btn-info"
                                       title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('network.nodes.edit', $node) }}"
                                       class="btn btn-sm btn-primary"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No hay nodos registrados</p>
                                <a href="{{ route('network.nodes.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Crear Primer Nodo
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if($nodes->hasPages())
            <div class="d-flex justify-content-center">
                {{ $nodes->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
