@extends('services::layouts.master')

@section('title', 'Gestión de Servicios')
@section('page-title', 'Gestión de Servicios')

@section('actions')
    <div class="d-flex gap-2">
        @if(auth()->user()->hasPermission('create', 'services'))
            <a href="{{ route('services.services.create') }}" class="btn btn-primary px-4 py-2 d-flex align-items-center rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                <i class="fas fa-plus-circle me-2"></i> Nuevo Servicio
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="card glass-card mb-4 border-0">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between border-0 bg-transparent">
            <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);"><i class="fas fa-list text-muted me-2"></i> Listado de Servicios</h6>
            <div class="dropdown no-arrow">
                <a class="btn btn-glass-action btn-sm rounded-circle" href="#" role="button" id="dropdownMenuLink"
                   data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="fas fa-ellipsis-v"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="dropdownMenuLink" style="border-radius: 12px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                    <div class="dropdown-header">Opciones:</div>
                    <a class="dropdown-item rounded py-2" href="{{ route('services.services.index') }}?filter=active">
                        <i class="fas fa-check-circle me-2 text-success"></i> Mostrar solo activos
                    </a>
                    <a class="dropdown-item rounded py-2" href="{{ route('services.services.index') }}?filter=inactive">
                        <i class="fas fa-times-circle me-2 text-danger"></i> Mostrar solo inactivos
                    </a>
                    <a class="dropdown-item rounded py-2" href="{{ route('services.services.index') }}">
                        <i class="fas fa-list me-2 text-primary"></i> Mostrar todos
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if($services && count($services) > 0)
                <div class="table-responsive">
                    <table class="table table-glass mb-0" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="ps-4">Nombre</th>
                                <th>Tipo</th>
                                <th>Tecnología</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Planes</th>
                                <th class="text-center">Servicios Adicionales</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($services as $service)
                                <tr>
                                    <td class="ps-4">
                                        <a href="{{ route('services.services.show', $service->id) }}" class="fw-bold text-decoration-none" style="color: var(--text-color);">
                                            {{ $service->name }}
                                        </a>
                                    </td>
                                    <td class="text-muted">{{ $service->service_type_label }}</td>
                                    <td class="text-muted">{{ $service->technology_label }}</td>
                                    <td class="text-center">
                                        @if($service->active)
                                             <span class="badge badge-pill badge-status-paid bg-transparent">Activo</span>
                                        @else
                                            <span class="badge badge-pill badge-status-overdue bg-transparent">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-center text-muted">
                                        <span class="badge badge-pill badge-plan me-1">{{ $service->plans_count ?? 0 }}</span>
                                        <a href="{{ route('services.plans.index', ['service_id' => $service->id]) }}" class="btn btn-sm btn-glass-action rounded-circle" style="width: 24px; height: 24px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-external-link-alt" style="font-size: 0.7rem;"></i>
                                        </a>
                                    </td>
                                    <td class="text-center text-muted">
                                        <span class="badge badge-pill badge-plan me-1">{{ $service->additional_services_count ?? 0 }}</span>
                                        <a href="{{ route('services.additional-services.index', ['service_id' => $service->id]) }}" class="btn btn-sm btn-glass-action rounded-circle" style="width: 24px; height: 24px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-external-link-alt" style="font-size: 0.7rem;"></i>
                                        </a>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('services.services.show', $service->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Ver detalle">
                                                <i class="fas fa-eye text-info"></i>
                                            </a>

                                            @if(auth()->user()->hasPermission('edit', 'services'))
                                                <a href="{{ route('services.services.edit', $service->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Editar servicio">
                                                    <i class="fas fa-edit text-warning"></i>
                                                </a>

                                                @if($service->active)
                                                    <form action="{{ route('services.services.deactivate', $service->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('POST')
                                                        <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Desactivar servicio" onclick="return confirm('¿Está seguro de desactivar este servicio?')">
                                                            <i class="fas fa-toggle-off text-muted"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('services.services.activate', $service->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('POST')
                                                        <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Activar servicio" onclick="return confirm('¿Está seguro de activar este servicio?')">
                                                            <i class="fas fa-toggle-on text-success"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            @if(auth()->user()->hasPermission('delete', 'services'))
                                                <form action="{{ route('services.services.destroy', $service->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Eliminar servicio" onclick="return confirm('¿Está seguro de eliminar este servicio? Esta acción no se puede deshacer.')">
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
                <div class="alert alert-info text-center m-4">
                    No hay servicios registrados.
                    @if(auth()->user()->hasPermission('create', 'services'))
                        <a href="{{ route('services.services.create') }}" class="alert-link ms-1">Crear un nuevo servicio</a>.
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection

