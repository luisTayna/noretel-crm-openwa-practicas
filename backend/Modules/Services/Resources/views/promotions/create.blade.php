@extends('services::layouts.master')

@section('title', 'Crear Promoción')
@section('page-title', 'Crear Nueva Promoción')

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.promotions.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver al listado
        </a>
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información de la Promoción</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('services.promotions.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre de la promoción <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="discount_type" class="form-label">Tipo de descuento <span class="text-danger">*</span></label>
                                <select class="form-control @error('discount_type') is-invalid @enderror" id="discount_type" name="discount_type" required>
                                    @foreach($discountTypes as $value => $label)
                                        <option value="{{ $value }}" {{ old('discount_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('discount_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="discount" class="form-label">Valor del descuento <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control @error('discount') is-invalid @enderror" id="discount" name="discount" value="{{ old('discount') }}" required>
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
                                <input type="date" class="form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Fecha de fin <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('end_date') is-invalid @enderror" id="end_date" name="end_date" value="{{ old('end_date', now()->addMonths(1)->format('Y-m-d')) }}" required>
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
                                                        {{ is_array(old('plan_ids')) && in_array($plan->id, old('plan_ids')) ? 'checked' : '' }}>
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
                                <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="active">
                                    Promoción activa
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary me-md-2">Limpiar</button>
                            <button type="submit" class="btn btn-primary">Guardar Promoción</button>
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

        // Función para actualizar el símbolo según el tipo de descuento
        function updateDiscountSymbol() {
            discountSymbol.textContent = discountTypeSelect.value === 'percentage' ? '%' : 'S/';
        }

        // Actualizar al cargar
        updateDiscountSymbol();

        // Actualizar al cambiar
        discountTypeSelect.addEventListener('change', updateDiscountSymbol);
    });
</script>
@endpush
