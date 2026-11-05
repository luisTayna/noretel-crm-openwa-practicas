@extends('core::layouts.master')

@section('title', 'Crear Usuario Interno')
@section('page-title', 'Alta de Personal Interno')

@section('content')
<div class="card glass-card border-0 mb-4">
    <div class="card-header d-flex justify-content-between align-items-center border-0 bg-transparent py-4">
        <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-user-plus me-2 text-primary"></i>Alta de Personal Interno</h5>
        <a href="{{ route('core.users.index') }}" class="btn btn-glass-action btn-sm px-3 py-2 d-flex align-items-center"><i class="fas fa-arrow-left me-1"></i> Volver</a>
    </div>
    <div class="card-body px-4 pb-4 pt-0">
        <form action="{{ route('core.users.store') }}" method="POST">
            @csrf

            <h5 class="pb-2 mb-3 fw-bold" style="color: var(--secondary-color); border-bottom: 2px solid rgba(0, 153, 255, 0.1);">
                <i class="fas fa-id-card me-2"></i>Datos personales / laborales
            </h5>
            
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label for="first_name" class="form-label small fw-semibold text-muted">Nombre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-glass @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="last_name" class="form-label small fw-semibold text-muted">Apellido <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-glass @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="position" class="form-label small fw-semibold text-muted">Cargo / posición <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-glass @error('position') is-invalid @enderror" id="position" name="position" value="{{ old('position') }}" placeholder="Vendedor, Técnico, Administrador" required>
                    @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="department" class="form-label small fw-semibold text-muted">Departamento <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-glass @error('department') is-invalid @enderror" id="department" name="department" value="{{ old('department') }}" placeholder="Ventas, Operaciones, Administración" required>
                    @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label for="employee_status" class="form-label small fw-semibold text-muted">Estado laboral <span class="text-danger">*</span></label>
                    <select class="form-select form-select-glass @error('employee_status') is-invalid @enderror" id="employee_status" name="employee_status" required>
                        <option value="active" {{ old('employee_status', 'active') === 'active' ? 'selected' : '' }}>Activo</option>
                        <option value="inactive" {{ old('employee_status') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                        <option value="suspended" {{ old('employee_status') === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                    </select>
                    @error('employee_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label for="hire_date" class="form-label small fw-semibold text-muted">Fecha ingreso <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-glass @error('hire_date') is-invalid @enderror" id="hire_date" name="hire_date" value="{{ old('hire_date', now()->format('Y-m-d')) }}" required>
                    @error('hire_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <h5 class="pb-2 mb-3 fw-bold" style="color: var(--secondary-color); border-bottom: 2px solid rgba(0, 153, 255, 0.1);">
                <i class="fas fa-key me-2"></i>Acceso al sistema
            </h5>
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="username" class="form-label small fw-semibold text-muted">Usuario <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-glass @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" required>
                    @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="email" class="form-label small fw-semibold text-muted">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-glass @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label small fw-semibold text-muted">Contraseña <span class="text-danger">*</span></label>
                    <input type="password" class="form-control form-control-glass @error('password') is-invalid @enderror" id="password" name="password" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label small fw-semibold text-muted">Estado de cuenta <span class="text-danger">*</span></label>
                    <select class="form-select form-select-glass @error('status') is-invalid @enderror" id="status" name="status" required>
                        <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Activo</option>
                        <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactivo</option>
                        <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspendido</option>
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8 d-flex align-items-end ps-3">
                    <div class="form-check form-switch pb-2">
                        <input class="form-check-input" type="checkbox" id="requires_2fa" name="requires_2fa" value="1" {{ old('requires_2fa') ? 'checked' : '' }} style="cursor: pointer; width: 2.5em; height: 1.25em;">
                        <label class="form-check-label small fw-semibold text-muted ms-2" for="requires_2fa" style="cursor: pointer; padding-top: 0.15rem;">Exigir autenticación de dos factores (2FA)</label>
                    </div>
                </div>
            </div>

            <h5 class="pb-2 mb-3 fw-bold" style="color: var(--secondary-color); border-bottom: 2px solid rgba(0, 153, 255, 0.1);">
                <i class="fas fa-user-shield me-2"></i>Rol interno
            </h5>
            
            <div class="row g-3 mb-4">
                @foreach($roles as $role)
                    <div class="col-md-3">
                        <label class="glass-card p-3 w-100 h-100 d-block cursor-pointer position-relative hover-lift" style="cursor:pointer; border: 1px solid rgba(255,255,255,0.8); background: rgba(255,255,255,0.45); border-radius: 16px !important;">
                            <div class="d-flex align-items-center mb-2">
                                <input type="radio" name="roles[]" value="{{ $role->id }}" class="form-check-input me-3" {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }} required style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                                <strong class="text-dark">{{ ($roleLabels ?? [])[$role->name] ?? ucfirst($role->name) }}</strong>
                            </div>
                            <small class="d-block text-muted ms-4"><i class="fas fa-shield-alt me-1 text-primary"></i>{{ $role->permissions->count() }} permisos activos</small>
                        </label>
                    </div>
                @endforeach
                @error('roles')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-end gap-2 mt-5">
                <a href="{{ route('core.users.index') }}" class="btn btn-glass-action px-4 py-2 rounded-pill fw-semibold"><i class="fas fa-times me-1"></i> Cancelar</a>
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                    <i class="fas fa-save me-1"></i> Registrar personal
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
