@extends('core::layouts.master')

@section('title', 'Roles')
@section('page-title', 'Gestión de Roles')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Listado de Roles</h5>
        @if(auth()->user()->canCreateInModule('roles'))
        <a href="{{ route('core.roles.create') }}" class="btn btn-primary btn-sm">
            <i class="fa fa-plus"></i> Nuevo Rol
        </a>
        @endif
    </div>
    <div class="card-body">
        <form action="{{ route('core.roles.index') }}" method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-10">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Buscar por nombre de rol" name="search" value="{{ request('search') }}">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <a href="{{ route('core.roles.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="fa fa-refresh"></i> Reiniciar
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rol</th>
                        <th>Guard</th>
                        <th>Permisos</th>
                        <th>Usuarios</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($roles as $role)
                    <tr>
                        <td>{{ $role->id }}</td>
                        <td><strong>{{ $role->name }}</strong></td>
                        <td><span class="badge bg-secondary">{{ $role->guard_name }}</span></td>
                        <td><span class="badge bg-info">{{ $role->permissions->count() }}</span></td>
                        <td><span class="badge bg-primary">{{ $role->users->count() }}</span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('core.roles.show', $role->id) }}" class="btn btn-info" title="Ver"><i class="fa fa-eye"></i></a>
                                @if(auth()->user()->canEditInModule('roles'))
                                <a href="{{ route('core.roles.edit', $role->id) }}" class="btn btn-primary" title="Editar"><i class="fa fa-edit"></i></a>
                                @endif
                                @if(auth()->user()->canDeleteInModule('roles'))
                                <button type="button" class="btn btn-danger" title="Eliminar" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $role->id }}"><i class="fa fa-trash"></i></button>
                                @endif
                            </div>

                            @if(auth()->user()->canDeleteInModule('roles'))
                            <div class="modal fade" id="deleteModal{{ $role->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Eliminar Rol</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>¿Estás seguro de que deseas eliminar el rol <strong>{{ $role->name }}</strong>?</p>
                                            @if($role->users->count() > 0)
                                                <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Este rol está asignado a {{ $role->users->count() }} usuario(s). Debes quitarlo antes de eliminarlo.</div>
                                            @else
                                                <p class="text-danger"><strong>Atención:</strong> Esta acción no se puede deshacer.</p>
                                            @endif
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="{{ route('core.roles.destroy', $role->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger" {{ $role->users->count() > 0 ? 'disabled' : '' }}>Eliminar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">No se encontraron roles</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $roles->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection
