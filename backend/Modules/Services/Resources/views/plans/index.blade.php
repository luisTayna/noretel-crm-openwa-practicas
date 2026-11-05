@extends('services::layouts.master')

@section('title', isset($service) ? 'Planes de ' . $service->name : 'Gestión de Planes')
@section('page-title', isset($service) ? 'Planes de ' . $service->name : 'Gestión de Planes')

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

        @if(auth()->user()->hasPermission('create', 'plans'))
            <a href="{{ route('services.plans.create', isset($service) ? ['service_id' => $service->id] : []) }}" class="btn btn-primary px-4 py-2 d-flex align-items-center rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                <i class="fas fa-plus-circle me-2"></i> Nuevo Plan
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="card glass-card mb-4 border-0">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between border-0 bg-transparent">
            <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);"><i class="fas fa-network-wired text-muted me-2"></i> Listado de Planes</h6>
            <div class="d-flex align-items-center gap-2">
                @if(auth()->user()->hasPermission('create', 'plans'))
                    <a href="{{ route('services.plans.create', isset($service) ? ['service_id' => $service->id] : []) }}"
                       class="btn btn-primary btn-sm px-3 py-2 d-flex align-items-center rounded-pill fw-semibold shadow-sm"
                       style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                        <i class="fas fa-plus-circle me-2"></i> Nuevo Plan
                    </a>
                @endif

                <div class="dropdown no-arrow">
                    <a class="btn btn-glass-action btn-sm rounded-circle" href="#" role="button" id="dropdownMenuLink"
                       data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-ellipsis-v"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end shadow border-0 p-2" aria-labelledby="dropdownMenuLink" style="border-radius: 12px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                        <div class="dropdown-header">Opciones:</div>
                        <a class="dropdown-item rounded py-2" href="{{ route('services.plans.index', isset($service) ? ['service_id' => $service->id, 'active' => 1] : ['active' => 1]) }}">
                            <i class="fas fa-check-circle me-2 text-success"></i> Mostrar solo activos
                        </a>
                        <a class="dropdown-item rounded py-2" href="{{ route('services.plans.index', isset($service) ? ['service_id' => $service->id, 'active' => 0] : ['active' => 0]) }}">
                            <i class="fas fa-times-circle me-2 text-danger"></i> Mostrar solo inactivos
                        </a>
                        <a class="dropdown-item rounded py-2" href="{{ route('services.plans.index', isset($service) ? ['service_id' => $service->id] : []) }}">
                            <i class="fas fa-list me-2 text-primary"></i> Mostrar todos
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            @if(count($plans) > 0)
                <div class="table-responsive">
                    <table class="table table-glass mb-0" id="dataTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th class="ps-4">Nombre</th>
                                <th>Servicio</th>
                                <th>Velocidad</th>
                                <th>Precio</th>
                                <th>Promoción</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($plans as $plan)
                                <tr>
                                    <td class="ps-4">
                                        <a href="{{ route('services.plans.show', $plan->id) }}" class="fw-bold text-decoration-none" style="color: var(--text-color);">
                                            {{ $plan->name }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('services.services.show', $plan->service_id) }}" class="text-decoration-none">
                                            <span class="badge badge-pill badge-plan bg-transparent">{{ $plan->service->name }}</span>
                                        </a>
                                    </td>
                                    <td class="text-muted"><i class="fas fa-tachometer-alt me-1 text-primary"></i>{{ $plan->download_speed }}/{{ $plan->upload_speed }} Mbps</td>
                                    <td>
                                        @php
                                            $activePromotion = $plan->promotions->first();
                                            $discountedPrice = (float) $plan->price;

                                            if ($activePromotion) {
                                                if ($activePromotion->discount_type === 'percentage') {
                                                    $discountedPrice = (float) $plan->price * (100 - (float) $activePromotion->discount) / 100;
                                                } else {
                                                    $discountedPrice = max(0, (float) $plan->price - (float) $activePromotion->discount);
                                                }
                                            }
                                        @endphp

                                        @if($discountedPrice < $plan->price)
                                            <span class="text-decoration-line-through text-muted small me-1">S/ {{ number_format($plan->price, 2) }}</span>
                                            <span class="text-success fw-bold">S/ {{ number_format($discountedPrice, 2) }}</span>
                                        @else
                                            <span class="text-dark fw-bold">S/ {{ number_format($plan->price, 2) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($activePromotion)
                                            <span class="badge badge-pill badge-status-pending bg-transparent">
                                                <i class="fas fa-fire me-1 text-warning"></i>{{ $activePromotion->name }}
                                                @if($activePromotion->discount_type === 'percentage')
                                                    ({{ $activePromotion->discount }}%)
                                                @else
                                                    (S/ {{ number_format($activePromotion->discount, 2) }})
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-muted small">Sin promoción</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($plan->active)
                                            <span class="badge badge-pill badge-status-paid bg-transparent">Activo</span>
                                        @else
                                            <span class="badge badge-pill badge-status-overdue bg-transparent">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-flex justify-content-end gap-1">
                                            <a href="{{ route('services.plans.show', $plan->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Ver detalle">
                                                <i class="fas fa-eye text-info"></i>
                                            </a>

                                            @if(auth()->user()->hasPermission('edit', 'plans'))
                                                <a href="{{ route('services.plans.edit', $plan->id) }}" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Editar plan">
                                                    <i class="fas fa-edit text-warning"></i>
                                                </a>

                                                @if($plan->active)
                                                    <form action="{{ route('services.plans.deactivate', $plan->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('POST')
                                                        <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Desactivar plan" onclick="return confirm('¿Está seguro de desactivar este plan?')">
                                                            <i class="fas fa-toggle-off text-muted"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('services.plans.activate', $plan->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @method('POST')
                                                        <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Activar plan" onclick="return confirm('¿Está seguro de activar este plan?')">
                                                            <i class="fas fa-toggle-on text-success"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            @if(auth()->user()->hasPermission('delete', 'plans'))
                                                <form action="{{ route('services.plans.destroy', $plan->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-glass-action btn-sm rounded-circle" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;" title="Eliminar plan" onclick="return confirm('¿Está seguro de eliminar este plan? Esta acción no se puede deshacer.')">
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
                    <i class="fas fa-info-circle me-2 text-info"></i>No hay planes registrados.
                    @if(auth()->user()->hasPermission('create', 'plans'))
                        <a href="{{ route('services.plans.create', isset($service) ? ['service_id' => $service->id] : []) }}" class="alert-link ms-1">Crear un nuevo plan</a>.
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
