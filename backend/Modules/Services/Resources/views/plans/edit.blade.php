@extends('services::layouts.master')

@section('title', 'Editar Plan')
@section('page-title', 'Editar Plan: ' . $plan->name)

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.plans.index', ['service_id' => $plan->service_id]) }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver a los planes
        </a>
        <a href="{{ route('services.plans.show', $plan->id) }}" class="btn btn-sm btn-info">
            <i class="fas fa-eye mr-1"></i> Ver detalle
        </a>
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Información del Plan</h6>
                    <div>
                        @if($plan->active)
                            <span class="badge badge-success">Activo</span>
                        @else
                            <span class="badge badge-danger">Inactivo</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <form action="{{ route('services.plans.update', $plan->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="service_id" class="form-label">Servicio <span class="text-danger">*</span></label>
                            <select class="form-control @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
                                <option value="">Seleccione un servicio</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" {{ old('service_id', $plan->service_id) == $service->id ? 'selected' : '' }}>
                                        {{ $service->name }} ({{ $service->service_type_label }} - {{ $service->technology_label }})
                                    </option>
                                @endforeach
                            </select>
                            @error('service_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre del plan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $plan->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Precio mensual (S/) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price', $plan->price) }}" required>
                                    @error('price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="download_speed" class="form-label">Velocidad de descarga (Mbps) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" min="0" class="form-control @error('download_speed') is-invalid @enderror" id="download_speed" name="download_speed" value="{{ old('download_speed', $plan->download_speed) }}" required>
                                    @error('download_speed')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="upload_speed" class="form-label">Velocidad de subida (Mbps) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" min="0" class="form-control @error('upload_speed') is-invalid @enderror" id="upload_speed" name="upload_speed" value="{{ old('upload_speed', $plan->upload_speed) }}" required>
                                    @error('upload_speed')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="commitment_period" class="form-label">Período de permanencia (meses)</label>
                            <input type="number" step="1" min="0" class="form-control @error('commitment_period') is-invalid @enderror" id="commitment_period" name="commitment_period" value="{{ old('commitment_period', $plan->commitment_period) }}">
                            @error('commitment_period')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Características del plan</label>
                            <div class="mb-2">
                                <div class="row feature-inputs">
                                    @php
                                        $features = old('features', $plan->features) ?: [];
                                    @endphp

                                    @if(count($features) > 0)
                                        @foreach($features as $feature)
                                            <div class="col-md-6 mb-2 feature-item">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="features[]" value="{{ $feature }}" placeholder="Ej: IP Fija">
                                                    <button type="button" class="btn btn-danger remove-feature"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="col-md-6 mb-2 feature-item">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="features[]" placeholder="Ej: IP Fija">
                                                <button type="button" class="btn btn-danger remove-feature"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary add-feature">
                                    <i class="fas fa-plus-circle"></i> Agregar característica
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Promociones aplicables</label>
                            <div class="border rounded p-3">
                                @if(count($promotions) > 0)
                                    <div class="row">
                                        @foreach($promotions as $promotion)
                                            <div class="col-md-6 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="promotion_ids[]" value="{{ $promotion->id }}" id="promotion_{{ $promotion->id }}"
                                                        {{ (is_array(old('promotion_ids')) && in_array($promotion->id, old('promotion_ids'))) || (old('promotion_ids') === null && in_array($promotion->id, $selectedPromotions)) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="promotion_{{ $promotion->id }}">
                                                        {{ $promotion->name }}
                                                        @if($promotion->discount_type === 'percentage')
                                                            ({{ $promotion->discount }}%)
                                                        @else
                                                            (S/ {{ number_format($promotion->discount, 2) }})
                                                        @endif
                                                        <small class="d-block text-muted">
                                                            Válido: {{ $promotion->start_date->format('d/m/Y') }} - {{ $promotion->end_date->format('d/m/Y') }}
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted mb-0">No hay promociones disponibles.</p>
                                @endif
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                                    {{ old('active', $plan->active) ? 'checked' : '' }}>
                                <label class="form-check-label" for="active">
                                    Plan activo
                                </label>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1"
                                    {{ old('is_featured', $plan->is_featured) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_featured">
                                    <span class="badge bg-warning text-dark">⭐ Destacar en página principal</span>
                                </label>
                                <small class="d-block text-muted mt-1">
                                    Los planes destacados aparecerán en la sección de ofertas
                                </small>
                            </div>
                        </div>
                        <div class="mb-3" id="featured-order-field" style="display: none;">
                            <label for="featured_order" class="form-label">
                                Orden de aparición (destacados)
                            </label>
                            <input type="number" class="form-control" id="featured_order" name="featured_order"
                                value="{{ old('featured_order', $plan->featured_order) }}"
                                min="0" max="100">
                            <small class="text-muted">
                                Menor número = aparece primero
                            </small>
                        </div>


                        

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('services.plans.show', $plan->id) }}" class="btn btn-secondary me-md-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Actualizar Plan</button>
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
        // Agregar característica
        document.querySelector('.add-feature').addEventListener('click', function() {
            const featureInputs = document.querySelector('.feature-inputs');
            const featureItem = document.createElement('div');
            featureItem.className = 'col-md-6 mb-2 feature-item';
            featureItem.innerHTML = `
                <div class="input-group">
                    <input type="text" class="form-control" name="features[]" placeholder="Ej: IP Fija">
                    <button type="button" class="btn btn-danger remove-feature"><i class="fas fa-times"></i></button>
                </div>
            `;
            featureInputs.appendChild(featureItem);

            // Agregar evento eliminar al nuevo botón
            featureItem.querySelector('.remove-feature').addEventListener('click', function() {
                featureItem.remove();
            });
        });

        // Eliminar característica (para los elementos existentes)
        document.querySelectorAll('.remove-feature').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.feature-item').remove();
            });
        });
        const featuredCheckbox = document.getElementById('is_featured');
        const featuredOrderField = document.getElementById('featured-order-field');

        if (!featuredCheckbox) return;

        function toggleFeaturedOrder() {
            featuredOrderField.style.display = featuredCheckbox.checked ? 'block' : 'none';
        }

        featuredCheckbox.addEventListener('change', toggleFeaturedOrder);
        toggleFeaturedOrder(); // importante al cargar
    });
</script>
@endpush

