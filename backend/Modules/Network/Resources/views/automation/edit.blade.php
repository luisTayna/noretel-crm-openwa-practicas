@section('title', 'Editar Regla de Automatización')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-magic"></i> Editar: {{ $automation->name }}
        </h1>
        <a href="{{ route('network.automation.show', $automation) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('network.automation.update', $automation) }}" method="POST">
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
                                   value="{{ old('name', $automation->name) }}" 
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="description">Descripción</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="3">{{ old('description', $automation->description) }}</textarea>
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
                                       {{ old('is_active', $automation->is_active) ? 'checked' : '' }}>
                                <label class="custom-control-label" for="is_active">
                                    Regla activa
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
                                @foreach($triggerTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('trigger_type', $automation->trigger_type) == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                            @error('trigger_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" id="schedule_cron_group">
                            <label for="schedule_cron">Programación CRON</label>
                            <input type="text" 
                                   class="form-control @error('schedule_cron') is-invalid @enderror" 
                                   id="schedule_cron" 
                                   name="schedule_cron" 
                                   value="{{ old('schedule_cron', $automation->schedule_cron) }}">
                            @error('schedule_cron')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Ej: "0 3 * * *" = Todos los días a las 3:00 AM
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="action_type">Tipo de Acción <span class="text-danger">*</span></label>
                            <select class="form-control @error('action_type') is-invalid @enderror" 
                                    id="action_type" 
                                    name="action_type" 
                                    required>
                                @foreach($actionTypes as $value => $label)
                                <option value="{{ $value }}" {{ old('action_type', $automation->action_type) == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                            @error('action_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                                <option value="all_routers" {{ old('scope', $automation->scope) == 'all_routers' ? 'selected' : '' }}>Todos los routers</option>
                                <option value="specific_routers" {{ old('scope', $automation->scope) == 'specific_routers' ? 'selected' : '' }}>Routers específicos</option>
                                <option value="zone" {{ old('scope', $automation->scope) == 'zone' ? 'selected' : '' }}>Por zona</option>
                                <option value="node" {{ old('scope', $automation->scope) == 'node' ? 'selected' : '' }}>Por nodo</option>
                            </select>
                            @error('scope')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" id="target_routers_group">
                            <label for="target_routers">Routers Específicos</label>
                            <select class="form-control @error('target_routers') is-invalid @enderror" 
                                    id="target_routers" 
                                    name="target_routers[]" 
                                    multiple
                                    size="5">
                                @foreach($routers as $router)
                                <option value="{{ $router->id }}" 
                                    {{ in_array($router->id, old('target_routers', $automation->target_routers ?? [])) ? 'selected' : '' }}>
                                    {{ $router->name }} ({{ $router->zone }})
                                </option>
                                @endforeach
                            </select>
                            @error('target_routers')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" id="target_zone_group">
                            <label for="target_zone">Zona</label>
                            <input type="text" 
                                   class="form-control @error('target_zone') is-invalid @enderror" 
                                   id="target_zone" 
                                   name="target_zone" 
                                   value="{{ old('target_zone', $automation->target_zone) }}">
                            @error('target_zone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group" id="target_node_group">
                            <label for="target_node_id">Nodo</label>
                            <select class="form-control @error('target_node_id') is-invalid @enderror" 
                                    id="target_node_id" 
                                    name="target_node_id">
                                <option value="">Seleccione un nodo</option>
                                @foreach($nodes as $node)
                                <option value="{{ $node->id }}" 
                                    {{ old('target_node_id', $automation->target_node_id) == $node->id ? 'selected' : '' }}>
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
                                <option value="low" {{ old('priority', $automation->priority) == 'low' ? 'selected' : '' }}>Baja</option>
                                <option value="medium" {{ old('priority', $automation->priority) == 'medium' ? 'selected' : '' }}>Media</option>
                                <option value="high" {{ old('priority', $automation->priority) == 'high' ? 'selected' : '' }}>Alta</option>
                                <option value="critical" {{ old('priority', $automation->priority) == 'critical' ? 'selected' : '' }}>Crítica</option>
                            </select>
                            @error('priority')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Advertencia:</h6>
                            <p class="mb-0 small">
                                Los cambios en esta regla se aplicarán inmediatamente si está activa.
                            </p>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('network.automation.show', $automation) }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Regla
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
    const triggerType = document.getElementById('trigger_type');
    const cronGroup = document.getElementById('schedule_cron_group');
    const scope = document.getElementById('scope');
    const targetRoutersGroup = document.getElementById('target_routers_group');
    const targetZoneGroup = document.getElementById('target_zone_group');
    const targetNodeGroup = document.getElementById('target_node_group');
    
    function updateCronVisibility() {
        if (triggerType.value === 'schedule') {
            cronGroup.style.display = 'block';
        } else {
            cronGroup.style.display = 'none';
        }
    }
    
    function updateScopeFields() {
        targetRoutersGroup.style.display = 'none';
        targetZoneGroup.style.display = 'none';
        targetNodeGroup.style.display = 'none';
        
        if (scope.value === 'specific_routers') {
            targetRoutersGroup.style.display = 'block';
        } else if (scope.value === 'zone') {
            targetZoneGroup.style.display = 'block';
        } else if (scope.value === 'node') {
            targetNodeGroup.style.display = 'block';
        }
    }
    
    triggerType.addEventListener('change', updateCronVisibility);
    scope.addEventListener('change', updateScopeFields);
    
    // Inicializar visibilidad
    updateCronVisibility();
    updateScopeFields();
});
</script>
@endpush