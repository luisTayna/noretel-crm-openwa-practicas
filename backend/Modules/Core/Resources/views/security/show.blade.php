@extends('core::layouts.master')

@section('title', 'Detalles de Política de Seguridad')

@section('page-title', 'Detalles de Política de Seguridad')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Política: {{ $policy->name }}</h5>
        <div>
            <a href="{{ route('core.security.index') }}" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Volver
            </a>
            @can('edit', 'security')
            <a href="{{ route('core.security.edit', $policy->id) }}" class="btn btn-primary">
                <i class="fa fa-edit"></i> Editar
            </a>
            @if(!$policy->active)
            <form action="{{ route('core.security.activate', $policy->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-check"></i> Activar
                </button>
            </form>
            @else
            <form action="{{ route('core.security.deactivate', $policy->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning">
                    <i class="fa fa-times"></i> Desactivar
                </button>
            </form>
            @endif
            @endcan
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Nombre:</strong> {{ $policy->name }}
                </div>
                <div class="mb-3">
                    <strong>Tipo:</strong>
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
                </div>
                <div class="mb-3">
                    <strong>Versión:</strong> {{ $policy->version }}
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Estado:</strong>
                    @if($policy->active)
                        <span class="badge bg-success">Activa</span>
                    @else
                        <span class="badge bg-warning">Inactiva</span>
                    @endif
                </div>
                <div class="mb-3">
                    <strong>Fecha de actualización:</strong> {{ $policy->update_date->format('d/m/Y H:i') }}
                </div>
                <div class="mb-3">
                    <strong>Fecha de creación:</strong> {{ $policy->created_at->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Configuración</h6>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        @foreach($policy->configuration as $key => $value)
                            <tr>
                                <th style="width: 30%">{{ str_replace('_', ' ', ucfirst($key)) }}</th>
                                <td>
                                    @if(is_bool($value))
                                        @if($value)
                                            <span class="badge bg-success">Sí</span>
                                        @else
                                            <span class="badge bg-danger">No</span>
                                        @endif
                                    @elseif(is_array($value))
                                        {{ implode(', ', $value) }}
                                    @else
                                        {{ $value }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if(count($previousVersions) > 0)
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Versiones Anteriores</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Versión</th>
                                <th>Nombre</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($previousVersions as $prevPolicy)
                                <tr>
                                    <td>{{ $prevPolicy->version }}</td>
                                    <td>{{ $prevPolicy->name }}</td>
                                    <td>{{ $prevPolicy->update_date->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @if($prevPolicy->active)
                                            <span class="badge bg-success">Activa</span>
                                        @else
                                            <span class="badge bg-warning">Inactiva</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('core.security.show', $prevPolicy->id) }}" class="btn btn-sm btn-outline-info">
                                            <i class="fa fa-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
