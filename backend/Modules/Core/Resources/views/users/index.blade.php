@extends('core::layouts.master')

@section('title', 'Usuarios')
@section('page-title', 'Personal del Sistema')

@push('styles')
<style>
    /* Estilos específicos para el diseño Glassmorphism del listado de usuarios */
    .system-users-glass {
        padding: 0.5rem;
    }

    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--accent-color) 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);
    }

    .badge-pill {
        border-radius: 9999px;
        padding: 0.35em 0.85em;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .role-admin {
        background-color: rgba(245, 158, 11, 0.1) !important;
        color: #d97706 !important;
        border: 1px solid rgba(245, 158, 11, 0.2) !important;
    }

    .role-technician {
        background-color: rgba(16, 185, 129, 0.1) !important;
        color: #10b981 !important;
        border: 1px solid rgba(16, 185, 129, 0.2) !important;
    }

    .role-default {
        background-color: rgba(0, 153, 255, 0.1) !important;
        color: var(--secondary-color) !important;
        border: 1px solid rgba(0, 153, 255, 0.2) !important;
    }

    .status-active {
        color: #10b981 !important;
    }

    .status-inactive {
        color: #64748b !important;
    }

    .status-suspended {
        color: #ef4444 !important;
    }

    /* Tabla Glassmorphic limpia */
    .table-glass {
        margin-bottom: 0;
        color: var(--text-color);
    }
    
    .table-glass th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        font-weight: 600;
        border-bottom: 2px solid rgba(0, 0, 0, 0.03);
        padding: 1.1rem 1rem;
        background: transparent;
    }
    
    .table-glass td {
        vertical-align: middle;
        border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        padding: 1.1rem 1rem;
        background: transparent;
        color: var(--text-color);
    }

    .table-glass tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.4) !important;
    }

    /* Estilos para buscadores y filtros */
    .form-control-glass,
    .form-select-glass {
        background-color: rgba(255, 255, 255, 0.6) !important;
        backdrop-filter: blur(5px) !important;
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        color: var(--text-color) !important;
        border-radius: 12px !important;
        padding: 0.6rem 1rem !important;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.01) !important;
        transition: all 0.2s ease !important;
    }

    .form-control-glass:focus,
    .form-select-glass:focus {
        background-color: white !important;
        border-color: var(--secondary-color) !important;
        box-shadow: 0 0 0 0.25rem rgba(0, 153, 255, 0.15) !important;
    }

    .btn-glass-action {
        background-color: rgba(255, 255, 255, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        color: var(--text-muted) !important;
        border-radius: 12px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
    }

    .btn-glass-action:hover {
        background-color: white !important;
        color: var(--text-color) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;
    }
</style>
@endpush

@section('content')
<div class="system-users-glass">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 fw-bold" style="color: var(--dark-bg);">Personal del Sistema</h2>
            <p class="text-muted mb-0 small">Administradores, ventas y técnicos</p>
        </div>
        <div class="d-flex gap-2">
            @if(auth()->user()->canCreateInModule('users'))
            <a href="{{ route('core.users.create') }}" class="btn btn-primary px-4 py-2 d-flex align-items-center rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                <i class="fas fa-user-plus me-2"></i> Nuevo usuario interno
            </a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <form action="{{ route('core.users.index') }}" method="GET" id="filterForm">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="position-relative">
                    <i class="fas fa-search position-absolute top-50 translate-middle-y text-muted" style="left: 1rem;"></i>
                    <input type="text" name="search" class="form-control form-control-glass ps-5" placeholder="Buscar por usuario o email..." value="{{ request('search') }}" onchange="document.getElementById('filterForm').submit()">
                </div>
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-glass" onchange="document.getElementById('filterForm').submit()">
                    <option value="">Todos los roles</option>
                    @if(isset($roles))
                        @foreach($roles as $r)
                            <option value="{{ $r->name }}" {{ request('role') == $r->name ? 'selected' : '' }}>{{ ucfirst($r->name) }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-glass" onchange="document.getElementById('filterForm').submit()">
                    <option value="">Todos los estados</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Activo</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactivo</option>
                    <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspendido</option>
                </select>
            </div>
            <div class="col-md-2">
                <a href="{{ route('core.users.index') }}" class="btn btn-glass-action w-100 py-2">
                    <i class="fas fa-undo me-1"></i> Limpiar
                </a>
            </div>
        </div>
    </form>

    <!-- Table -->
    <div class="glass-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-glass">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Contacto</th>
                        <th>Rol(es)</th>
                        <th>Verificación</th>
                        <th>Último Acceso</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3 flex-shrink-0">
                                    {{ mb_strtoupper(mb_substr($user->username, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="fw-bold" style="color: var(--text-color);">{{ optional($user->employee)->full_name ?: $user->username }}</div>
                                    <div class="small text-muted">
                                        {{ $user->username }}
                                        @if($user->employee)
                                            · {{ $user->employee->position }} / {{ $user->employee->department }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="color: var(--text-color);">{{ $user->email }}</div>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @forelse($user->roles as $role)
                                    @php
                                        $roleClass = 'role-default';
                                        if($role->name == 'admin') $roleClass = 'role-admin';
                                        if($role->name == 'technician') $roleClass = 'role-technician';
                                    @endphp
                                    <span class="badge badge-pill {{ $roleClass }}">
                                        {{ ucfirst($role->name) }}
                                    </span>
                                @empty
                                    <span class="text-muted small">Sin rol asignado</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            @if($user->email_verified_at)
                                <span class="badge badge-pill badge-status-paid bg-transparent"><i class="fas fa-check me-1"></i> Verificado</span>
                            @else
                                <span class="badge badge-pill badge-status-pending bg-transparent"><i class="fas fa-clock me-1"></i> Pendiente</span>
                            @endif
                            
                            @if($user->requires_2fa)
                                <span class="badge badge-pill badge-plan bg-transparent ms-1"><i class="fas fa-shield-alt me-1"></i> 2FA</span>
                            @endif
                        </td>
                        <td>
                            <div class="text-muted">
                                @if($user->last_access)
                                    @if(\Carbon\Carbon::parse($user->last_access)->diffInMinutes(now()) < 5)
                                        <span class="text-success fw-semibold"><i class="fas fa-circle small me-1"></i> En línea ahora</span>
                                    @else
                                        {{ \Carbon\Carbon::parse($user->last_access)->format('d/m/Y H:i') }}
                                    @endif
                                @else
                                    Nunca ha ingresado
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($user->status == 'active')
                                <div class="status-active fw-semibold"><i class="fas fa-circle small me-1"></i> Activo</div>
                            @elseif($user->status == 'inactive')
                                <div class="status-inactive fw-semibold"><i class="fas fa-circle small me-1"></i> Inactivo</div>
                            @elseif($user->status == 'suspended')
                                <div class="status-suspended fw-semibold"><i class="fas fa-circle small me-1"></i> Suspendido</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-glass-action btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="border-radius: 12px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                                    <li><a class="dropdown-item rounded py-2" href="{{ route('core.users.show', $user->id) }}"><i class="fas fa-eye me-2 text-info"></i> Ver Detalles</a></li>
                                    
                                    @if(auth()->user()->canEditInModule('users'))
                                    <li><a class="dropdown-item rounded py-2" href="{{ route('core.users.edit', $user->id) }}"><i class="fas fa-edit me-2 text-warning"></i> Editar</a></li>
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    
                                        @if($user->status == 'active')
                                        <li>
                                            <form action="{{ route('core.users.deactivate', $user->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item rounded py-2"><i class="fas fa-ban me-2 text-muted"></i> Desactivar</button>
                                            </form>
                                        </li>
                                        @else
                                        <li>
                                            <form action="{{ route('core.users.activate', $user->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="dropdown-item rounded py-2"><i class="fas fa-check me-2 text-success"></i> Activar</button>
                                            </form>
                                        </li>
                                        @endif
                                    @endif

                                    @if(auth()->user()->canDeleteInModule('users') && $user->id != auth()->id())
                                    <li>
                                        <form action="{{ route('core.users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="dropdown-item rounded py-2 text-danger"><i class="fas fa-trash me-2"></i> Eliminar</button>
                                        </form>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                            <p class="mb-0">No se encontraron miembros del personal registrados.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 d-flex justify-content-end">
        {{ $users->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
