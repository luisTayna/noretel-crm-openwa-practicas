@extends('services::layouts.master')

@section('title', 'Detalle de Promoción')
@section('page-title', 'Promoción: ' . $promotion->name)

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.promotions.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver al listado
        </a>
        @if(auth()->user()->hasPermission('edit', 'promotions'))
            <a href="{{ route('services.promotions.edit', $promotion->id) }}" class="btn btn-sm btn-warning">
                <i class="fas fa-edit mr-1"></i> Editar
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card glass-card border-0 mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center border-0 bg-transparent">
                    <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);">
                        <i class="fas fa-tag text-muted me-2"></i>Información de la Promoción
                    </h6>
                    @if($promotion->active)
                        <span class="badge badge-pill badge-status-paid bg-transparent">Activa</span>
                    @else
                        <span class="badge badge-pill badge-status-overdue bg-transparent">Inactiva</span>
                    @endif
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8">{{ $promotion->name }}</dd>

                        <dt class="col-sm-4">Descripción</dt>
                        <dd class="col-sm-8">{{ $promotion->description ?: 'Sin descripción' }}</dd>

                        <dt class="col-sm-4">Descuento</dt>
                        <dd class="col-sm-8">
                            @if($promotion->discount_type === 'percentage')
                                {{ $promotion->discount }}%
                            @else
                                S/ {{ number_format($promotion->discount, 2) }}
                            @endif
                        </dd>

                        <dt class="col-sm-4">Vigencia</dt>
                        <dd class="col-sm-8">
                            {{ $promotion->start_date->format('d/m/Y') }} - {{ $promotion->end_date->format('d/m/Y') }}
                            @if($promotion->start_date > now())
                                <span class="badge badge-pill badge-status-pending bg-transparent ms-2">Próxima</span>
                            @elseif($promotion->end_date < now())
                                <span class="badge badge-pill bg-transparent text-muted border border-secondary ms-2">Finalizada</span>
                            @else
                                <span class="badge badge-pill badge-status-paid bg-transparent ms-2">En curso</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card glass-card border-0 mb-4">
                <div class="card-header py-3 border-0 bg-transparent">
                    <h6 class="m-0 font-weight-bold" style="color: var(--dark-bg);">
                        <i class="fas fa-layer-group text-muted me-2"></i>Planes Aplicables
                    </h6>
                </div>
                <div class="card-body">
                    @if(count($plans) > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($plans as $plan)
                                <li class="list-group-item bg-transparent px-0">
                                    <a href="{{ route('services.plans.show', $plan->id) }}" class="fw-semibold text-decoration-none">
                                        {{ $plan->name }}
                                    </a>
                                    <small class="d-block text-muted">
                                        {{ $plan->download_speed }}/{{ $plan->upload_speed }} Mbps - S/ {{ number_format($plan->price, 2) }}
                                    </small>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted mb-0">Esta promoción no está asociada a planes.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
