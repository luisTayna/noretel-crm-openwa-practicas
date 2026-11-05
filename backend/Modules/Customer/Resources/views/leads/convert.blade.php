@extends('core::layouts.master')

@section('title', 'Convertir Lead a Cliente')
@section('page-title', 'Convertir Lead a Cliente')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="fas fa-user-plus"></i> Convertir Lead: {{ $lead->name }}
    </div>

    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Está a punto de convertir este lead en un cliente. Complete la información adicional necesaria.
        </div>

        <form action="{{ route('customer.leads.convert', $lead->id) }}" method="POST">
            @csrf

            <div class="row mb-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Información del Lead</h5>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Nombre del Lead:</label>
                    <p>{{ $lead->name }}</p>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Contacto:</label>
                    <p>{{ $lead->contact }}</p>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Fuente:</label>
                    <p>{{ $lead->source ?: 'No especificada' }}</p>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Fecha de Captura:</label>
                    <p>{{ $lead->capture_date->format('d/m/Y') }}</p>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label fw-bold">Valor Potencial:</label>
                    <p>{{ $lead->potential_value ? '$'.number_format($lead->potential_value, 2) : 'No especificado' }}</p>
                </div>
            </div>

            <div class="row mb-4 mt-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Información del Cliente</h5>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label for="customer_type" class="form-label">Tipo de Cliente <span class="text-danger">*</span></label>
                    <select name="customer_type" id="customer_type" class="form-select @error('customer_type') is-invalid @enderror" required>
                        <option value="">Seleccione un tipo</option>
                        @foreach($customerTypes as $value => $label)
                        <option value="{{ $value }}" {{ old('customer_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('customer_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="first_name" class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $lead->name) }}" required>
                    @error('first_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="last_name" class="form-label">Apellido <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label for="identity_document" class="form-label">Documento de Identidad</label>
                    <input type="text" class="form-control @error('identity_document') is-invalid @enderror" id="identity_document" name="identity_document" value="{{ old('identity_document') }}">
                    @error('identity_document')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4 mb-3">
                    <label for="phone" class="form-label">Teléfono</label>
                    <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $lead->contact) }}">
                    @error('phone')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label for="segment" class="form-label">Segmento</label>
                    <select name="segment" id="segment" class="form-select @error('segment') is-invalid @enderror">
                        <option value="">Seleccione un segmento</option>
                        @foreach($segments as $value => $label)
                        <option value="{{ $value }}" {{ old('segment') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('segment')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-4 mt-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Dirección</h5>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label for="street" class="form-label">Calle/Avenida <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('street') is-invalid @enderror" id="street" name="street" value="{{ old('street') }}" required>
                    @error('street')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2 mb-3">
                    <label for="number" class="form-label">Número</label>
                    <input type="text" class="form-control @error('number') is-invalid @enderror" id="number" name="number" value="{{ old('number') }}">
                    @error('number')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2 mb-3">
                    <label for="city" class="form-label">Ciudad <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city') }}" required>
                    @error('city')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2 mb-3">
                    <label for="state" class="form-label">Estado <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('state') is-invalid @enderror" id="state" name="state" value="{{ old('state') }}" required>
                    @error('state')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4 mb-3">
                    <label for="postal_code" class="form-label">Código Postal</label>
                    <input type="text" class="form-control @error('postal_code') is-invalid @enderror" id="postal_code" name="postal_code" value="{{ old('postal_code') }}">
                    @error('postal_code')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- <div class="col-md-4 mb-3">
                    <label for="country" class="form-label">País <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('country') is-invalid @enderror" id="country" name="country" value="{{ old('country', 'México') }}" required>
                    @error('country')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div> -->
            </div>

            <div class="row mb-4 mt-4">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Notas</h5>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12 mb-3">
                    <label for="notes" class="form-label">Notas de Conversión</label>
                    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <a href="{{ route('customer.leads.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Convertir a Cliente</button>
            </div>
        </form>
    </div>
</div>
@endsection