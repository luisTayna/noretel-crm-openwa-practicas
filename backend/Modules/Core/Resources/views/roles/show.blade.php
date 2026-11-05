@extends('core::layouts.master')

@section('title', 'Detalle de Rol')
@section('page-title', 'Detalle de Rol')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h5 class="mb-0">Información del Rol</h5></div>
            <div class="card-body text-center">
                <div class="d-inline-block bg-primary text-white rounded-circle p-3 mb-3"><i class="fa fa-user-shield fa-2x"></i></div>
                <h5>{{ $role->name }}</h5>
                <span class="badge bg-secondary">guard: {{ $role->guard_name }}</span>
                <hr>
                <p class="text-muted mb-0">Rol Spatie. Los permisos se guardan en <code>role_has_permissions</code>.</p>
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('core.roles.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left"></i> Volver</a>
                @if(auth()->user()->canEditInModule('roles'))
                <a href="{{ route('core.roles.edit', $role->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> Editar</a>
                @endif
            </div>
        </div>

        @if(auth()->user()->canEditInModule('roles'))
        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Asignar rol a usuario</h5></div>
            <div class="card-body">
                <form action="{{ route('core.roles.assign') }}" method="POST">
                    @csrf
                    <input type="hidden" name="role_id" value="{{ $role->id }}">
                    <div class="mb-3">
                        <label for="user_id" class="form-label">Usuario</label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">Seleccione un usuario</option>
                            @foreach($availableUsers as $availableUser)
                                <option value="{{ $availableUser->id }}">{{ $availableUser->username }} ({{ $availableUser->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" {{ $availableUsers->isEmpty() ? 'disabled' : '' }}>Asignar</button>
                </form>
            </div>
        </div>
        @endif
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header"><h5 class="mb-0">Permisos asignados</h5></div>
            <div class="card-body">
                @if($role->permissions->count() > 0)
                    <div class="accordion" id="permissionsAccordion">
                        @foreach($permissionsByModule as $moduleName => $permissions)
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingPerm{{ $loop->index }}">
                                    <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePerm{{ $loop->index }}">
                                        <strong>{{ $moduleName }}</strong>
                                        <span class="badge bg-primary ms-2">{{ $permissions->count() }}</span>
                                    </button>
                                </h2>
                                <div id="collapsePerm{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#permissionsAccordion">
                                    <div class="accordion-body p-0">
                                        <table class="table table-sm mb-0">
                                            <thead><tr><th>Permiso</th><th>Acción</th></tr></thead>
                                            <tbody>
                                                @foreach($permissions as $permission)
                                                    @php
                                                        $permissionParts = array_pad(explode('.', $permission->name, 2), 2, '');
                                                        $action = $permissionParts[1];
                                                    @endphp
                                                    <tr>
                                                        <td><code>{{ $permission->name }}</code></td>
                                                        <td>{{ $action }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-center text-muted mb-0">Este rol no tiene permisos asignados.</p>
                @endif
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header"><h5 class="mb-0">Usuarios con este rol</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead><tr><th>Usuario</th><th>Email</th><th>Estado</th><th>Acciones</th></tr></thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td>{{ $user->username }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td><span class="badge bg-{{ $user->status === 'active' ? 'success' : ($user->status === 'suspended' ? 'danger' : 'secondary') }}">{{ ucfirst($user->status) }}</span></td>
                                    <td>
                                        <a href="{{ route('core.users.show', $user->id) }}" class="btn btn-sm btn-info"><i class="fa fa-eye"></i></a>
                                        @if(auth()->user()->canEditInModule('roles'))
                                        <form action="{{ route('core.roles.remove') }}" method="POST" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $user->id }}">
                                            <input type="hidden" name="role_id" value="{{ $role->id }}">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Quitar este rol al usuario?')"><i class="fa fa-times"></i></button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center">No hay usuarios con este rol</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-center">{{ $users->links() }}</div>
        </div>
    </div>
</div>
@endsection
