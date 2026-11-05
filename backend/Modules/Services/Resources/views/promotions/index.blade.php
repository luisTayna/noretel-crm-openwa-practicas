@extends('services::layouts.master')

@section('title', 'Gestión de Promociones')
@section('page-title', 'Gestión de Promociones')

@section('actions')
    <div class="d-flex gap-2">
        <a href="{{ route('services.dashboard') }}" class="btn btn-glass-action px-3 py-2 d-flex align-items-center rounded-pill fw-semibold">
            <i class="fas fa-arrow-left me-2"></i> Volver al dashboard
        </a>

        @if(auth()->user()->hasPermission('create', 'promotions'))
            <a href="{{ route('services.promotions.create') }}" class="btn btn-primary px-4 py-2 d-flex align-items-center rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                <i class="fas fa-plus-circle me-2"></i> Nueva Promoción
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="card glass-card mb-4 border-0">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between border-0 bg-transparent">
            <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);"><i class="fas fa-tags text-muted me-2"></i> Listado de Promociones</h6>
            <div class="dropdown no-arrow">
                <a class="btn btn-glass-action btn-sm rounded-circle" href="#" role="button" id="dropdownMenuLink"
                   data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fas fa-ellipsis-v"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="dropdownMenuLink" style="border-radius: 12px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                    <div class="dropdown-header">Opciones:</div>
                    <a class="dropdown-item rounded py-2" href="{{ route('services.promotions.index', ['active' => 1]) }}">
                        <i class="fas fa-check-circle me-2 text-success"></i> Mostrar solo activas
                    </a>
                    <a class="dropdown-item rounded py-2" href="{{ route('services.promotions.index', ['active' => 0]) }}">
                        <i class="fas fa-times-circle me-2 text-danger"></i> Mostrar solo inactivas
                    </a>
                    <a class="dropdown-item rounded py-2" href="{{ route('services.promotions.index') }}">
                        <i class="fas fa-list me-2 text-primary"></i> Mostrar todas
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if(count($promotions) > 0)
                <div class="table-responsive">
                    <table class="table table-glass mb-0" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="ps-4">Nombre</th>
                                <th>Descuento</th>
                                <th>Período de Validez</th>
                                <th class="text-center">Planes</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($promotions as $promotion)
                                <tr>
                                    <td class="ps-4">
                                        <a href="{{ route('services.promotions.show', $promotion->id) }}" class="fw-bold text-decoration-none" style="color: var(--text-color);">
                                            {{ $promotion->name }}
                                        </a>
                                    </td>
                                    <td class="text-dark fw-bold">
                                        @if($promotion->discount_type === 'percentage')
                                            <span class="badge badge-pill badge-status-overdue bg-transparent"><i class="fas fa-percent me-1"></i>{{ $promotion->discount }}% OFF</span>
                                        @else
                                            <span class="badge badge-pill badge-status-overdue bg-transparent"><i class="fas fa-tag me-1"></i>S/ {{ number_format($promotion->discount, 2) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted small">{{ $promotion->start_date->format('d/m/Y') }} - {{ $promotion->end_date->format('d/m/Y') }}</span>
                                            @if($promotion->start_date > now())
                                                <span class="badge badge-pill badge-status-pending bg-transparent small px-2">Próxima</span>
                                            @elseif($promotion->end_date < now())
                                                <span class="badge badge-pill bg-transparent text-muted border border-secondary small px-2">Finalizada</span>
                                            @else
                                                <span class="badge badge-pill badge-status-paid bg-transparent small px-2">En curso</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center text-muted">
                                        <span class="badge badge-pill badge-plan bg-transparent px-3">{{ $promotion->plans_count ?? 0 }}</span>
                                    </td>
                                    <td class="text-center">
                                        @if($promotion->active)
                                            <span class="badge badge-pill badge-status-paid bg-transparent">Activa</span>
                                        @else
                                            <span class="badge badge-pill badge-status-overdue bg-transparent">Inactiva</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('services.promotions.show', $promotion->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Ver detalle">
                                                <i class="fas fa-eye text-info"></i>
                                            </a>

                                            @if(auth()->user()->hasPermission('edit', 'promotions'))
                                                <a href="{{ route('services.promotions.edit', $promotion->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Editar promoción">
                                                    <i class="fas fa-edit text-warning"></i>
                                                </a>

                                                @if($promotion->active)
                                                    <form action="{{ route('services.promotions.deactivate', $promotion->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('POST')
                                                        <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Desactivar promoción" onclick="return confirm('¿Está seguro de desactivar esta promoción?')">
                                                            <i class="fas fa-toggle-off text-muted"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('services.promotions.activate', $promotion->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('POST')
                                                        <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Activar promoción" onclick="return confirm('¿Está seguro de activar esta promoción?')">
                                                            <i class="fas fa-toggle-on text-success"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            @if(auth()->user()->hasPermission('delete', 'promotions'))
                                                <form action="{{ route('services.promotions.destroy', $promotion->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Eliminar promoción" onclick="return confirm('¿Está seguro de eliminar esta promoción? Esta acción no se puede deshacer.')">
                                                        <i class="fas fa-trash text-danger"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-info text-center m-4 bg-transparent border-0 glass-card">
                    <i class="fas fa-info-circle me-2 text-info"></i>No hay promociones registradas.
                    @if(auth()->user()->hasPermission('create', 'promotions'))
                        <a href="{{ route('services.promotions.create') }}" class="alert-link ms-1">Crear una nueva promoción</a>.
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
