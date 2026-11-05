@extends('core::layouts.master')

@section('title', 'Políticas de Seguridad')

@section('page-title', 'Políticas de Seguridad')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Políticas de Seguridad</h5>
        <div>
            @if (auth()->user()->canCreateInModule('security'))
            <a href="{{ route('core.security.create') }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> Nueva Política
            </a>
            @endif

        </div>
    </div>
    <div class="card-body">
        @if($policies->isEmpty())
            <div class="alert alert-info mb-0">
                No se encontraron políticas de seguridad.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Versión</th>
                            <th>Actualización</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($policies as $policy)
                            <tr>
                                <td>{{ $policy->name }}</td>
                                <td>
                                    @switch($policy->policy_type)
                                        @case('password')
                                            <span class="badge bg-primary">Contraseñas</span>
                                            @break
                                        @case('login')
                                            <span class="badge bg-success">Inicio de sesión</span>
                                            @break
                                        @case('account_lockout')
                                            <span class="badge bg-warning">Bloqueo de cuenta</span>
                                            @break
                                        @case('session')
                                            <span class="badge bg-info">Sesión</span>
                                            @break
                                        @case('api')
                                            <span class="badge bg-secondary">API</span>
                                            @break
                                        @case('file_upload')
                                            <span class="badge bg-dark">Carga de archivos</span>
                                            @break
                                        @default
                                            <span class="badge bg-light text-dark">{{ $policy->policy_type }}</span>
                                    @endswitch
                                </td>
                                <td>{{ $policy->version }}</td>
                                <td>{{ $policy->update_date->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if($policy->active)
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-warning">Inactiva</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('core.security.show', $policy->id) }}" class="btn btn-sm btn-outline-info">
                                            <i class="fa fa-eye"></i> Ver
                                        </a>

                                        @if(auth()->user()->canEditInModule('security'))
                                        <a href="{{ route('core.security.edit', $policy->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-edit"></i> Editar
                                        </a>

                                        @if(!$policy->active)
                                        <form action="{{ route('core.security.activate', $policy->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <i class="fa fa-check"></i> Activar
                                            </button>
                                        </form>
                                        @else
                                        <form action="{{ route('core.security.deactivate', $policy->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                                <i class="fa fa-times"></i> Desactivar
                                            </button>
                                        </form>
                                        @endif
                                        @endif 
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $policies->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
