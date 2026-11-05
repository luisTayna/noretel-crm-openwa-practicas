@extends('core::layouts.master')

@section('title', 'Nodo: ' . $node->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-sitemap"></i> {{ $node->name }}
        </h1>
        <div>
            <a href="{{ route('network.nodes.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="{{ route('network.nodes.edit', $node) }}" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
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
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%">Código:</th>
                            <td><code>{{ $node->code }}</code></td>
                        </tr>
                        <tr>
                            <th>Tipo:</th>
                            <td><span class="badge badge-info">{{ $node->type_label }}</span></td>
                        </tr>
                        <tr>
                            <th>Estado:</th>
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
                        </tr>
                        <tr>
                            <th>Zona:</th>
                            <td>{{ $node->zone }}</td>
                        </tr>
                        <tr>
                            <th>Distrito:</th>
                            <td>{{ $node->district ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Provincia:</th>
                            <td>{{ $node->province ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Departamento:</th>
                            <td>{{ $node->department ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Ubicación:</th>
                            <td>{{ $node->location }}</td>
                        </tr>
                        <tr>
                            <th>Coordenadas:</th>
                            <td>
                                @if($node->latitude && $node->longitude)
                                    {{ $node->latitude }}, {{ $node->longitude }}
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Radio de Cobertura:</th>
                            <td>{{ $node->coverage_radius }} metros</td>
                        </tr>
                        @if($node->activated_at)
                        <tr>
                            <th>Activado:</th>
                            <td>{{ $node->activated_at->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Capacidad y Carga --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Capacidad y Carga</h6>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5>Carga Actual</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>{{ $node->current_load }} / {{ $node->capacity }} clientes</span>
                            <strong>{{ $node->load_percentage }}%</strong>
                        </div>
                        <div class="progress" style="height: 30px;">
                            <div class="progress-bar bg-{{ $node->load_percentage > 85 ? 'danger' : ($node->load_percentage > 70 ? 'warning' : 'success') }}" 
                                 style="width: {{ $node->load_percentage }}%">
                                {{ $node->load_percentage }}%
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="row text-center">
                        <div class="col-6">
                            <div class="border-right">
                                <h4 class="mb-0">{{ $node->routers->count() }}</h4>
                                <small class="text-muted">Routers Asignados</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <h4 class="mb-0">{{ $node->capacity - $node->current_load }}</h4>
                            <small class="text-muted">Capacidad Disponible</small>
                        </div>
                    </div>

                    @if($node->description)
                    <hr>
                    <h6>Descripción:</h6>
                    <p class="mb-0">{{ $node->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Jerarquía --}}
    @if($node->parentNode || $node->childNodes->count() > 0)
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Jerarquía de Red</h6>
                </div>
                <div class="card-body">
                    @if($node->parentNode)
                    <div class="mb-3">
                        <h6>Nodo Padre:</h6>
                        <a href="{{ route('network.nodes.show', $node->parentNode) }}" class="btn btn-outline-primary">
                            <i class="fas fa-level-up-alt"></i> {{ $node->parentNode->name }}
                        </a>
                    </div>
                    @endif

                    @if($node->childNodes->count() > 0)
                    <div>
                        <h6>Nodos Hijos:</h6>
                        <div class="row">
                            @foreach($node->childNodes as $child)
                            <div class="col-md-3 mb-2">
                                <a href="{{ route('network.nodes.show', $child) }}" class="btn btn-outline-secondary btn-block">
                                    <i class="fas fa-level-down-alt"></i> {{ $child->name }}
                                </a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Routers Asignados --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Routers Asignados ({{ $node->routers->count() }})
                    </h6>
                </div>
                <div class="card-body">
                    @if($node->routers->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>IP</th>
                                    <th>Marca</th>
                                    <th>Clientes</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($node->routers as $router)
                                <tr>
                                    <td><code>{{ $router->code }}</code></td>
                                    <td>
                                        <a href="{{ route('network.routers.show', $router) }}">
                                            {{ $router->name }}
                                        </a>
                                    </td>
                                    <td><code>{{ $router->ip_address }}</code></td>
                                    <td><span class="badge badge-info">{{ $router->brand }}</span></td>
                                    <td>
                                        {{ $router->connected_clients }} / {{ $router->max_clients }}
                                        <small class="text-muted">({{ $router->load_percentage }}%)</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $router->status === 'active' ? 'success' : 'danger' }}">
                                            {{ $router->status_label }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('network.routers.show', $router) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-center text-muted mb-0">No hay routers asignados a este nodo</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection