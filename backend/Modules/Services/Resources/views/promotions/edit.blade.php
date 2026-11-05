@extends('services::layouts.master')

@section('title', 'Editar Promoción')
@section('page-title', 'Editar Promoción: ' . $promotion->name)

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.promotions.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver al listado
        </a>
        <a href="{{ route('services.promotions.show', $promotion->id) }}" class="btn btn-sm btn-info">
            <i class="fas fa-eye mr-1"></i> Ver detalle
        </a>
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Información de la Promoción</h6>
                    @if($promotion->active)
                        <span class="badge badge-status-paid bg-transparent">Activa</span>
                    @else
                        <span class="badge badge-status-overdue bg-transparent">Inactiva</span>
                    @endif
                </div>
                <div class="card-body">
                    <form action="{{ route('services.promotions.update', $promotion->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre de la promoción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $promotion->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $promotion->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="discount_type" class="form-label">Tipo de descuento <span class="text-danger">*</span></label>
                                <select class="form-control @error('discount_type') is-invalid @enderror" id="discount_type" name="discount_type" required>
                                    @foreach($discountTypes as $value => $label)
                                        <option value="{{ $value }}" {{ old('discount_type', $promotion->discount_type) == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('discount_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="discount" class="form-label">Valor del descuento <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control @error('discount') is-invalid @enderror" id="discount" name="discount" value="{{ old('discount', $promotion->discount) }}" required>
                                    <span class="input-group-text discount-symbol">%</span>
                                    @error('discount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Fecha de inicio <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date', optional($promotion->start_date)->format('Y-m-d')) }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Fecha de fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" value="{{ old('end_date', optional($promotion->end_date)->format('Y-m-d')) }}" required>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Planes aplicables</label>
                            <div class="border rounded p-3">
                                @if(count($plans) > 0)
                                    <div class="row">
                                        @foreach($plans as $plan)
                                            <div class="col-md-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="plan_ids[]" value="{{ $plan->id }}" id="plan_{{ $plan->id }}"
                                                        {{ (is_array(old('plan_ids')) && in_array($plan->id, old('plan_ids'))) || (old('plan_ids') === null && in_array($plan->id, $selectedPlans)) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="plan_{{ $plan->id }}">
                                                        {{ $plan->name }} ({{ $plan->service->name }})
                                                        <small class="d-block text-muted">
                                                            {{ $plan->download_speed }}/{{ $plan->upload_speed }} Mbps - S/ {{ number_format($plan->price, 2) }}
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No hay planes disponibles para aplicar promociones.</p>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', $promotion->active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="active">
                                    Promoción activa
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('services.promotions.show', $promotion->id) }}" class="btn btn-secondary me-md-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Actualizar Promoción</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const discountTypeSelect = document.getElementById('discount_type');
        const discountSymbol = document.querySelector('.discount-symbol');

        function updateDiscountSymbol() {
            discountSymbol.textContent = discountTypeSelect.value === 'percentage' ? '%' : 'S/';
        }

        updateDiscountSymbol();
        discountTypeSelect.addEventListener('change', updateDiscountSymbol);
    });
</script>
@endpush
