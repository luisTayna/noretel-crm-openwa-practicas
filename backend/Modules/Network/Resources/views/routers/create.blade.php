@extends('core::layouts.master')

@section('title', 'Crear Router')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-router"></i> Crear Nuevo Router
        </h1>
        <a href="{{ route('network.routers.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('network.routers.store') }}" method="POST">
                @csrf

                <div class="row">
                    {{-- Información Básica --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Información Básica</h5>

                        <div class="form-group">
                            <label for="name">Nombre <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('name') is-invalid @enderror"
                                   id="name"
                                   name="name"
                                   value="{{ old('name') }}"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="code">Código (opcional, se genera automático)</label>
                            <input type="text"
                                   class="form-control @error('code') is-invalid @enderror"
                                   id="code"
                                   name="code"
                                   value="{{ old('code') }}"
                                   placeholder="Ej: MK-CEN-0001">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Si no lo ingresas, se generará automáticamente</small>
                        </div>

                        <div class="form-group">
                            <label for="brand">Marca <span class="text-danger">*</span></label>
                            <select class="form-control @error('brand') is-invalid @enderror"
                                    id="brand"
                                    name="brand"
                                    required>
                                <option value="">Seleccione una marca</option>
                                @foreach($brands as $brand)
                                <option value="{{ $brand }}" {{ old('brand') == $brand ? 'selected' : '' }}>
                                    {{ $brand }}
                                </option>
                                @endforeach
                            </select>
                            @error('brand')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="model">Modelo</label>
                            <input type="text"
                                   class="form-control @error('model') is-invalid @enderror"
                                   id="model"
                                   name="model"
                                   value="{{ old('model') }}"
                                   placeholder="Ej: RB4011iGS+">
                            @error('model')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="serial_number">Número de Serie</label>
                            <input type="text"
                                   class="form-control @error('serial_number') is-invalid @enderror"
                                   id="serial_number"
                                   name="serial_number"
                                   value="{{ old('serial_number') }}">
                            @error('serial_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Configuración de Red --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Configuración de Red</h5>

                        <div class="form-group">
                            <label for="ip_address">Dirección IP <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('ip_address') is-invalid @enderror"
                                   id="ip_address"
                                   name="ip_address"
                                   value="{{ old('ip_address') }}"
                                   required
                                   placeholder="192.168.1.1">
                            @error('ip_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="mac_address">Dirección MAC</label>
                            <input type="text"
                                   class="form-control @error('mac_address') is-invalid @enderror"
                                   id="mac_address"
                                   name="mac_address"
                                   value="{{ old('mac_address') }}"
                                   placeholder="00:0C:29:12:34:56">
                            @error('mac_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="zone">Zona <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control @error('zone') is-invalid @enderror"
                                   id="zone"
                                   name="zone"
                                   value="{{ old('zone') }}"
                                   required
                                   placeholder="Centro, Norte, Sur, etc.">
                            @error('zone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="location">Ubicación Física</label>
                            <input type="text"
                                   class="form-control @error('location') is-invalid @enderror"
                                   id="location"
                                   name="location"
                                   value="{{ old('location') }}"
                                   placeholder="Av. Principal 123">
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="node_id">Nodo</label>
                            <select class="form-control @error('node_id') is-invalid @enderror"
                                    id="node_id"
                                    name="node_id">
                                <option value="">Sin nodo asignado</option>
                                @foreach($nodes as $node)
                                <option value="{{ $node->id }}" {{ old('node_id') == $node->id ? 'selected' : '' }}>
                                    {{ $node->name }} ({{ $node->zone }})
                                </option>
                                @endforeach
                            </select>
                            @error('node_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="max_clients">Capacidad Máxima de Clientes <span class="text-danger">*</span></label>
                            <input type="number"
                                   class="form-control @error('max_clients') is-invalid @enderror"
                                   id="max_clients"
                                   name="max_clients"
                                   value="{{ old('max_clients', 50) }}"
                                   required
                                   min="1">
                            @error('max_clients')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    {{-- Credenciales (opcional) --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Credenciales de Acceso (Opcional)</h5>
                        <p class="text-muted small">Estas credenciales se guardarán encriptadas</p>

                        <div class="form-group">
                            <label for="credentials_username">Usuario</label>
                            <input type="text"
                                   class="form-control"
                                   id="credentials_username"
                                   name="credentials[username]"
                                   value="{{ old('credentials.username') }}"
                                   placeholder="admin">
                        </div>

                        <div class="form-group">
                            <label for="credentials_password">Contraseña</label>
                            <input type="password"
                                   class="form-control"
                                   id="credentials_password"
                                   name="credentials[password]"
                                   placeholder="••••••••">
                        </div>

                        <div class="form-group">
                            <label for="credentials_api_port">Puerto API</label>
                            <input type="number"
                                   class="form-control"
                                   id="credentials_api_port"
                                   name="credentials[api_port]"
                                   value="{{ old('credentials.api_port', 8728) }}"
                                   placeholder="8728">
                            <small class="form-text text-muted">Para MikroTik: 8728, para Huawei: 80/443</small>
                        </div>
                    </div>

                    {{-- Estado y Notas --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Estado y Notas</h5>

                        <div class="form-group">
                            <label for="status">Estado</label>
                            <select class="form-control @error('status') is-invalid @enderror"
                                    id="status"
                                    name="status">
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Activo</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                <option value="maintenance" {{ old('status') == 'maintenance' ? 'selected' : '' }}>Mantenimiento</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="notes">Notas</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes"
                                      name="notes"
                                      rows="5"
                                      placeholder="Notas adicionales sobre este router...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('network.routers.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Router
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
