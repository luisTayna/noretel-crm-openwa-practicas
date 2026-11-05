@extends('core::layouts.master')

@section('title', 'Detalle de Usuario')
@section('page-title', 'Detalle de Usuario')

@section('content')
<div class="row g-4">
    <!-- Columna Izquierda: Información de Usuario y Roles -->
    <div class="col-md-4">
        <!-- Tarjeta de Información -->
        <div class="card glass-card border-0 mb-4">
            <div class="card-header border-0 bg-transparent py-3">
                <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-id-card me-2 text-primary"></i>Información del Usuario</h5>
            </div>
            <div class="card-body pt-0">
                <div class="text-center mb-4">
                    <div class="d-inline-block rounded-circle p-3 mb-3" style="background: linear-gradient(135deg, rgba(0, 153, 255, 0.1), rgba(0, 204, 102, 0.1)); border: 1px solid rgba(255,255,255,0.8);">
                        <i class="fa fa-user fa-3x text-primary"></i>
                    </div>
                    <h5 class="fw-bold mb-1" style="color: var(--dark-bg);">{{ $user->username }}</h5>
                    <p class="text-muted small mb-3">{{ $user->email }}</p>
                    @if($user->employee)
                        <div class="p-2 rounded-3 bg-white bg-opacity-50 border border-white">
                            <p class="mb-0 fw-bold text-dark">{{ $user->employee->full_name }}</p>
                            <small class="text-muted">{{ $user->employee->position }} · {{ $user->employee->department }}</small>
                        </div>
                    @endif
                </div>
                
                <ul class="list-group list-group-flush" style="background: transparent;">
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                        <span class="text-muted small fw-semibold">Estado</span>
                        @if($user->status == 'active')
                            <span class="badge badge-pill badge-status-paid bg-transparent">Activo</span>
                        @elseif($user->status == 'inactive')
                            <span class="badge badge-pill bg-transparent text-muted border border-secondary px-3">Inactivo</span>
                        @elseif($user->status == 'suspended')
                            <span class="badge badge-pill badge-status-overdue bg-transparent">Suspendido</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                        <span class="text-muted small fw-semibold">Autenticación 2FA</span>
                        <span class="badge badge-pill bg-transparent {{ $user->requires_2fa ? 'text-primary border border-primary' : 'text-muted border border-secondary' }} px-3">
                            {{ $user->requires_2fa ? 'Activado' : 'Desactivado' }}
                        </span>
                    </li>
                    @if($user->employee)
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                        <span class="text-muted small fw-semibold">Estado laboral</span>
                        <span class="badge badge-pill bg-transparent text-{{ $user->employee->status === 'active' ? 'success border border-success' : ($user->employee->status === 'suspended' ? 'danger border border-danger' : 'secondary border border-secondary') }} px-3">
                            {{ ucfirst($user->employee->status) }}
                        </span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                        <span class="text-muted small fw-semibold">Fecha de ingreso</span>
                        <span class="fw-medium text-dark">{{ $user->employee->hire_date ? $user->employee->hire_date->format('d/m/Y') : 'Sin fecha' }}</span>
                    </li>
                    @endif
                    
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                        <span class="text-muted small fw-semibold">Último acceso</span>
                        <span class="fw-medium text-dark">{{ $user->last_access ? $user->last_access->format('d/m/Y H:i') : 'Nunca' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                        <span class="text-muted small fw-semibold">Fecha creación</span>
                        <span class="fw-medium text-dark">{{ $user->created_at->format('d/m/Y H:i') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-3" style="border-bottom: none;">
                        <span class="text-muted small fw-semibold">Última actualización</span>
                        <span class="fw-medium text-dark">{{ $user->updated_at->format('d/m/Y H:i') }}</span>
                    </li>
                </ul>
            </div>
            <div class="card-footer border-0 bg-transparent py-3 d-flex justify-content-between">
                <a href="{{ route('core.users.index') }}" class="btn btn-glass-action btn-sm px-3 rounded-pill">
                    <i class="fa fa-arrow-left me-1"></i> Volver
                </a>
                <a href="{{ route('core.users.edit', $user->id) }}" class="btn btn-primary btn-sm px-4 rounded-pill shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                    <i class="fa fa-edit me-1"></i> Editar
                </a>
            </div>
        </div>
        
        <!-- Tarjeta de Roles -->
        <div class="card glass-card border-0 mb-4">
            <div class="card-header border-0 bg-transparent py-3">
                <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-shield-alt me-2 text-success"></i>Roles Asignados</h5>
            </div>
            <div class="card-body pt-0">
                @if($user->roles->count() > 0)
                    <ul class="list-group list-group-flush" style="background: transparent;">
                        @foreach($user->roles as $role)
                            <li class="list-group-item bg-transparent px-0 py-3 d-flex align-items-center justify-content-between" style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">{{ ucfirst($role->name) }}</h6>
                                    <p class="text-muted mb-0 small">{{ $role->permissions->count() }} permisos asignados</p>
                                </div>
                                <span class="badge badge-pill bg-transparent text-success border border-success px-3"><i class="fas fa-check me-1"></i> Activo</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-center text-muted mb-0 py-3">Este usuario no tiene roles asignados</p>
                @endif
            </div>
        </div>
    </div>
    
    <!-- Columna Derecha: Registro de Actividad -->
    <div class="col-md-8">
        <div class="card glass-card border-0">
            <div class="card-header d-flex justify-content-between align-items-center border-0 bg-transparent py-4 px-4">
                <h5 class="mb-0 fw-bold" style="color: var(--dark-bg);"><i class="fas fa-history me-2 text-info"></i>Registro de Actividad</h5>
                <div style="width: 220px;">
                    <select class="form-select form-select-glass form-select-sm" id="filterActivity" style="padding: 0.4rem 1rem !important; font-size: 0.85rem !important;">
                        <option value="">Todas las actividades</option>
                        <option value="login_successful">Inicios de sesión</option>
                        <option value="password_changed">Cambios de contraseña</option>
                        <option value="profile_updated">Actualizaciones de perfil</option>
                    </select>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-glass mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Acción</th>
                                <th>Módulo</th>
                                <th>Detalle</th>
                                <th class="pe-4">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                                <tr class="activity-row" data-type="{{ $log->action_type }}">
                                    <td class="ps-4 text-muted small">{{ $log->action_date->format('d/m/Y H:i:s') }}</td>
                                    <td>
                                        <span class="badge badge-pill bg-transparent text-secondary border border-secondary">
                                            {{ $log->action_type }}
                                        </span>
                                    </td>
                                    <td class="text-dark fw-medium">{{ ucfirst($log->module) }}</td>
                                    <td class="text-muted small">{{ $log->action_detail }}</td>
                                    <td class="pe-4 text-muted small">{{ $log->source_ip }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fas fa-info-circle fa-2x mb-2 opacity-50"></i>
                                        <p class="mb-0">No hay registros de actividad para este usuario.</p>
                                    </td>
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
