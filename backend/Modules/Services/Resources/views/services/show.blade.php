@extends('services::layouts.master')

@section('title', 'Detalles del Servicio')
@section('page-title', 'Detalles del Servicio: ' . $service->name)

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.services.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver al listado
        </a>

        @if(auth()->user()->hasPermission('edit', 'services'))
            <a href="{{ route('services.services.edit', $service->id) }}" class="btn btn-sm btn-primary">
                <i class="fas fa-edit mr-1"></i> Editar
            </a>
        @endif

        @if(auth()->user()->hasPermission('create', 'services'))
            <a href="{{ route('services.plans.create', ['service_id' => $service->id]) }}" class="btn btn-sm btn-success">
                <i class="fas fa-plus-circle mr-1"></i> Nuevo Plan
            </a>
            <a href="{{ route('services.additional-services.create', ['service_id' => $service->id]) }}" class="btn btn-sm btn-info">
                <i class="fas fa-plus-circle mr-1"></i> Nuevo Servicio Adicional
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-4">
            <!-- Información básica del servicio -->
            <div class="card shadow mb-4">
                <div class="service-detail-header">
                    <h5 class="font-weight-bold mb-0">
                        <i class="fas fa-server mr-2"></i> {{ $service->name }}
                        @if($service->active)
                            <span class="badge badge-light float-end">Activo</span>
                        @else
                            <span class="badge badge-danger float-end">Inactivo</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    @if($service->description)
                        <p class="mb-4">{{ $service->description }}</p>
                    @else
                        <p class="text-muted mb-4"><em>Sin descripción</em></p>
                    @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1 text-sm text-muted">Tipo de servicio:</p>
                            <p class="mb-2 font-weight-bold">{{ $service->service_type_label }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-sm text-muted">Tecnología:</p>
                            <p class="mb-2 font-weight-bold">{{ $service->technology_label }}</p>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <div>
                            <p class="mb-1 text-sm text-muted">Creado:</p>
                            <p class="mb-0">{{ $service->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-sm text-muted">Actualizado:</p>
                            <p class="mb-0">{{ $service->updated_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Planes asociados al servicio -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-layer-group mr-1"></i> Planes asociados
                    </h6>
                    <a href="{{ route('services.plans.index', ['service_id' => $service->id]) }}" class="btn btn-sm btn-primary">
                        Ver todos
                    </a>
                </div>
                <div class="card-body">
                    @if(count($plans) > 0)
                        <div class="table-responsive">
                            <table class="table table-borderless">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Velocidad</th>
                                        <th>Precio</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($plans as $plan)
                                        <tr class="plan-item {{ !$plan->active ? 'inactive' : '' }}">
                                            <td>
                                                <a href="{{ route('services.plans.show', $plan->id) }}">
                                                    {{ $plan->name }}
                                                </a>
                                            </td>
                                            <td>{{ $plan->download_speed }}/{{ $plan->upload_speed }} Mbps</td>
                                            <td>S/ {{ number_format($plan->getDiscountedPrice(), 2) }}</td>
                                            <td>
                                                @if($plan->active)
                                                    <span class="badge badge-success">Activo</span>
                                                @else
                                                    <span class="badge badge-danger">Inactivo</span>
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('services.plans.show', $plan->id) }}" class="btn btn-sm btn-link">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info text-center">
                            No hay planes asociados a este servicio.
                            @if(auth()->user()->hasPermission('create', 'services'))
                                <a href="{{ route('services.plans.create', ['service_id' => $service->id]) }}" class="alert-link">Crear un plan</a>.
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Servicios adicionales -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-plus-circle mr-1"></i> Servicios adicionales
                    </h6>
                    <a href="{{ route('services.additional-services.index', ['service_id' => $service->id]) }}" class="btn btn-sm btn-primary">
                        Ver todos
                    </a>
                </div>
                <div class="card-body">
                    @if(count($additionalServices) > 0)
                        <div class="row">
                            @foreach($additionalServices as $additionalService)
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <a href="{{ route('services.additional-services.show', $additionalService->id) }}">
                                                    {{ $additionalService->name }}
                                                </a>
                                            </h6>
                                            <p class="card-text">
                                                @if($additionalService->description)
                                                    {{ Str::limit($additionalService->description, 100) }}
                                                @else
                                                    <span class="text-muted"><em>Sin descripción</em></span>
                                                @endif
                                            </p>
                                            <p class="card-text">
                                                <strong>Precio:</strong> S/ {{ number_format($additionalService->price, 2) }}
                                            </p>
                                            @if($additionalService->configurable)
                                                <span class="badge badge-info">Configurable</span>
                                            @endif
                                        </div>
                                        <div class="card-footer bg-light">
                                            <a href="{{ route('services.additional-services.show', $additionalService->id) }}" class="btn btn-sm btn-link p-0">
                                                Ver detalle <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info text-center">
                            No hay servicios adicionales para este servicio.
                            @if(auth()->user()->hasPermission('create', 'services'))
                                <a href="{{ route('services.additional-services.create', ['service_id' => $service->id]) }}" class="alert-link">Crear un servicio adicional</a>.
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

