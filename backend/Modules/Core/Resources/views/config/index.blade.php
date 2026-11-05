@extends('core::layouts.master')

@section('title', 'Configuraciones')
@section('page-title', 'Gestión de Configuraciones')

@section('content')
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Importar configuraciones</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('core.config.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="json_file" class="form-label">Archivo JSON</label>
                        <input type="file" class="form-control @error('json_file') is-invalid @enderror" id="json_file" name="json_file" accept=".json">
                        @error('json_file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Importar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Exportar configuraciones</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('core.config.export') }}" method="GET">
                    <div class="mb-3">
                        <label class="form-label">Seleccionar módulos para exportar</label>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> Si no selecciona ningún módulo, se exportarán todas las configuraciones.
                        </div>
                        <div class="row">
                            @foreach($modules as $mod)
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="modules[]" value="{{ $mod }}" id="export_{{ $mod }}">
                                        <label class="form-check-label" for="export_{{ $mod }}">
                                            {{ ucfirst($mod) }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Exportar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Configuraciones del Sistema</h5>
        <a href="{{ route('core.config.create') }}" class="btn btn-primary btn-sm">
            <i class="fa fa-plus"></i> Nueva Configuración
        </a>
    </div>
    <div class="card-body">
        <!-- Filtros -->
        <form action="{{ route('core.config.index') }}" method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Buscar configuración" name="search" value="{{ $search }}">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="module" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los módulos</option>
                        @foreach($modules as $mod)
                            <option value="{{ $mod }}" {{ $module == $mod ? 'selected' : '' }}>{{ ucfirst($mod) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="{{ route('core.config.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="fa fa-refresh"></i> Reiniciar
                    </a>
                </div>
            </div>
        </form>

        <!-- Acordeón para módulos -->
        <div class="accordion" id="accordionConfig">
            @forelse($configs as $moduleName => $moduleConfigs)
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading{{ $moduleName }}">
                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $moduleName }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse{{ $moduleName }}">
                            <strong>{{ ucfirst($moduleName) }}</strong>
                            <span class="badge bg-primary ms-2">{{ count($moduleConfigs) }}</span>
                        </button>
                    </h2>
                    <div id="collapse{{ $moduleName }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading{{ $moduleName }}" data-bs-parent="#accordionConfig">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Parámetro</th>
                                            <th>Valor</th>
                                            <th>Tipo</th>
                                            <th>Descripción</th>
                                            <th>Editable</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($moduleConfigs as $paramName => $paramData)
                                            <tr>
                                                <td><code>{{ $paramName }}</code></td>
                                                <td>
                                                    @if($paramData['data_type'] == 'json')
                                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#jsonModal{{ str_replace('.', '_', $moduleName.'_'.$paramName) }}">
                                                            Ver JSON
                                                        </button>

                                                        <!-- Modal para ver JSON -->
                                                        <div class="modal fade" id="jsonModal{{ str_replace('.', '_', $moduleName.'_'.$paramName) }}" tabindex="-1" aria-labelledby="jsonModalLabel{{ str_replace('.', '_', $moduleName.'_'.$paramName) }}" aria-hidden="true">
                                                            <div class="modal-dialog modal-lg">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="jsonModalLabel{{ str_replace('.', '_', $moduleName.'_'.$paramName) }}">{{ $moduleName }}.{{ $paramName }}</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <pre><code>{{ json_encode($paramData['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @elseif($paramData['data_type'] == 'boolean')
                                                        @if($paramData['value'])
                                                            <span class="badge bg-success">true</span>
                                                        @else
                                                            <span class="badge bg-danger">false</span>
                                                        @endif
                                                    @else
                                                        {{ Str::limit(is_array($paramData['value']) ? json_encode($paramData['value']) : (string)$paramData['value'], 50) }}
                                                    @endif
                                                </td>
                                                <td>{{ $paramData['data_type'] }}</td>
                                                <td>{{ $paramData['description'] ?? 'Sin descripción' }}</td>
                                                <td>
                                                    @if($paramData['editable'])
                                                        <span class="badge bg-success">Sí</span>
                                                    @else
                                                        <span class="badge bg-danger">No</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="{{ route('core.config.edit', $paramData['id']) }}" class="btn btn-primary" title="Editar" {{ !$paramData['editable'] ? 'disabled' : '' }}>
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-warning" title="Restablecer" data-bs-toggle="modal" data-bs-target="#resetModal{{ $paramData['id'] }}" {{ !$paramData['editable'] ? 'disabled' : '' }}>
                                                            <i class="fa fa-refresh"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger" title="Eliminar" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $paramData['id'] }}" {{ !$paramData['editable'] ? 'disabled' : '' }}>
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </div>

                                                    <!-- Modal para restablecer configuración -->
                                                    <div class="modal fade" id="resetModal{{ $paramData['id'] }}" tabindex="-1" aria-labelledby="resetModalLabel{{ $paramData['id'] }}" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="resetModalLabel{{ $paramData['id'] }}">Restablecer Configuración</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>¿Estás seguro de que deseas restablecer la configuración <strong>{{ $moduleName }}.{{ $paramName }}</strong> a valores por defecto?</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                    <form action="{{ route('core.config.reset', $paramData['id']) }}" method="POST">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-warning">Restablecer</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Modal para eliminar configuración -->
                                                    <div class="modal fade" id="deleteModal{{ $paramData['id'] }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $paramData['id'] }}" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteModalLabel{{ $paramData['id'] }}">Eliminar Configuración</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>¿Estás seguro de que deseas eliminar la configuración <strong>{{ $moduleName }}.{{ $paramName }}</strong>?</p>
                                                                    <p class="text-danger"><strong>Atención:</strong> Esta acción no se puede deshacer.</p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                                    <form action="{{ route('core.config.destroy', $paramData['id']) }}" method="POST">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-danger">Eliminar</button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No se encontraron configuraciones.
                    <a href="{{ route('core.config.create') }}" class="alert-link">Crear una nueva configuración</a>.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
