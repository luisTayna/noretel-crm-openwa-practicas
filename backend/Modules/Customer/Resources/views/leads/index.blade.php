@extends('core::layouts.master')

@section('title', 'Leads')
@section('page-title', 'Gestión de Leads')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-user-tag"></i> Leads
        </div>
        @can('create-leads')
        <a href="{{ route('customer.leads.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Nuevo Lead
        </a>
        @endcan
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form action="{{ route('customer.leads.index') }}" method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Buscar por nombre o contacto" name="search" value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        @foreach($leadStatuses as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="source" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas las fuentes</option>
                        @foreach($leadSources as $source)
                            <option value="{{ $source }}" {{ request('source') == $source ? 'selected' : '' }}>
                                {{ ucfirst($source) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check me-3">
                        <input class="form-check-input" type="checkbox" name="unconverted" id="unconverted" value="1" {{ request('unconverted') ? 'checked' : '' }} onChange="this.form.submit()">
                        <label class="form-check-label" for="unconverted">
                            Solo no convertidos
                        </label>
                    </div>
                    <a href="{{ route('customer.leads.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-sync me-1"></i> Reiniciar
                    </a>
                </div>
            </div>
        </form>

        <!-- Tabla de leads -->
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Fuente</th>
                        <th>Interés</th>
                        <th>Fecha Captura</th>
                        <th>Estado</th>
                        <th>Valor Potencial</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leads as $lead)
                    <tr>
                        <td>{{ $lead->id }}</td>
                        <td>{{ $lead->name }}</td>
                        <td>{{ $lead->phone ?: $lead->contact }}</td>
                        <td>{{ $lead->source }}</td>
                        <td>{{ $lead->interest ?: '-' }}</td>
                        <td>{{ $lead->capture_date ? $lead->capture_date->format('d/m/Y') : '-' }}</td>
                        <td>
                            @if($lead->status == 'new')
                                <span class="badge bg-primary">Nuevo</span>
                            @elseif($lead->status == 'contacted')
                                <span class="badge bg-info">Contactado</span>
                            @elseif($lead->status == 'qualified')
                                <span class="badge bg-success">Calificado</span>
                            @elseif($lead->status == 'proposal')
                                <span class="badge bg-warning">Propuesta</span>
                            @elseif($lead->status == 'negotiation')
                                <span class="badge bg-info">Negociación</span>
                            @elseif($lead->status == 'converted')
                                <span class="badge bg-success">Convertido</span>
                            @elseif($lead->status == 'lost')
                                <span class="badge bg-danger">Perdido</span>
                            @else
                                <span class="badge bg-secondary">{{ $lead->status }}</span>
                            @endif
                        </td>
                        <td>
                            @if($lead->potential_value)
                                ${{ number_format($lead->potential_value, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="action-btn-group">
                                <a href="{{ route('customer.leads.show', $lead->id) }}" class="action-btn" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                @can('edit-leads')
                                <a href="{{ route('customer.leads.edit', $lead->id) }}" class="action-btn" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <button type="button" class="action-btn" title="Cambiar Estado" data-bs-toggle="modal" data-bs-target="#statusModal{{ $lead->id }}">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                
                                @endcan

                                @can('convert-leads')
                                @if($lead->status != 'converted')
                                <a href="{{ route('customer.leads.convert-form', $lead->id) }}" class="action-btn" title="Convertir a Cliente">
                                    <i class="fas fa-user-plus"></i>
                                </a>
                                @endif
                                @endcan
                                
                                @can('delete-leads')
                                @if($lead->status != 'converted')
                                <button type="button" class="action-btn" title="Eliminar" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $lead->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endif
                                @endcan
                            </div>
                            
                            <!-- Modal para cambiar estado -->
                            @can('edit-leads')
                            <div class="modal fade" id="statusModal{{ $lead->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Cambiar Estado del Lead</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="{{ route('customer.leads.change-status', $lead->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Estado</label>
                                                    <select name="status" id="status" class="form-select" required>
                                                        <option value="new" {{ $lead->status == 'new' ? 'selected' : '' }}>Nuevo</option>
                                                        <option value="contacted" {{ $lead->status == 'contacted' ? 'selected' : '' }}>Contactado</option>
                                                        <option value="qualified" {{ $lead->status == 'qualified' ? 'selected' : '' }}>Calificado</option>
                                                        <option value="proposal" {{ $lead->status == 'proposal' ? 'selected' : '' }}>Propuesta</option>
                                                        <option value="negotiation" {{ $lead->status == 'negotiation' ? 'selected' : '' }}>Negociación</option>
                                                        <option value="converted" {{ $lead->status == 'converted' ? 'selected' : '' }}>Convertido</option>
                                                        <option value="lost" {{ $lead->status == 'lost' ? 'selected' : '' }}>Perdido</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endcan
                            
                            <!-- Modal para eliminar lead -->
                            @can('delete-leads')
                            <div class="modal fade" id="deleteModal{{ $lead->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Eliminar Lead</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>¿Está seguro de que desea eliminar el lead <strong>{{ $lead->name }}</strong>?</p>
                                            <p class="text-danger"><strong>Atención:</strong> Esta acción no se puede deshacer.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="{{ route('customer.leads.destroy', $lead->id) }}" method="POST">
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
                        <td colspan="9" class="text-center">No se encontraron leads con los criterios de búsqueda</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="d-flex justify-content-center mt-4">
            {{ $leads->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection
