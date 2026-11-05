@extends('core::layouts.master')

@section('title', 'Crear Regla de Automatización')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-magic"></i> Crear Nueva Regla de Automatización
        </h1>
        <a href="{{ route('network.automation.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('network.automation.store') }}" method="POST">
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
                                   required
                                   placeholder="Ej: Reinicio automático de routers">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">Descripción</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="3"
                                      placeholder="Describe el propósito de esta regla...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" 
                                       class="custom-control-input" 
                                       id="is_active" 
                                       name="is_active" 
                                       value="1"
                                       {{ old('is_active', true) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">
                                    Activar regla inmediatamente
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Trigger y Acción --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Configuración</h5>

                        <div class="form-group">
                            <label for="trigger_type">Tipo de Trigger <span class="text-danger">*</span></label>
                            <select class="form-control @error('trigger_type') is-invalid @enderror" 
                                    id="trigger_type" 
                                    name="trigger_type" 
                                    required>
                                <option value="">Seleccione un trigger</option>
                                @foreach($triggerTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('trigger_type') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                            @error('trigger_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                ¿Cuándo se ejecutará esta regla?
                            </small>
                        </div>

                        <div class="form-group" id="schedule_cron_group" style="display: none;">
                            <label for="schedule_cron">Programación CRON</label>
                            <input type="text" 
                                   class="form-control @error('schedule_cron') is-invalid @enderror" 
                                   id="schedule_cron" 
                                   name="schedule_cron" 
                                   value="{{ old('schedule_cron') }}"
                                   placeholder="0 3 * * *">
                            @error('schedule_cron')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Formato: minuto hora día mes día_semana<br>
                                Ej: "0 3 * * *" = Todos los días a las 3:00 AM
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="action_type">Tipo de Acción <span class="text-danger">*</span></label>
                            <select class="form-control @error('action_type') is-invalid @enderror" 
                                    id="action_type" 
                                    name="action_type" 
                                    required>
                                <option value="">Seleccione una acción</option>
                                @foreach($actionTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('action_type') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                            @error('action_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                ¿Qué acción se ejecutará?
                            </small>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    {{-- Alcance --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Alcance</h5>

                        <div class="form-group">
                            <label for="scope">Aplicar a <span class="text-danger">*</span></label>
                            <select class="form-control @error('scope') is-invalid @enderror" 
                                    id="scope" 
                                    name="scope" 
                                    required>
                                <option value="all_routers" {{ old('scope') == 'all_routers' ? 'selected' : '' }}>Todos los routers</option>
                                <option value="specific_routers" {{ old('scope') == 'specific_routers' ? 'selected' : '' }}>Routers específicos</option>
                                <option value="zone" {{ old('scope') == 'zone' ? 'selected' : '' }}>Por zona</option>
                                <option value="node" {{ old('scope') == 'node' ? 'selected' : '' }}>Por nodo</option>
                            </select>
                            @error('scope')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" id="target_routers_group" style="display: none;">
                            <label for="target_routers">Routers Específicos</label>
                            <select class="form-control @error('target_routers') is-invalid @enderror" 
                                    id="target_routers" 
                                    name="target_routers[]" 
                                    multiple
                                    size="5">
                                @foreach($routers as $router)
                                <option value="{{ $router->id }}">
                                    {{ $router->name }} ({{ $router->zone }})
                                </option>
                                @endforeach
                            </select>
                            @error('target_routers')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Mantén presionado Ctrl para seleccionar múltiples
                            </small>
                        </div>

                        <div class="form-group" id="target_zone_group" style="display: none;">
                            <label for="target_zone">Zona</label>
                            <input type="text" 
                                   class="form-control @error('target_zone') is-invalid @enderror" 
                                   id="target_zone" 
                                   name="target_zone" 
                                   value="{{ old('target_zone') }}"
                                   placeholder="Ej: Centro, Norte, Sur">
                            @error('target_zone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" id="target_node_group" style="display: none;">
                            <label for="target_node_id">Nodo</label>
                            <select class="form-control @error('target_node_id') is-invalid @enderror" 
                                    id="target_node_id" 
                                    name="target_node_id">
                                <option value="">Seleccione un nodo</option>
                                @foreach($nodes as $node)
                                <option value="{{ $node->id }}">
                                    {{ $node->name }} ({{ $node->zone }})
                                </option>
                                @endforeach
                            </select>
                            @error('target_node_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Prioridad --}}
                    <div class="col-md-6">
                        <h5 class="mb-3">Opciones Adicionales</h5>

                        <div class="form-group">
                            <label for="priority">Prioridad</label>
                            <select class="form-control @error('priority') is-invalid @enderror" 
                                    id="priority" 
                                    name="priority">
                                <option value="low" {{ old('priority', 'medium') == 'low' ? 'selected' : '' }}>Baja</option>
                                <option value="medium" {{ old('priority', 'medium') == 'medium' ? 'selected' : '' }}>Media</option>
                                <option value="high" {{ old('priority', 'medium') == 'high' ? 'selected' : '' }}>Alta</option>
                                <option value="critical" {{ old('priority', 'medium') == 'critical' ? 'selected' : '' }}>Crítica</option>
                            </select>
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> Nota:</h6>
                            <p class="mb-0 small">
                                Las reglas de automatización ayudan a gestionar la red de forma eficiente. 
                                Asegúrate de probar la regla antes de activarla en producción.
                            </p>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('network.automation.index') }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Regla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mostrar/ocultar campo de CRON según trigger type
    const triggerType = document.getElementById('trigger_type');
    const cronGroup = document.getElementById('schedule_cron_group');
    
    triggerType.addEventListener('change', function() {
        if (this.value === 'schedule') {
            cronGroup.style.display = 'block';
        } else {
            cronGroup.style.display = 'none';
        }
    });

    // Mostrar/ocultar campos según scope
    const scope = document.getElementById('scope');
    const targetRoutersGroup = document.getElementById('target_routers_group');
    const targetZoneGroup = document.getElementById('target_zone_group');
    const targetNodeGroup = document.getElementById('target_node_group');
    
    scope.addEventListener('change', function() {
        targetRoutersGroup.style.display = 'none';
        targetZoneGroup.style.display = 'none';
        targetNodeGroup.style.display = 'none';
        
        if (this.value === 'specific_routers') {
            targetRoutersGroup.style.display = 'block';
        } else if (this.value === 'zone') {
            targetZoneGroup.style.display = 'block';
        } else if (this.value === 'node') {
            targetNodeGroup.style.display = 'block';
        }
    });

    // Trigger inicial
    if (triggerType.value === 'schedule') {
        cronGroup.style.display = 'block';
    }
    
    scope.dispatchEvent(new Event('change'));
});
</script>
@endpush