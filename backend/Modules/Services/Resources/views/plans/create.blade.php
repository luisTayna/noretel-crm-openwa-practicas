@extends('services::layouts.master')

@section('title', 'Crear Plan')
@section('page-title', 'Crear Nuevo Plan')

@section('actions')
    <div class="btn-group" role="group">
        @if(isset($selectedService))
            <a href="{{ route('services.plans.index', ['service_id' => $selectedService->id]) }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Volver a los planes
            </a>
        @else
            <a href="{{ route('services.plans.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Volver al listado
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información del Plan</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('services.plans.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="service_id" class="form-label">Servicio <span class="text-danger">*</span></label>
                            <select class="form-control @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required {{ isset($selectedService) ? 'disabled' : '' }}>
                                <option value="">Seleccione un servicio</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" {{ (old('service_id', isset($selectedService) ? $selectedService->id : '')) == $service->id ? 'selected' : '' }}>
                                        {{ $service->name }} ({{ $service->service_type_label }} - {{ $service->technology_label }})
                                    </option>
                                @endforeach
                            </select>
                            @if(isset($selectedService))
                                <input type="hidden" name="service_id" value="{{ $selectedService->id }}">
                            @endif
                            @error('service_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre del plan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Precio mensual (S/) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price') }}" required>
                                    @error('price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="download_speed" class="form-label">Velocidad de descarga (Mbps) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" min="0" class="form-control @error('download_speed') is-invalid @enderror" id="download_speed" name="download_speed" value="{{ old('download_speed') }}" required>
                                    @error('download_speed')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="upload_speed" class="form-label">Velocidad de subida (Mbps) <span class="text-danger">*</span></label>
                                    <input type="number" step="1" min="0" class="form-control @error('upload_speed') is-invalid @enderror" id="upload_speed" name="upload_speed" value="{{ old('upload_speed') }}" required>
                                    @error('upload_speed')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="commitment_period" class="form-label">Período de permanencia (meses)</label>
                            <input type="number" step="1" min="0" class="form-control @error('commitment_period') is-invalid @enderror" id="commitment_period" name="commitment_period" value="{{ old('commitment_period', 0) }}">
                            @error('commitment_period')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Características del plan</label>
                            <div class="mb-2">
                                <div class="row feature-inputs">
                                    @if(old('features') && is_array(old('features')))
                                        @foreach(old('features') as $index => $feature)
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
                                                        {{ is_array(old('promotion_ids')) && in_array($promotion->id, old('promotion_ids')) ? 'checked' : '' }}>
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
                                <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="active">
                                    Plan activo
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1" {{ old('is_featured') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_featured">
                                    <span class="badge bg-warning text-dark">⭐ Destacar en página principal</span>
                                </label>
                                <small class="d-block text-muted mt-1">
                                    Los planes destacados aparecerán en la sección de ofertas de la página principal
                                </small>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="featured-order-field" style="display: none;">
                            <label for="featured_order" class="form-label">Orden de aparición (destacados)</label>
                            <input type="number" class="form-control" id="featured_order" name="featured_order" value="{{ old('featured_order', 0) }}" min="0" max="100">
                            <small class="text-muted">Menor número = aparece primero (0-100)</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary me-md-2">Limpiar</button>
                            <button type="submit" class="btn btn-primary">Guardar Plan</button>
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
        
        // Mostrar/ocultar campo de orden cuando se marca como destacado
        const featuredCheckbox = document.getElementById('is_featured');
        const featuredOrderField = document.getElementById('featured-order-field');
        
        function toggleFeaturedOrder() {
            if (featuredCheckbox.checked) {
                featuredOrderField.style.display = 'block';
            } else {
                featuredOrderField.style.display = 'none';
            }
        }
        
        featuredCheckbox.addEventListener('change', toggleFeaturedOrder);
        toggleFeaturedOrder(); // Ejecutar al cargar
    });
</script>
@endpush
