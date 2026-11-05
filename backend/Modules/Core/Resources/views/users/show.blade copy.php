@extends('core::layouts.master')

@section('title', 'Detalle de Usuario')
@section('page-title', 'Detalle de Usuario')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información del Usuario</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="d-inline-block bg-primary text-white rounded-circle p-3 mb-3">
                        <i class="fa fa-user fa-2x"></i>
                    </div>
                    <h5>{{ $user->username }}</h5>
                    <p class="text-muted">{{ $user->email }}</p>
                </div>
                
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Estado</span>
                        @if($user->status == 'active')
                            <span class="badge bg-success">Activo</span>
                        @elseif($user->status == 'inactive')
                            <span class="badge bg-secondary">Inactivo</span>
                        @elseif($user->status == 'suspended')
                            <span class="badge bg-danger">Suspendido</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Autenticación 2FA</span>
                        <span class="badge {{ $user->requires_2fa ? 'bg-primary' : 'bg-secondary' }}">
                            {{ $user->requires_2fa ? 'Activado' : 'Desactivado' }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Empleado asociado</span>
                        @if($user->employee)
                            <span>{{ $user->employee->first_name }} {{ $user->employee->last_name }}</span>
                        @else
                            <span class="text-muted">No asignado</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Último acceso</span>
                        <span>{{ $user->last_access ? $user->last_access->format('d/m/Y H:i') : 'Nunca' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Fecha de creación</span>
                        <span>{{ $user->created_at->format('d/m/Y H:i') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>Última actualización</span>
                        <span>{{ $user->updated_at->format('d/m/Y H:i') }}</span>
                    </li>
                </ul>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between">
                    <a href="{{ route('core.users.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> Volver
                    </a>
                    <a href="{{ route('core.users.edit', $user->id) }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-edit"></i> Editar
                    </a>
                </div>
            </div>
        </div>
        
        <div class="card shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">Roles asignados</h5>
            </div>
            <div class="card-body">
                @if($user->roles->count() > 0)
                    <ul class="list-group list-group-flush">
                        @foreach($user->roles as $role)
                            <li class="list-group-item">
                                <h6 class="mb-1">{{ $role->name }}</h6>
                                <p class="text-muted mb-0 small">{{ $role->description }}</p>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-center text-muted mb-0">Este usuario no tiene roles asignados</p>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Registro de Actividad</h5>
                <div>
                    <select class="form-select form-select-sm" id="filterActivity">
                        <option value="">Todas las actividades</option>
                        <option value="login_successful">Inicios de sesión</option>
                        <option value="password_changed">Cambios de contraseña</option>
                        <option value="profile_updated">Actualizaciones de perfil</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Acción</th>
                                <th>Módulo</th>
                                <th>Detalle</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr class="activity-row" data-type="{{ $log->action_type }}">
                                    <td>{{ $log->action_date->format('d/m/Y H:i:s') }}</td>
                                    <td>{{ $log->action_type }}</td>
                                    <td>{{ $log->module }}</td>
                                    <td>{{ $log->action_detail }}</td>
                                    <td>{{ $log->source_ip }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No hay registros de actividad</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Filtro de actividades
        $('#filterActivity').on('change', function() {
            var filterValue = $(this).val();
            
            if (filterValue === '') {
                $('.activity-row').show();
            } else {
                $('.activity-row').hide();
                $('.activity-row[data-type="' + filterValue + '"]').show();
            }
        });
    });
</script>
@endpush