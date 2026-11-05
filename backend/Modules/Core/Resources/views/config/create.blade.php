@extends('core::layouts.master')

@section('title', 'Crear Configuración')
@section('page-title', 'Crear Nueva Configuración')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Formulario de Creación de Configuración</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('core.config.store') }}" method="POST">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="module" class="form-label">Módulo <span class="text-danger">*</span></label>
                    <select class="form-select @error('module') is-invalid @enderror" id="module" name="module" required>
                        <option value="">Seleccionar módulo</option>
                        @foreach($modules as $mod)
                            @if($mod === 'new_module')
                                <option value="new_module" {{ old('module') === 'new_module' ? 'selected' : '' }}>Crear nuevo módulo</option>
                            @else
                                <option value="{{ $mod }}" {{ old('module') === $mod ? 'selected' : '' }}>{{ ucfirst($mod) }}</option>
                            @endif
                        @endforeach
                    </select>
                    @error('module')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6" id="newModuleContainer" style="{{ old('module') === 'new_module' ? '' : 'display: none;' }}">
                    <label for="new_module" class="form-label">Nombre del nuevo módulo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('new_module') is-invalid @enderror" id="new_module" name="new_module" value="{{ old('new_module') }}">
                    @error('new_module')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="parameter" class="form-label">Parámetro <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('parameter') is-invalid @enderror" id="parameter" name="parameter" value="{{ old('parameter') }}" required>
                    @error('parameter')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="data_type" class="form-label">Tipo de dato <span class="text-danger">*</span></label>
                    <select class="form-select @error('data_type') is-invalid @enderror" id="data_type" name="data_type" required>
                        <option value="string" {{ old('data_type') === 'string' ? 'selected' : '' }}>String</option>
                        <option value="integer" {{ old('data_type') === 'integer' ? 'selected' : '' }}>Integer</option>
                        <option value="float" {{ old('data_type') === 'float' ? 'selected' : '' }}>Float</option>
                        <option value="boolean" {{ old('data_type') === 'boolean' ? 'selected' : '' }}>Boolean</option>
                        <option value="json" {{ old('data_type') === 'json' ? 'selected' : '' }}>JSON</option>
                    </select>
                    @error('data_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="value" class="form-label">Valor <span class="text-danger">*</span></label>
                    <div id="string_input" class="value-input" {{ old('data_type') && old('data_type') !== 'string' ? 'style=display:none' : '' }}>
                        <input type="text" class="form-control @error('value') is-invalid @enderror" name="value" value="{{ old('value') }}">
                    </div>
                    <div id="number_input" class="value-input" {{ old('data_type') === 'integer' || old('data_type') === 'float' ? '' : 'style=display:none' }}>
                        <input type="number" class="form-control @error('value') is-invalid @enderror" name="value" value="{{ old('value') }}" step="{{ old('data_type') === 'float' ? '0.01' : '1' }}">
                    </div>
                    <div id="boolean_input" class="value-input" {{ old('data_type') === 'boolean' ? '' : 'style=display:none' }}>
                        <select class="form-select @error('value') is-invalid @enderror" name="value">
                            <option value="true" {{ old('value') === 'true' ? 'selected' : '' }}>True</option>
                            <option value="false" {{ old('value') === 'false' ? 'selected' : '' }}>False</option>
                        </select>
                    </div>
                    <div id="json_input" class="value-input" {{ old('data_type') === 'json' ? '' : 'style=display:none' }}>
                        <textarea class="form-control @error('value') is-invalid @enderror" name="value" rows="5">{{ old('value') }}</textarea>
                        <div class="form-text">Ingrese un objeto JSON válido.</div>
                    </div>
                    @error('value')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="description" class="form-label">Descripción</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input @error('editable') is-invalid @enderror" type="checkbox" role="switch" id="editable" name="editable" value="1" {{ old('editable', '1') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label" for="editable">Permitir edición</label>
                        @error('editable')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <hr>

            <div class="d-flex justify-content-between">
                <a href="{{ route('core.config.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Configuración</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Mostrar/ocultar campo de nuevo módulo
        $('#module').on('change', function() {
            if ($(this).val() === 'new_module') {
                $('#newModuleContainer').show();
                $('#new_module').prop('required', true);
            } else {
                $('#newModuleContainer').hide();
                $('#new_module').prop('required', false);
            }
        });

        // Mostrar/ocultar campos según el tipo de dato
        $('#data_type').on('change', function() {
            const dataType = $(this).val();

            // Ocultar todos los inputs
            $('.value-input').hide();

            // Mostrar el input correspondiente
            switch (dataType) {
                case 'integer':
                case 'float':
                    $('#number_input').show();
                    $('#number_input input').prop('step', dataType === 'float' ? '0.01' : '1');
                    break;
                case 'boolean':
                    $('#boolean_input').show();
                    break;
                case 'json':
                    $('#json_input').show();
                    break;
                default: // string
                    $('#string_input').show();
                    break;
            }
        });
    });
</script>
@endpush
