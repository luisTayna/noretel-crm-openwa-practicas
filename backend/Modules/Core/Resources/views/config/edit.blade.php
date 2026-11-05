@extends('core::layouts.master')

@section('title', 'Editar Configuración')
@section('page-title', 'Editar Configuración')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Formulario de Edición de Configuración</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('core.config.update', $config['id']) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Módulo</label>
                    <input type="text" class="form-control" value="{{ $config['module'] }}" disabled>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Parámetro</label>
                    <input type="text" class="form-control" value="{{ $config['parameter'] }}" disabled>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Tipo de dato</label>
                    <input type="text" class="form-control" value="{{ $config['data_type'] }}" disabled>
                </div>

                <div class="col-md-6">
                    <div class="alert alert-info mt-4">
                        <i class="fa fa-info-circle"></i> El tipo de dato no puede ser modificado.
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="value" class="form-label">Valor <span class="text-danger">*</span></label>

                    @if($config['data_type'] === 'string')
                        <input type="text" class="form-control @error('value') is-invalid @enderror" name="value" value="{{ old('value', is_string($config['value']) ? $config['value'] : json_encode($config['value'])) }}">
                    @elseif($config['data_type'] === 'integer' || $config['data_type'] === 'float')
                        <input type="number" class="form-control @error('value') is-invalid @enderror" name="value" value="{{ old('value', $config['value']) }}" step="{{ $config['data_type'] === 'float' ? '0.01' : '1' }}">
                    @elseif($config['data_type'] === 'boolean')
                        <select class="form-select @error('value') is-invalid @enderror" name="value">
                            <option value="true" {{ old('value', $config['value'] ? 'true' : 'false') === 'true' ? 'selected' : '' }}>True</option>
                            <option value="false" {{ old('value', $config['value'] ? 'true' : 'false') === 'false' ? 'selected' : '' }}>False</option>
                        </select>
                    @elseif($config['data_type'] === 'json')
                        <textarea class="form-control @error('value') is-invalid @enderror" name="value" rows="5">{{ old('value', json_encode($config['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) }}</textarea>
                        <div class="form-text">Ingrese un objeto JSON válido.</div>
                    @endif

                    @error('value')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label for="description" class="form-label">Descripción</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $config['description']) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input @error('editable') is-invalid @enderror" type="checkbox" role="switch" id="editable" name="editable" value="1" {{ old('editable', $config['editable']) ? 'checked' : '' }}>
                        <label class="form-check-label" for="editable">Permitir edición</label>
                        @error('editable')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <hr>

            <div class="d-flex justify-content-between">
                <a href="{{ route('core.config.index', ['module' => $config['module']]) }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar Configuración</button>
            </div>
        </form>
    </div>
</div>
@endsection
