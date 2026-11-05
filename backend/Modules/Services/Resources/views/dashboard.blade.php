@extends('services::layouts.master')

@section('title', 'Dashboard de Servicios')
@section('page-title', 'Resumen de Servicios')

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
    <!-- Overview Cards -->
    <div class="row g-4 mb-4">
        <!-- Servicios -->
        <div class="col-xl-3 col-md-6">
            <div class="card metric-card card-info-light border-0 py-2 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="metric-title text-uppercase mb-1">Servicios Activos</div>
                            <div class="metric-value fw-bold mb-0 text-dark">{{ $servicesCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-box" style="background: white; color: var(--secondary-color);">
                                <i class="fas fa-server"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planes -->
        <div class="col-xl-3 col-md-6">
            <div class="card metric-card card-mrr border-0 py-2 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="metric-title text-uppercase mb-1">Planes Ofrecidos</div>
                            <div class="metric-value fw-bold mb-0 text-dark">{{ $plansCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-box" style="background: white; color: var(--primary-color);">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Servicios Adicionales -->
        <div class="col-xl-3 col-md-6">
            <div class="card metric-card card-warning-light border-0 py-2 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="metric-title text-uppercase mb-1">Servicios Adicionales</div>
                            <div class="metric-value fw-bold mb-0 text-dark">{{ $additionalServicesCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-box" style="background: white; color: #f59e0b;">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Promociones -->
        <div class="col-xl-3 col-md-6">
            <div class="card metric-card card-customers border-0 py-2 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col mr-2">
                            <div class="metric-title text-uppercase mb-1">Promociones Activas</div>
                            <div class="metric-value fw-bold mb-0 text-dark">{{ $activePromotionsCount ?? 0 }}</div>
                        </div>
                        <div class="col-auto">
                            <div class="icon-box" style="background: white; color: var(--accent-color);">
                                <i class="fas fa-tags"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4">
        <!-- Recent Services -->
        <div class="col-lg-6">
            <div class="card glass-card border-0 mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between border-0 bg-transparent">
                    <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);"><i class="fas fa-list text-muted me-2"></i>Servicios Recientes</h6>
                    <a href="{{ route('services.services.index') }}" class="btn btn-sm btn-glass-action rounded-pill px-3">
                        Ver todos
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(isset($recentServices) && count($recentServices) > 0)
                        <div class="table-responsive">
                            <table class="table table-glass">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Nombre</th>
                                        <th>Tipo</th>
                                        <th>Tecnología</th>
                                        <th class="text-center pe-4">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentServices as $service)
                                        <tr>
                                            <td class="ps-4">
                                                <a href="{{ route('services.services.show', $service->id) }}" class="fw-bold text-decoration-none" style="color: var(--text-color);">
                                                    {{ $service->name }}
                                                </a>
                                            </td>
                                            <td class="text-muted">{{ $service->service_type_label }}</td>
                                            <td class="text-muted">{{ $service->technology_label }}</td>
                                            <td class="text-center pe-4">
                                                @if($service->active)
                                                    <span class="badge badge-pill badge-status-paid bg-transparent">Activo</span>
                                                @else
                                                    <span class="badge badge-pill badge-status-overdue bg-transparent">Inactivo</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-center text-muted py-5 mb-0">No hay servicios registrados.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Plans -->
        <div class="col-lg-6">
            <div class="card glass-card border-0 mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between border-0 bg-transparent">
                    <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);"><i class="fas fa-layer-group text-muted me-2"></i>Planes Recientes</h6>
                    <a href="{{ route('services.plans.index') }}" class="btn btn-sm btn-glass-action rounded-pill px-3">
                        Ver todos
                    </a>
                </div>
                <div class="card-body p-0">
                    @if(isset($recentPlans) && count($recentPlans) > 0)
                        <div class="table-responsive">
                            <table class="table table-glass">
                                <thead>
                                    <tr>
                                        <th class="ps-4">Nombre</th>
                                        <th>Servicio</th>
                                        <th>Precio</th>
                                        <th class="pe-4">Velocidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentPlans as $plan)
                                        <tr>
                                            <td class="ps-4">
                                                <a href="{{ route('services.plans.show', $plan->id) }}" class="fw-bold text-decoration-none" style="color: var(--text-color);">
                                                    {{ $plan->name }}
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge badge-pill badge-plan bg-transparent">{{ $plan->service->name }}</span>
                                            </td>
                                            <td class="text-dark fw-bold">S/ {{ number_format($plan->price, 2) }}</td>
                                            <td class="pe-4 text-muted small"><i class="fas fa-tachometer-alt me-1 text-primary"></i>{{ $plan->download_speed }}/{{ $plan->upload_speed }} Mbps</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-center text-muted py-5 mb-0">No hay planes registrados.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Active Promotions -->
    <div class="card glass-card border-0 mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between border-0 bg-transparent">
            <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);"><i class="fas fa-tags text-muted me-2"></i>Promociones Activas</h6>
            <a href="{{ route('services.promotions.index') }}" class="btn btn-sm btn-glass-action rounded-pill px-3">
                Ver todas
            </a>
        </div>
        <div class="card-body px-4 pb-4">
            @if(isset($activePromotions) && count($activePromotions) > 0)
                <div class="row g-4">
                    @foreach($activePromotions as $promotion)
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm" style="border-radius: 16px; overflow: hidden; background: rgba(255, 255, 255, 0.55); border: 1px solid rgba(255, 255, 255, 0.75);">
                                <div class="card-header bg-transparent py-3 border-0" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.08), rgba(239, 68, 68, 0.02)) !important;">
                                    <h6 class="m-0 font-weight-bold text-danger"><i class="fas fa-fire me-2"></i>{{ $promotion->name }}</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-3 text-muted small">{{ $promotion->description }}</p>
                                    <div class="mb-3">
                                        @if($promotion->discount_type == 'percentage')
                                            <span class="badge badge-pill badge-status-overdue bg-transparent p-2 px-3 fw-bold fs-6"><i class="fas fa-percent me-1"></i>{{ $promotion->discount }}% OFF</span>
                                        @else
                                            <span class="badge badge-pill badge-status-overdue bg-transparent p-2 px-3 fw-bold fs-6"><i class="fas fa-tag me-1"></i>S/ {{ number_format($promotion->discount, 2) }} DCTO</span>
                                        @endif
                                    </div>
                                    <div class="text-muted small">
                                        <i class="fas fa-calendar-alt me-1 text-primary"></i>Válido: {{ $promotion->start_date->format('d/m/Y') }} - {{ $promotion->end_date->format('d/m/Y') }}
                                    </div>
                                </div>
                                <div class="card-footer bg-transparent border-0 py-3">
                                    <small class="text-muted fw-semibold"><i class="fas fa-network-wired me-1"></i>Planes aplicables: {{ $promotion->plans->count() }}</small>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-center text-muted py-5 mb-0">No hay promociones activas.</p>
            @endif
        </div>
    </div>
@endsection

