@extends('services::layouts.master')

@section('title', 'Detalles del Plan')
@section('page-title', 'Detalles del Plan: ' . $plan->name)

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.plans.index', ['service_id' => $plan->service_id]) }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver a los planes
        </a>

        @if(auth()->user()->hasPermission('edit', 'plans'))
            <a href="{{ route('services.plans.edit', $plan->id) }}" class="btn btn-sm btn-primary">
                <i class="fas fa-edit mr-1"></i> Editar
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-8">
            <!-- Detalles del plan -->
            <div class="card shadow mb-4">
                <div class="service-detail-header">
                    <h5 class="font-weight-bold mb-0">
                        <i class="fas fa-layer-group mr-2"></i> {{ $plan->name }}
                        @if($plan->active)
                            <span class="badge badge-light float-end">Activo</span>
                        @else
                            <span class="badge badge-danger float-end">Inactivo</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="font-weight-bold">Servicio:</h6>
                        <p>
                            <a href="{{ route('services.services.show', $plan->service_id) }}">
                                {{ $service->name }}
                            </a>
                            ({{ $service->service_type_label }} - {{ $service->technology_label }})
                        </p>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <h6 class="font-weight-bold">Precio mensual:</h6>
                            @if($activePromotions->count() > 0)
                                <p class="mb-0">
                                    <span class="text-decoration-line-through text-muted">S/ {{ number_format($plan->price, 2) }}</span>
                                </p>
                                <p class="text-success font-weight-bold h5">
                                    S/ {{ number_format($plan->getDiscountedPrice(), 2) }}
                                </p>
                            @else
                                <p class="h5">S/ {{ number_format($plan->price, 2) }}</p>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <h6 class="font-weight-bold">Velocidad de descarga:</h6>
                            <p class="h5">{{ $plan->download_speed }} Mbps</p>
                        </div>

                        <div class="col-md-4">
                            <h6 class="font-weight-bold">Velocidad de subida:</h6>
                            <p class="h5">{{ $plan->upload_speed }} Mbps</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="font-weight-bold">Período de permanencia:</h6>
                        <p>
                            @if($plan->commitment_period > 0)
                                {{ $plan->commitment_period }} {{ $plan->commitment_period == 1 ? 'mes' : 'meses' }}
                            @else
                                Sin período de permanencia
                            @endif
                        </p>
                    </div>

                    <div class="mb-4">
                        <h6 class="font-weight-bold">Características:</h6>
                        @if(!empty($plan->features) && count($plan->features) > 0)
                            <ul class="feature-list">
                                @foreach($plan->features as $feature)
                                    <li><i class="fas fa-check-circle"></i> {{ $feature }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-muted"><em>No hay características registradas</em></p>
                        @endif
                    </div>

                    <div class="row mb-0">
                        <div class="col-md-6">
                            <p class="mb-1 text-sm text-muted">Creado:</p>
                            <p class="mb-0">{{ $plan->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-sm text-muted">Actualizado:</p>
                            <p class="mb-0">{{ $plan->updated_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Promociones activas -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-warning">
                        <i class="fas fa-tags mr-1"></i> Promociones Activas
                    </h6>
                </div>
                <div class="card-body">
                    @if($activePromotions->count() > 0)
                        @foreach($activePromotions as $promotion)
                            <div class="mb-3 pb-3 border-bottom">
                                <h6 class="font-weight-bold">{{ $promotion->name }}</h6>
                                <p class="mb-2">{{ $promotion->description }}</p>
                                <p class="mb-2">
                                    <span class="badge badge-warning">
                                        @if($promotion->discount_type === 'percentage')
                                            {{ $promotion->discount }}% de descuento
                                        @else
                                            S/ {{ number_format($promotion->discount, 2) }} de descuento
                                        @endif
                                    </span>
                                </p>
                                <p class="small text-muted mb-0">
                                    Válido: {{ $promotion->start_date->format('d/m/Y') }} - {{ $promotion->end_date->format('d/m/Y') }}
                                </p>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted">No hay promociones activas para este plan.</p>
                    @endif
                </div>
            </div>

            <!-- Promociones programadas -->
            @if($inactivePromotions->count() > 0)
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-muted">
                            <i class="fas fa-calendar-alt mr-1"></i> Promociones Programadas
                        </h6>
                    </div>
                    <div class="card-body">
                        @foreach($inactivePromotions as $promotion)
                            <div class="mb-3 pb-3 border-bottom">
                                <h6>{{ $promotion->name }}</h6>
                                <p class="small mb-2">
                                    @if($promotion->discount_type === 'percentage')
                                        {{ $promotion->discount }}% de descuento
                                    @else
                                        S/ {{ number_format($promotion->discount, 2) }} de descuento
                                    @endif
                                </p>
                                <p class="small text-muted mb-0">
                                    Válido: {{ $promotion->start_date->format('d/m/Y') }} - {{ $promotion->end_date->format('d/m/Y') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Acciones -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-cog mr-1"></i> Acciones
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
            @if(auth()->user()->hasPermission('edit', 'plans'))
                            @if($plan->active)
                                <form action="{{ route('services.plans.deactivate', $plan->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-danger btn-block mb-2" onclick="return confirm('¿Está seguro de desactivar este plan?')">
                                        <i class="fas fa-toggle-off mr-1"></i> Desactivar Plan
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('services.plans.activate', $plan->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-block mb-2" onclick="return confirm('¿Está seguro de activar este plan?')">
                                        <i class="fas fa-toggle-on mr-1"></i> Activar Plan
                                    </button>
                                </form>
                            @endif
                        @endif

            @if(auth()->user()->hasPermission('delete', 'plans'))
                            <form action="{{ route('services.plans.destroy', $plan->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('¿Está seguro de eliminar este plan? Esta acción no se puede deshacer.')">
                                    <i class="fas fa-trash mr-1"></i> Eliminar Plan
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

