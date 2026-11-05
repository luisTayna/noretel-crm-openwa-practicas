@extends('operations::layouts.master')

@section('title', 'Nueva orden de instalación')
@section('page-title', 'Operaciones · Nueva orden de instalación')

@section('content')
@php
    $defaultContractDate = \Modules\Operations\Entities\OnboardingOrder::nextContractDeliveryDate();
    $selectedCustomerId = old('customer_id', optional($customer)->id);
    $selectedPlanId = old('plan_id');
@endphp

<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.78);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.55);
        border-radius: 20px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
    }

    .glass-card .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(226, 232, 240, .8);
        padding: 1.25rem 1.5rem;
        font-weight: 800;
        color: #0f172a;
    }

    .flow-note {
        border-radius: 16px;
        border: 1px solid #dbeafe;
        background: linear-gradient(135deg, #eff6ff, #f8fafc);
        padding: 1rem 1.1rem;
        color: #334155;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold" style="color:#0f172a;">Nueva orden de instalación</h1>
            <p class="text-muted mb-0">
                Primero se registra el plan y la fecha esperada. El técnico se asigna después, al agendar la instalación.
            </p>
        </div>
        <a href="{{ $customer ? route('operations.customers.flow', $customer->id) : route('operations.onboarding') }}" class="btn btn-light border shadow-sm" style="border-radius:12px;">
            <i class="fas fa-arrow-left me-1"></i> Regresar
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card glass-card border-0">
                <div class="card-header">
                    <i class="fas fa-clipboard-list me-2 text-primary"></i> Datos de la solicitud
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('operations.onboarding-orders.store') }}" id="onboarding-order-form">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="customer_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                @if($customer)
                                    <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                                    <div class="form-control bg-light">
                                        {{ $customer->full_name }} · {{ $customer->identity_document ?: 'Sin documento' }}
                                    </div>
                                @else
                                    <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                                        <option value="">Seleccionar cliente</option>
                                        @foreach($customers as $item)
                                            <option value="{{ $item->id }}" {{ (string) $selectedCustomerId === (string) $item->id ? 'selected' : '' }}>
                                                {{ $item->full_name }} · {{ $item->identity_document ?: 'Sin documento' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('customer_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label for="plan_id" class="form-label">Plan <span class="text-danger">*</span></label>
                                <select name="plan_id" id="plan_id" class="form-select @error('plan_id') is-invalid @enderror" required>
                                    <option value="">Seleccionar plan</option>
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" data-price="{{ number_format((float) $plan->price, 2, '.', '') }}" {{ (string) $selectedPlanId === (string) $plan->id ? 'selected' : '' }}>
                                            {{ $plan->name }} · S/ {{ number_format((float) $plan->price, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('plan_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="expected_contract_date" class="form-label">Fecha esperada de contrato</label>
                                <input
                                    type="date"
                                    name="expected_contract_date"
                                    id="expected_contract_date"
                                    class="form-control @error('expected_contract_date') is-invalid @enderror"
                                    value="{{ old('expected_contract_date', $defaultContractDate) }}"
                                >
                                @error('expected_contract_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Por defecto se agenda al próximo día 30 operativo.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="final_price" class="form-label">Precio final mensual</label>
                                <div class="input-group">
                                    <span class="input-group-text">S/</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="final_price"
                                        id="final_price"
                                        class="form-control @error('final_price') is-invalid @enderror"
                                        value="{{ old('final_price') }}"
                                        placeholder="Se toma del plan si lo dejas vacío"
                                    >
                                    @error('final_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notas internas</label>
                                <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" placeholder="Ej. referencia de dirección, condición comercial, observaciones para técnico">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ $customer ? route('operations.customers.flow', $customer->id) : route('operations.onboarding') }}" class="btn btn-light border">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Registrar orden
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="flow-note">
                <h6 class="fw-bold mb-2"><i class="fas fa-route me-2 text-primary"></i> Flujo correcto</h6>
                <p class="mb-2 small">
                    Cliente → Orden de instalación → Instalación → Contrato día 30 → Recibo mensual → Pago.
                </p>
                <p class="mb-0 small text-muted">
                    ESTO ES IMPORTANTE: esta solicitud NO es un contrato borrador. Es el registro operativo para avanzar instalación antes de emitir el contrato real.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const planSelect = document.getElementById('plan_id');
    const finalPriceInput = document.getElementById('final_price');

    function syncPlanPrice() {
        if (!planSelect || !finalPriceInput || finalPriceInput.value) {
            return;
        }

        const option = planSelect.options[planSelect.selectedIndex];
        if (option && option.dataset.price) {
            finalPriceInput.placeholder = option.dataset.price;
        }
    }

    planSelect?.addEventListener('change', syncPlanPrice);
    syncPlanPrice();
});
</script>
@endpush
