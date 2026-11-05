@extends('core::layouts.master')

@section('title', 'Reglas de Automatización')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-magic"></i> Reglas de Automatización
        </h1>
        <a href="{{ route('network.automation.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Regla
        </a>
    </div>

    {{-- Estadísticas Rápidas --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Reglas Activas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $rules->where('is_active', true)->count() }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-power-off fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Ejecuciones
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $rules->sum('execution_count') }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-play fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Tasa de Éxito Promedio
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $rules->avg('success_rate') ? number_format($rules->avg('success_rate'), 1) : 0 }}%
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Programadas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ $rules->where('trigger_type', 'schedule')->count() }}
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Lista de Reglas --}}
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Trigger</th>
                            <th>Acción</th>
                            <th>Alcance</th>
                            <th>Ejecuciones</th>
                            <th>Tasa Éxito</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rules as $rule)
                        <tr>
                            <td>
                                <a href="{{ route('network.automation.show', $rule) }}">
                                    <strong>{{ $rule->name }}</strong>
                                </a>
                                @if($rule->description)
                                <br>
                                <small class="text-muted">{{ Str::limit($rule->description, 50) }}</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-info">{{ $rule->trigger_type_label }}</span>
                                @if($rule->trigger_type === 'schedule' && $rule->schedule_cron)
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> {{ $rule->schedule_cron }}
                                </small>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-primary">{{ $rule->action_type_label }}</span>
                            </td>
                            <td>
                                <small>{{ ucfirst(str_replace('_', ' ', $rule->scope)) }}</small>
                            </td>
                            <td class="text-center">
                                <strong>{{ $rule->execution_count }}</strong>
                                <br>
                                <small class="text-success">✓ {{ $rule->success_count }}</small>
                                <small class="text-danger">✗ {{ $rule->failure_count }}</small>
                            </td>
                            <td class="text-center">
                                @if($rule->execution_count > 0)
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-{{ $rule->success_rate >= 80 ? 'success' : ($rule->success_rate >= 50 ? 'warning' : 'danger') }}"
                                         style="width: {{ $rule->success_rate }}%">
                                        {{ number_format($rule->success_rate, 0) }}%
                                    </div>
                                </div>
                                @else
                                <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($rule->is_active)
                                    <span class="badge badge-success">
                                        <i class="fas fa-check"></i> Activa
                                    </span>
                                @else
                                    <span class="badge badge-secondary">
                                        <i class="fas fa-pause"></i> Inactiva
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('network.automation.show', $rule) }}"
                                       class="btn btn-sm btn-info"
                                       title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('network.automation.edit', $rule) }}"
                                       class="btn btn-sm btn-primary"
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="{{ route('network.automation.toggle', $rule) }}"
                                          method="POST"
                                          class="d-inline">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm btn-{{ $rule->is_active ? 'warning' : 'success' }}"
                                                title="{{ $rule->is_active ? 'Desactivar' : 'Activar' }}">
                                            <i class="fas fa-{{ $rule->is_active ? 'pause' : 'play' }}"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('network.automation.execute', $rule) }}"
                                          method="POST"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Ejecutar esta regla manualmente?')">
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm btn-secondary"
                                                title="Ejecutar ahora">
                                            <i class="fas fa-bolt"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No hay reglas de automatización configuradas</p>
                                <a href="{{ route('network.automation.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Crear Primera Regla
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginación --}}
            @if($rules->hasPages())
            <div class="d-flex justify-content-center">
                {{ $rules->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.progress {
    border-radius: 3px;
}
</style>
@endpush
