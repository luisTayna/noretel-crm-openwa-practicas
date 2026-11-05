@extends('core::layouts.app')

@section('title', 'Regla: ' . $automation->name)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-magic"></i> {{ $automation->name }}
        </h1>
        <div>
            <a href="{{ route('network.automation.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="{{ route('network.automation.edit', $automation) }}" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <form action="{{ route('network.automation.toggle', $automation) }}" 
                  method="POST" 
                  class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $automation->is_active ? 'warning' : 'success' }}">
                    <i class="fas fa-{{ $automation->is_active ? 'pause' : 'play' }}"></i> 
                    {{ $automation->is_active ? 'Desactivar' : 'Activar' }}
                </button>
            </form>
        </div>
    </div>

    <div class="row">
        {{-- Información General --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información General</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="35%">Estado:</th>
                            <td>
                                @if($automation->is_active)
                                    <span class="badge badge-success">
                                        <i class="fas fa-check"></i> Activa
                                    </span>
                                @else
                                    <span class="badge badge-secondary">
                                        <i class="fas fa-pause"></i> Inactiva
                                    </span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Tipo de Trigger:</th>
                            <td><span class="badge badge-info">{{ $automation->trigger_type_label }}</span></td>
                        </tr>
                        <tr>
                            <th>Tipo de Acción:</th>
                            <td><span class="badge badge-primary">{{ $automation->action_type_label }}</span></td>
                        </tr>
                        <tr>
                            <th>Alcance:</th>
                            <td>{{ ucfirst(str_replace('_', ' ', $automation->scope)) }}</td>
                        </tr>
                        @if($automation->trigger_type === 'schedule' && $automation->schedule_cron)
                        <tr>
                            <th>Programación:</th>
                            <td>
                                <code>{{ $automation->schedule_cron }}</code>
                                <br>
                                <small class="text-muted">Formato CRON</small>
                            </td>
                        </tr>
                        @endif
                        @if($automation->trigger_conditions)
                        <tr>
                            <th>Condiciones:</th>
                            <td>
                                <pre class="bg-light p-2">{{ json_encode($automation->trigger_conditions, JSON_PRETTY_PRINT) }}</pre>
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <th>Creado por:</th>
                            <td>{{ $automation->creator->name ?? 'Sistema' }}</td>
                        </tr>
                        <tr>
                            <th>Creado:</th>
                            <td>{{ $automation->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Estadísticas --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Estadísticas de Ejecución</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <h3 class="mb-0">{{ $automation->execution_count }}</h3>
                            <small class="text-muted">Total</small>
                        </div>
                        <div class="col-4">
                            <h3 class="mb-0 text-success">{{ $automation->success_count }}</h3>
                            <small class="text-muted">Exitosas</small>
                        </div>
                        <div class="col-4">
                            <h3 class="mb-0 text-danger">{{ $automation->failure_count }}</h3>
                            <small class="text-muted">Fallidas</small>
                        </div>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Tasa de Éxito:</span>
                            <strong>{{ $automation->success_rate ? number_format($automation->success_rate, 1) : 0 }}%</strong>
                        </div>
                        @if($automation->execution_count > 0)
                        <div class="progress" style="height: 25px;">
                            <div class="progress-bar bg-{{ $automation->success_rate >= 80 ? 'success' : ($automation->success_rate >= 50 ? 'warning' : 'danger') }}" 
                                 style="width: {{ $automation->success_rate }}%">
                                {{ number_format($automation->success_rate, 0) }}%
                            </div>
                        </div>
                        @endif
                    </div>

                    @if($automation->last_executed_at)
                    <div class="alert alert-info">
                        <strong>Última Ejecución:</strong><br>
                        {{ $automation->last_executed_at->format('d/m/Y H:i:s') }}<br>
                        <small>({{ $automation->last_executed_at->diffForHumans() }})</small>
                    </div>
                    @else
                    <div class="alert alert-warning">
                        <strong>Nunca ejecutada</strong>
                    </div>
                    @endif

                    <form action="{{ route('network.automation.execute', $automation) }}" 
                          method="POST"
                          onsubmit="return confirm('¿Ejecutar esta regla manualmente?')">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-block">
                            <i class="fas fa-bolt"></i> Ejecutar Manualmente
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Descripción --}}
    @if($automation->description)
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Descripción</h6>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ $automation->description }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Configuración de Acción --}}
    @if($automation->action_config)
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Configuración de Acción</h6>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3">{{ json_encode($automation->action_config, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Historial de Ejecuciones --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Historial de Ejecuciones Recientes</h6>
                    <a href="{{ route('network.automation.history', $automation) }}" class="btn btn-sm btn-primary">
                        Ver Todo el Historial
                    </a>
                </div>
                <div class="card-body">
                    @if($automation->routerLogs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha/Hora</th>
                                    <th>Router</th>
                                    <th>Acción</th>
                                    <th>Usuario</th>
                                    <th>Tiempo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($automation->routerLogs->take(10) as $log)
                                <tr>
                                    <td>
                                        {{ $log->created_at->format('d/m/Y H:i:s') }}<br>
                                        <small class="text-muted">{{ $log->created_at->diffForHumans() }}</small>
                                    </td>
                                    <td>
                                        @if($log->router)
                                        <a href="{{ route('network.routers.show', $log->router) }}">
                                            {{ $log->router->name }}
                                        </a>
                                        @else
                                        <span class="text-muted">N/A</span>
                                        @endif
                                    </td>
                                    <td><small>{{ $log->action }}</small></td>
                                    <td>{{ $log->user->name ?? 'Sistema' }}</td>
                                    <td>{{ $log->execution_time ? number_format($log->execution_time, 2) . 's' : 'N/A' }}</td>
                                    <td>
                                        <span class="badge badge-{{ $log->success ? 'success' : 'danger' }}">
                                            {{ $log->success ? 'Exitoso' : 'Fallido' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-center text-muted mb-0">No hay ejecuciones registradas</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
