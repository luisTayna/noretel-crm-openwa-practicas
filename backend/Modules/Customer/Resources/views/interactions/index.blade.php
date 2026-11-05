@extends('core::layouts.master')

@section('title', 'Interacciones')
@section('page-title', 'Gestión de Interacciones')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-comments"></i> Interacciones con Clientes
        </div>
        @can('create-customers')
        <a href="{{ route('customer.interactions.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Nueva Interacción
        </a>
        @endcan
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form action="{{ route('customer.interactions.index') }}" method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <select name="customer_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los clientes</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los tipos</option>
                        @foreach($interactionTypes as $type)
                            <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="channel" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los canales</option>
                        @foreach($channels as $channel)
                            <option value="{{ $channel }}" {{ request('channel') == $channel ? 'selected' : '' }}>
                                {{ ucfirst($channel) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="follow_up" id="follow_up" value="1" {{ request('follow_up') ? 'checked' : '' }} onChange="this.form.submit()">
                        <label class="form-check-label" for="follow_up">
                            Requiere seguimiento
                        </label>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <a href="{{ route('customer.interactions.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sync me-1"></i> Reiniciar
                    </a>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-3 align-self-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar por Fecha</button>
                </div>
            </div>
        </form>

        <!-- Tabla de interacciones -->
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Canal</th>
                        <th>Fecha</th>
                        <th>Registrado por</th>
                        <th>Seguimiento</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($interactions as $interaction)
                    <tr>
                        <td>{{ $interaction->id }}</td>
                        <td>
                            <a href="{{ route('customer.customers.show', $interaction->customer_id) }}">
                                {{ $interaction->customer->full_name }}
                            </a>
                        </td>
                        <td>{{ $interaction->interaction_type }}</td>
                        <td>{{ $interaction->channel }}</td>
                        <td>{{ $interaction->date->format('d/m/Y H:i') }}</td>
                        <td>{{ $interaction->createdBy ? $interaction->createdBy->username : 'N/A' }}</td>
                        <td>
                            @if($interaction->follow_up_required)
                                <span class="badge bg-warning">Requiere seguimiento</span>
                            @else
                                <span class="badge bg-success">Completo</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-btn-group">
                                <a href="{{ route('customer.interactions.show', $interaction->id) }}" class="action-btn" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                @can('edit-customers')
                                <a href="{{ route('customer.interactions.edit', $interaction->id) }}" class="action-btn" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                @if($interaction->follow_up_required)
                                    <button type="button" class="action-btn" title="Marcar como Completo" data-bs-toggle="modal" data-bs-target="#unmarkFollowUpModal{{ $interaction->id }}">
                                        <i class="fas fa-check"></i>
                                    </button>
                                @else
                                    <button type="button" class="action-btn" title="Marcar para Seguimiento" data-bs-toggle="modal" data-bs-target="#markFollowUpModal{{ $interaction->id }}">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                @endif
                                @endcan
                                
                                @can('delete-customers')
                                <button type="button" class="action-btn" title="Eliminar" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $interaction->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endcan
                            </div>
                            
                            <!-- Modal para marcar para seguimiento -->
                            @can('edit-customers')
                            <div class="modal fade" id="markFollowUpModal{{ $interaction->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Marcar para Seguimiento</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>¿Marcar esta interacción para seguimiento posterior?</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="{{ route('customer.interactions.mark-follow-up', $interaction->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-primary">Marcar para Seguimiento</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Modal para desmarcar seguimiento -->
                            <div class="modal fade" id="unmarkFollowUpModal{{ $interaction->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Marcar como Completo</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>¿Marcar esta interacción como completa (sin necesidad de seguimiento)?</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="{{ route('customer.interactions.unmark-follow-up', $interaction->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-success">Marcar como Completo</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endcan
                            
                            <!-- Modal para eliminar interacción -->
                            @can('delete-customers')
                            <div class="modal fade" id="deleteModal{{ $interaction->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Eliminar Interacción</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>¿Está seguro de que desea eliminar esta interacción?</p>
                                            <p class="text-danger"><strong>Atención:</strong> Esta acción no se puede deshacer.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="{{ route('customer.interactions.destroy', $interaction->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">Eliminar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center">No se encontraron interacciones con los criterios de búsqueda</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="d-flex justify-content-center mt-4">
            {{ $interactions->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection
