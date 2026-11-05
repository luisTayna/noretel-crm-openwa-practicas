@extends('core::layouts.master')

@section('title', 'Editar Nodo')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-sitemap"></i> Editar Nodo: {{ $node->name }}
        </h1>
        <a href="{{ route('network.nodes.show', $node) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('network.nodes.update', $node) }}" method="POST">
                @csrf
                @method('PUT')

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
                                   value="{{ old('name', $node->name) }}" 
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="code">Código <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('code') is-invalid @enderror" 
                                   id="code" 
                                   name="code" 
                                   value="{{ old('code', $node->code) }}" 
                                   required>
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="type">Tipo <span class="text-danger">*</span></label>
                            <select class="form-control @error('type') is-invalid @enderror" 
                                    id="type" 
                                    name="type" 
                                    required>
                                @foreach($types as $type)
                                <option value="{{ $type }}" {{ old('type', $node->type) == $type ? 'selected' : '' }}>
                                    {{ ucfirst($type) }}
                                </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="parent_node_id">Nodo Padre (Opcional)</label>
                            <select class="form-control @error('parent_node_id') is-invalid @enderror" 
                                    id="parent_node_id" 
                                    name="parent_node_id">
                                <option value="">Sin nodo padre</option>
                                @foreach($parentNodes as $parentNode)
                                <option value="{{ $parentNode->id }}" {{ old('parent_node_id', $node->parent_node_id) == $parentNode->id ? 'selected' : '' }}>
                                    {{ $parentNode->name }} ({{ $parentNode->zone }})
                                </option>
                                @endforeach
                            </select>
                            @error('parent_node_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="capacity">Capacidad <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control @error('capacity') is-invalid @enderror" 
                                   id="capacity" 
                                   name="capacity" 
                                   value="{{ old('capacity', $node->capacity) }}" 
                                   required
                                   min="1">
                            @error('capacity')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="coverage_radius">Radio de Cobertura (metros) <span class="text-danger">*</span></label>
                            <input type="number" 
                                   class="form-control @error('coverage_radius') is-invalid @enderror" 
                                   id="coverage_radius" 
                                   name="coverage_radius" 
                                   value="{{ old('coverage_radius', $node->coverage_radius) }}" 
                                   required
                                   min="0"
                                   step="0.01">
                            @error('coverage_radius')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Ubicación --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Ubicación</h5>

                        <div class="form-group">
                            <label for="location">Ubicación Física <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('location') is-invalid @enderror" 
                                   id="location" 
                                   name="location" 
                                   value="{{ old('location', $node->location) }}" 
                                   required>
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="zone">Zona <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('zone') is-invalid @enderror" 
                                   id="zone" 
                                   name="zone" 
                                   value="{{ old('zone', $node->zone) }}" 
                                   required>
                            @error('zone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="district">Distrito</label>
                            <input type="text" 
                                   class="form-control @error('district') is-invalid @enderror" 
                                   id="district" 
                                   name="district" 
                                   value="{{ old('district', $node->district) }}">
                            @error('district')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="province">Provincia</label>
                            <input type="text" 
                                   class="form-control @error('province') is-invalid @enderror" 
                                   id="province" 
                                   name="province" 
                                   value="{{ old('province', $node->province) }}">
                            @error('province')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="department">Departamento</label>
                            <input type="text" 
                                   class="form-control @error('department') is-invalid @enderror" 
                                   id="department" 
                                   name="department" 
                                   value="{{ old('department', $node->department) }}">
                            @error('department')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="latitude">Latitud</label>
                                    <input type="text" 
                                           class="form-control @error('latitude') is-invalid @enderror" 
                                           id="latitude" 
                                           name="latitude" 
                                           value="{{ old('latitude', $node->latitude) }}">
                                    @error('latitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="longitude">Longitud</label>
                                    <input type="text" 
                                           class="form-control @error('longitude') is-invalid @enderror" 
                                           id="longitude" 
                                           name="longitude" 
                                           value="{{ old('longitude', $node->longitude) }}">
                                    @error('longitude')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-6">
                        <h5 class="mb-3">Estado</h5>

                        <div class="form-group">
                            <label for="status">Estado</label>
                            <select class="form-control @error('status') is-invalid @enderror" 
                                    id="status" 
                                    name="status">
                                <option value="active" {{ old('status', $node->status) == 'active' ? 'selected' : '' }}>Activo</option>
                                <option value="inactive" {{ old('status', $node->status) == 'inactive' ? 'selected' : '' }}>Inactivo</option>
                                <option value="maintenance" {{ old('status', $node->status) == 'maintenance' ? 'selected' : '' }}>Mantenimiento</option>
                                <option value="planned" {{ old('status', $node->status) == 'planned' ? 'selected' : '' }}>Planificado</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" 
                                       class="custom-control-input" 
                                       id="is_operational" 
                                       name="is_operational" 
                                       value="1"
                                       {{ old('is_operational', $node->is_operational) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_operational">
                                    Operacional
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h5 class="mb-3">Descripción</h5>

                        <div class="form-group">
                            <label for="description">Descripción</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="5">{{ old('description', $node->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('network.nodes.show', $node) }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Nodo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection