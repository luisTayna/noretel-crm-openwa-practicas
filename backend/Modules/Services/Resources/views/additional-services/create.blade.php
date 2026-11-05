@extends('services::layouts.master')

@section('title', 'Crear Servicio Adicional')
@section('page-title', 'Crear Nuevo Servicio Adicional')

@section('actions')
    <div class="btn-group" role="group">
        @if(isset($selectedService))
            <a href="{{ route('services.additional-services.index', ['service_id' => $selectedService->id]) }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Volver a servicios adicionales
            </a>
        @else
            <a href="{{ route('services.additional-services.index') }}" class="btn btn-sm btn-secondary">
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
                    <h6 class="m-0 font-weight-bold text-primary">Información del Servicio Adicional</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('services.additional-services.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="service_id" class="form-label">Servicio Principal <span class="text-danger">*</span></label>
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
                            <label for="name" class="form-label">Nombre del servicio adicional <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="price" class="form-label">Precio (S/) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control @error('price') is-invalid @enderror" id="price" name="price" value="{{ old('price') }}" required>
                            @error('price')
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

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="configurable" name="configurable" value="1" {{ old('configurable') ? 'checked' : '' }}>
                                <label class="form-check-label" for="configurable">
                                    Servicio configurable
                                </label>
                                <small class="form-text text-muted">Marque esta opción si el servicio puede ser configurado con opciones adicionales.</small>
                            </div>
                        </div>

                        <div class="mb-3 configuration-options" style="{{ old('configurable') ? '' : 'display: none;' }}">
                            <label class="form-label">Opciones de configuración</label>
                            <div class="mb-2">
                                <div class="row option-inputs">
                                    @if(old('configuration_options') && is_array(old('configuration_options')))
                                        @foreach(old('configuration_options') as $index => $option)
                                            <div class="col-12 mb-2 option-item">
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="configuration_options[]" value="{{ $option }}" placeholder="Ej: Cantidad de IPs">
                                                    <button type="button" class="btn btn-danger remove-option"><i class="fas fa-times"></i></button>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        <div class="col-12 mb-2 option-item">
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="configuration_options[]" placeholder="Ej: Cantidad de IPs">
                                                <button type="button" class="btn btn-danger remove-option"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary add-option">
                                    <i class="fas fa-plus-circle"></i> Agregar opción
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary me-md-2">Limpiar</button>
                            <button type="submit" class="btn btn-primary">Guardar Servicio Adicional</button>
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
        const configurableCheckbox = document.getElementById('configurable');
        const configurationOptions = document.querySelector('.configuration-options');

        // Mostrar/ocultar opciones de configuración
        configurableCheckbox.addEventListener('change', function() {
            configurationOptions.style.display = this.checked ? 'block' : 'none';
        });

        // Agregar opción
        document.querySelector('.add-option').addEventListener('click', function() {
            const optionInputs = document.querySelector('.option-inputs');
            const optionItem = document.createElement('div');
            optionItem.className = 'col-12 mb-2 option-item';
            optionItem.innerHTML = `
                <div class="input-group">
                    <input type="text" class="form-control" name="configuration_options[]" placeholder="Ej: Cantidad de IPs">
                    <button type="button" class="btn btn-danger remove-option"><i class="fas fa-times"></i></button>
                </div>
            `;
            optionInputs.appendChild(optionItem);

            // Agregar evento eliminar al nuevo botón
            optionItem.querySelector('.remove-option').addEventListener('click', function() {
                optionItem.remove();
            });
        });

        // Eliminar opción (para los elementos existentes)
        document.querySelectorAll('.remove-option').forEach(button => {
            button.addEventListener('click', function() {
                this.closest('.option-item').remove();
            });
        });
    });
</script>
@endpush

