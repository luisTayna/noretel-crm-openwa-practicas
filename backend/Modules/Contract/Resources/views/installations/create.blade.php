@extends('operations::layouts.master')

@section('title', 'Agendar instalación')
@section('page-title', 'Operaciones · Agendar instalación')

@push('styles')
<style>
    .flow-shell {
        background: #f8fafc;
        border-radius: 24px;
        padding: 1.25rem;
    }

    .glass-card {
        background: rgba(255, 255, 255, .82);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, .65);
        border-radius: 22px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
        overflow: hidden;
    }

    .glass-card .card-header {
        background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%);
        border-bottom: 1px solid #e2e8f0;
        padding: 1.35rem 1.55rem;
        font-weight: 800;
        color: #0f172a;
    }

    .glass-card .form-control,
    .glass-card .form-select {
        border-radius: 12px;
        border-color: #dbe3ef;
        min-height: 44px;
    }

    .glass-card .form-control:focus,
    .glass-card .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 .22rem rgba(59, 130, 246, .12);
    }

    .flow-note {
        border-radius: 18px;
        border: 1px solid #dbeafe;
        background: linear-gradient(135deg, #eff6ff, #ffffff);
        padding: 1.1rem;
        color: #334155;
    }

    .btn-flow-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 700;
        box-shadow: 0 12px 25px rgba(37, 99, 235, .20);
    }

    .btn-flow-primary:hover { color: #fff; transform: translateY(-1px); }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold" style="color:#0f172a;">Agendar instalación</h1>
            <p class="text-muted mb-0">Asigna técnico, fecha y ruta para ejecutar la visita. El contrato se genera después, en la etapa día 30.</p>
        </div>
        <a href="{{ $contract ? route('operations.customers.flow', $contract->customer_id) : ($onboardingOrder ? route('operations.customers.flow', $onboardingOrder->customer_id) : route('contract.installations.index')) }}" class="btn btn-light border shadow-sm" style="border-radius:12px; font-weight:700;">
            <i class="fas fa-arrow-left me-1"></i> Volver
        </a>
    </div>

    <div class="flow-shell">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card glass-card border-0">
                    <div class="card-header">
                        <i class="fas fa-tools me-2 text-primary"></i> Programar visita técnica
                    </div>

                    <div class="card-body p-4">
                        @if($technicians->isEmpty())
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                No hay técnicos activos disponibles. Crea o asigna un empleado técnico antes de programar instalaciones.
                            </div>
                        @endif

                        <form action="{{ route('contract.installations.store') }}" method="POST">
                            @csrf

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Orden de instalación / Contrato <span class="text-danger">*</span></label>
                                    @if($onboardingOrder)
                                        <input type="hidden" name="onboarding_order_id" value="{{ $onboardingOrder->id }}">
                                        <div class="form-control bg-light">
                                            {{ $onboardingOrder->order_number }} · {{ optional($onboardingOrder->customer)->full_name }} · {{ optional($onboardingOrder->plan)->name }}
                                        </div>
                                    @elseif($contract)
                                        <input type="hidden" name="contract_id" value="{{ $contract->id }}">
                                        <div class="form-control bg-light">
                                            Contrato #{{ $contract->id }} · {{ optional($contract->customer)->full_name }} · {{ optional($contract->plan)->name }}
                                        </div>
                                    @else
                                        <select name="onboarding_order_id" id="onboarding_order_id" class="form-select @error('onboarding_order_id') is-invalid @enderror mb-2">
                                            <option value="">Seleccionar orden de instalación sin contrato</option>
                                            @foreach($onboardingOrders as $item)
                                                <option value="{{ $item->id }}" {{ old('onboarding_order_id') == $item->id ? 'selected' : '' }}>
                                                    {{ $item->order_number }} · {{ optional($item->customer)->full_name }} · {{ optional($item->plan)->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <select name="contract_id" id="contract_id" class="form-select @error('contract_id') is-invalid @enderror">
                                            <option value="">O seleccionar contrato legado</option>
                                            @foreach($contracts as $item)
                                                <option value="{{ $item->id }}" {{ old('contract_id') == $item->id ? 'selected' : '' }}>
                                                    #{{ $item->id }} · {{ optional($item->customer)->full_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @error('onboarding_order_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                    @error('contract_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="technician_id" class="form-label">Técnico <span class="text-danger">*</span></label>
                                    <select name="technician_id" id="technician_id" class="form-select @error('technician_id') is-invalid @enderror" required>
                                        <option value="">Seleccionar técnico</option>
                                        @foreach($technicians as $technician)
                                            <option value="{{ $technician->id }}" {{ old('technician_id') == $technician->id ? 'selected' : '' }}>
                                                {{ $technician->full_name }}{{ $technician->position ? ' · ' . $technician->position : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('technician_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">El técnico se define aquí, no en la orden inicial.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="scheduled_date" class="form-label">Fecha programada <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="scheduled_date" id="scheduled_date" class="form-control @error('scheduled_date') is-invalid @enderror" value="{{ old('scheduled_date', now()->addDay()->format('Y-m-d\TH:i')) }}" required>
                                    @error('scheduled_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="route_id" class="form-label">Ruta</label>
                                    <select name="route_id" id="route_id" class="form-select @error('route_id') is-invalid @enderror">
                                        <option value="">Sin ruta asignada</option>
                                        @foreach($routes as $route)
                                            <option value="{{ $route->id }}" {{ old('route_id') == $route->id ? 'selected' : '' }}>
                                                {{ $route->zone }} · {{ optional($route->date)->format('d/m/Y') }} · {{ $route->status }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('route_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>

                                <div class="col-12">
                                    <label for="notes" class="form-label">Notas internas</label>
                                    <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" placeholder="Ej. referencia de visita, horario preferido, observaciones para el técnico">{{ old('notes') }}</textarea>
                                    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="{{ $contract ? route('operations.customers.flow', $contract->customer_id) : ($onboardingOrder ? route('operations.customers.flow', $onboardingOrder->customer_id) : route('contract.installations.index')) }}" class="btn btn-light border" style="border-radius:12px; font-weight:700;">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-flow-primary px-4" {{ $technicians->isEmpty() ? 'disabled' : '' }}>
                                    <i class="fas fa-calendar-check me-1"></i> Agendar instalación
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="flow-note">
                    <h6 class="fw-bold mb-2"><i class="fas fa-route me-2 text-primary"></i> Flujo correcto</h6>
                    <p class="mb-2 small">Orden creada → Agendar instalación → Completar instalación → Contrato día 30.</p>
                    <p class="mb-0 small text-muted">La habilitación de equipo está preparada para una etapa futura, pero por ahora queda deshabilitada.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
