@extends('core::layouts.master')

@section('title', 'Crear Rol')
@section('page-title', 'Crear Nuevo Rol')

@section('content')
<div class="card shadow-sm">
    <div class="card-header"><h5 class="mb-0">Formulario de Creación de Rol</h5></div>
    <div class="card-body">
        <form action="{{ route('core.roles.store') }}" method="POST">
            @csrf
            <input type="hidden" name="guard_name" value="web">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Nombre del Rol <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="ej. soporte" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Guard</label>
                    <div class="form-control bg-light">web</div>
                    <small class="text-muted">Spatie usará este guard para roles y permisos.</small>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label">Permisos Spatie</label>
                    <div class="alert alert-info"><i class="fa fa-info-circle"></i> Se guardan como <code>modulo.accion</code>, por ejemplo <code>customers.view</code>.</div>

                    <div class="accordion" id="accordionPermissions">
                        @foreach($modules as $moduleKey => $module)
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading{{ $moduleKey }}">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $moduleKey }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}" aria-controls="collapse{{ $moduleKey }}">
                                        <div class="d-flex align-items-center w-100">
                                            <strong>{{ $module['name'] }}</strong>
                                            <div class="ms-auto form-check">
                                                <input class="form-check-input module-checkbox" type="checkbox" id="module_{{ $moduleKey }}" data-module="{{ $moduleKey }}">
                                                <label class="form-check-label" for="module_{{ $moduleKey }}">Seleccionar todo</label>
                                            </div>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse{{ $moduleKey }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" aria-labelledby="heading{{ $moduleKey }}" data-bs-parent="#accordionPermissions">
                                    <div class="accordion-body">
                                        <div class="row">
                                            @foreach($module['actions'] as $actionKey => $actionName)
                                                @php($permissionName = $moduleKey . '.' . $actionKey)
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]" value="{{ $permissionName }}" id="permission_{{ $moduleKey }}_{{ $actionKey }}" data-module="{{ $moduleKey }}" {{ in_array($permissionName, old('permissions', [])) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="permission_{{ $moduleKey }}_{{ $actionKey }}">{{ $actionName }} <small class="text-muted d-block">{{ $permissionName }}</small></label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('permissions')<div class="text-danger mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <hr>
            <div class="d-flex justify-content-between">
                <a href="{{ route('core.roles.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Rol</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.module-checkbox').forEach(function (moduleCheckbox) {
        moduleCheckbox.addEventListener('change', function () {
            document.querySelectorAll(`input.permission-checkbox[data-module="${this.dataset.module}"]`).forEach(cb => cb.checked = this.checked);
        });
    });
    document.querySelectorAll('.permission-checkbox').forEach(function (permissionCheckbox) {
        permissionCheckbox.addEventListener('change', function () {
            const total = document.querySelectorAll(`input.permission-checkbox[data-module="${this.dataset.module}"]`).length;
            const checked = document.querySelectorAll(`input.permission-checkbox[data-module="${this.dataset.module}"]:checked`).length;
            document.getElementById(`module_${this.dataset.module}`).checked = total === checked && total > 0;
        });
    });
});
</script>
@endpush
