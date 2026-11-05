@extends('core::layouts.master')

@section('title', 'Editar Usuario')
@section('page-title', 'Editar Usuario')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">Formulario de Edición de Usuario</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('core.users.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Nombre de usuario <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $user->username) }}" required>
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Deje este campo en blanco si no desea cambiar la contraseña.
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label for="status" class="form-label">Estado <span class="text-danger">*</span></label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                        <option value="active" {{ (old('status', $user->status) == 'active') ? 'selected' : '' }}>Activo</option>
                        <option value="inactive" {{ (old('status', $user->status) == 'inactive') ? 'selected' : '' }}>Inactivo</option>
                        <option value="suspended" {{ (old('status', $user->status) == 'suspended') ? 'selected' : '' }}>Suspendido</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="employee_id" class="form-label">Empleado asociado</label>
                    <select class="form-select @error('employee_id') is-invalid @enderror" id="employee_id" name="employee_id">
                        <option value="">Ninguno</option>
                        @foreach(\App\Models\Employee::all() as $employee)
                            <option value="{{ $employee->id }}" {{ (old('employee_id', $user->employee_id) == $employee->id) ? 'selected' : '' }}>
                                {{ $employee->first_name }} {{ $employee->last_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('employee_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input @error('requires_2fa') is-invalid @enderror" type="checkbox" role="switch" id="requires_2fa" name="requires_2fa" value="1" {{ old('requires_2fa', $user->requires_2fa) ? 'checked' : '' }}>
                        <label class="form-check-label" for="requires_2fa">Requiere autenticación de dos factores</label>
                        @error('requires_2fa')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label">Roles</label>
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                @foreach($roles as $role)
                                    <div class="col-md-4 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role_{{ $role->id }}" 
                                                {{ (old('roles') && in_array($role->id, old('roles'))) || 
                                                   (!old('roles') && in_array($role->id, $userRoles)) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="role_{{ $role->id }}">
                                                {{ $role->name }}
                                                <small class="d-block text-muted">{{ $role->description }}</small>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @error('roles')
                        <div class="text-danger mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <hr>
            
            <div class="d-flex justify-content-between">
                <a href="{{ route('core.users.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
            </div>
        </form>
    </div>
</div>
@endsection