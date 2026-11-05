@extends('services::layouts.master')

@section('title', 'Crear Servicio')
@section('page-title', 'Crear Nuevo Servicio')

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.services.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver al listado
        </a>
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información del Servicio</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('services.services.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre del servicio <span class="text-danger">*</span></label>
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

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="service_type" class="form-label">Tipo de servicio <span class="text-danger">*</span></label>
                                    <select class="form-control @error('service_type') is-invalid @enderror" id="service_type" name="service_type" required>
                                        <option value="">Seleccione un tipo</option>
                                        @foreach($serviceTypes as $value => $option)
                                            <option value="{{ $value }}"
                                                {{ old('service_type') == $value ? 'selected' : '' }}
                                                {{ $option['active'] ? '' : 'disabled' }}>
                                                {{ $option['label'] }}{{ $option['active'] ? '' : ' (inactivo)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('service_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="technology" class="form-label">Tecnología <span class="text-danger">*</span></label>
                                    <select class="form-control @error('technology') is-invalid @enderror" id="technology" name="technology" required>
                                        <option value="">Seleccione una tecnología</option>
                                        @foreach($technologies as $value => $option)
                                            <option value="{{ $value }}"
                                                {{ old('technology') == $value ? 'selected' : '' }}
                                                {{ $option['active'] ? '' : 'disabled' }}>
                                                {{ $option['label'] }}{{ $option['active'] ? '' : ' (inactivo)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('technology')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', '1') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label" for="active">
                                    Servicio activo
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary me-md-2">Limpiar</button>
                            <button type="submit" class="btn btn-primary">Guardar Servicio</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
