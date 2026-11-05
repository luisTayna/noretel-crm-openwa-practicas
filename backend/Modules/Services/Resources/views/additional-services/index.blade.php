@extends('services::layouts.master')

@section('title', isset($service) ? 'Servicios Adicionales de ' . $service->name : 'Gestión de Servicios Adicionales')
@section('page-title', isset($service) ? 'Servicios Adicionales de ' . $service->name : 'Gestión de Servicios Adicionales')

@section('actions')
    <div class="d-flex gap-2">
        @if(isset($service))
            <a href="{{ route('services.services.show', $service->id) }}" class="btn btn-glass-action px-3 py-2 d-flex align-items-center rounded-pill fw-semibold">
                <i class="fas fa-arrow-left me-2"></i> Volver al servicio
            </a>
        @else
            <a href="{{ route('services.dashboard') }}" class="btn btn-glass-action px-3 py-2 d-flex align-items-center rounded-pill fw-semibold">
                <i class="fas fa-arrow-left me-2"></i> Volver al dashboard
            </a>
        @endif

        @if(auth()->user()->hasPermission('create', 'additional-services'))
            <a href="{{ route('services.additional-services.create', isset($service) ? ['service_id' => $service->id] : []) }}" class="btn btn-primary px-4 py-2 d-flex align-items-center rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                <i class="fas fa-plus-circle me-2"></i> Nuevo Adicional
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="card glass-card mb-4 border-0">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between border-0 bg-transparent">
            <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);"><i class="fas fa-plus-circle text-muted me-2"></i> Listado de Servicios Adicionales</h6>
        </div>
        <div class="card-body p-0">
            @if(count($additionalServices) > 0)
                <div class="table-responsive">
                    <table class="table table-glass mb-0" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="ps-4">Nombre</th>
                                <th>Servicio</th>
                                <th>Precio</th>
                                <th class="text-center">Configurable</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($additionalServices as $additionalService)
                                <tr>
                                    <td class="ps-4">
                                        <a href="{{ route('services.additional-services.show', $additionalService->id) }}" class="fw-bold text-decoration-none" style="color: var(--text-color);">
                                            {{ $additionalService->name }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('services.services.show', $additionalService->service_id) }}" class="text-decoration-none">
                                            <span class="badge badge-pill badge-plan bg-transparent">{{ $additionalService->service->name }}</span>
                                        </a>
                                    </td>
                                    <td class="text-dark fw-bold">S/ {{ number_format($additionalService->price, 2) }}</td>
                                    <td class="text-center">
                                        @if($additionalService->configurable)
                                            <span class="badge badge-pill badge-status-paid bg-transparent px-3">Sí</span>
                                        @else
                                            <span class="badge badge-pill badge-status-pending bg-transparent px-3">No</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('services.additional-services.show', $additionalService->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Ver detalle">
                                                <i class="fas fa-eye text-info"></i>
                                            </a>

                                            @if(auth()->user()->hasPermission('edit', 'additional-services'))
                                                <a href="{{ route('services.additional-services.edit', $additionalService->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Editar servicio adicional">
                                                    <i class="fas fa-edit text-warning"></i>
                                                </a>
                                            @endif

                                            @if(auth()->user()->hasPermission('delete', 'additional-services'))
                                                <form action="{{ route('services.additional-services.destroy', $additionalService->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Eliminar servicio adicional" onclick="return confirm('¿Está seguro de eliminar este servicio adicional? Esta acción no se puede deshacer.')">
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
                    <i class="fas fa-info-circle me-2 text-info"></i>No hay servicios adicionales registrados.
                    @if(auth()->user()->hasPermission('create', 'additional-services'))
                        <a href="{{ route('services.additional-services.create', isset($service) ? ['service_id' => $service->id] : []) }}" class="alert-link ms-1">Crear un nuevo servicio adicional</a>.
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
