@extends('core::layouts.master')

@section('title', 'Solicitudes de Servicio')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-ticket-alt"></i> Solicitudes de Servicio
        </h1>
    </div>

    {{-- Filtros --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('network.service-requests.index') }}" class="form-inline">
                <div class="form-group mr-2">
                    <input type="text" name="search" class="form-control"
                           placeholder="Buscar por ticket..." value="{{ request('search') }}">
                </div>

                <div class="form-group mr-2">
                    <select name="status" class="form-control">
                        <option value="">Todos los estados</option>
                        @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group mr-2">
                    <select name="type" class="form-control">
                        <option value="">Todos los tipos</option>
                        @foreach($types as $type)
                        <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group mr-2">
                    <select name="priority" class="form-control">
                        <option value="">Todas las prioridades</option>
                        @foreach($priorities as $priority)
                        <option value="{{ $priority }}" {{ request('priority') == $priority ? 'selected' : '' }}>
                            {{ ucfirst($priority) }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-primary mr-2">
                    <i class="fas fa-search"></i> Buscar
                </button>

                <a href="{{ route('network.service-requests.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Limpiar
                </a>
            </form>
        </div>
    </div>

    {{-- Lista de solicitudes --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Router</th>
                            <th>Estado</th>
                            <th>Prioridad</th>
                            <th>Técnico</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceRequests as $request)
                        <tr>
                            <td>
                                <a href="{{ route('network.service-requests.show', $request) }}">
                                    <strong>{{ $request->ticket_number }}</strong>
                                </a>
                                @if($request->is_automated)
                                <br>
                                <small class="badge badge-info">Auto</small>
                                @endif
                            </td>
                            <td>
                                @if($request->customer)
                                    {{ $request->customer->user->name ?? 'N/A' }}
                                    <br>
                                    <small class="text-muted">{{ $request->customer->user->email ?? '' }}</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ $request->type_label }}</small>
                            </td>
                            <td>
                                @if($request->router)
                                    <a href="{{ route('network.routers.show', $request->router) }}">
                                        {{ $request->router->name }}
                                    </a>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{
                                    $request->status === 'completed' ? 'success' :
                                    ($request->status === 'failed' ? 'danger' :
                                    ($request->status === 'in_progress' ? 'info' : 'warning'))
                                }}">
                                    {{ $request->status_label }}
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-{{
                                    $request->priority === 'critical' || $request->priority === 'high' ? 'danger' :
                                    ($request->priority === 'medium' ? 'warning' : 'secondary')
                                }}">
                                    {{ $request->priority_label }}
                                </span>
                            </td>
                            <td>
                                @if($request->assignedTechnician)
                                    {{ $request->assignedTechnician->name }}
                                @else
                                    <span class="text-muted">Sin asignar</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ $request->created_at->format('d/m/Y H:i') }}</small>
                                <br>
                                <small class="text-muted">{{ $request->created_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <a href="{{ route('network.service-requests.show', $request) }}"
                                   class="btn btn-sm btn-info"
                                   title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No hay solicitudes de servicio</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if($serviceRequests->hasPages())
            <div class="d-flex justify-content-center">
                {{ $serviceRequests->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
