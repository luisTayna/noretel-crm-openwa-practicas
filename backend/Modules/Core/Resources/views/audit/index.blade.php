@extends('core::layouts.master')

@section('title', 'Panel de Auditoría')

@section('page-title', 'Panel de Auditoría')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Logs de Auditoría</h5>
        <div>
            <a href="{{ route('core.audit.dashboard') }}" class="btn btn-info">
                <i class="fa fa-chart-bar"></i> Dashboard
            </a>
            <a href="{{ route('core.audit.export', Request::all()) }}" class="btn btn-success">
                <i class="fa fa-download"></i> Exportar CSV
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('core.audit.index') }}" class="mb-4">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Usuario</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">Todos los usuarios</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->username }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="module" class="form-label">Módulo</label>
                    <select name="module" id="module" class="form-select">
                        <option value="">Todos los módulos</option>
                        @foreach($modules as $module)
                            <option value="{{ $module }}" {{ request('module') == $module ? 'selected' : '' }}>
                                {{ ucfirst($module) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="action_type" class="form-label">Tipo de Acción</label>
                    <select name="action_type" id="action_type" class="form-select">
                        <option value="">Todas las acciones</option>
                        @foreach($actionTypes as $action)
                            <option value="{{ $action }}" {{ request('action_type') == $action ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $action)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Fecha desde</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="{{ request('date_from') }}">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="date_to" class="form-label">Fecha hasta</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-9 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fa fa-search"></i> Filtrar
                    </button>
                    <a href="{{ route('core.audit.index') }}" class="btn btn-secondary">
                        <i class="fa fa-times"></i> Limpiar filtros
                    </a>
                </div>
            </div>
        </form>

        @if($logs->isEmpty())
            <div class="alert alert-info mb-0">
                No se encontraron registros de auditoría con los filtros seleccionados.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Fecha</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                            <th>Detalle</th>
                            <th>IP</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td>{{ $log->user ? $log->user->username : 'N/A' }}</td>
                                <td>{{ $log->action_date->format('d/m/Y H:i:s') }}</td>
                                <td>{{ ucfirst($log->module) }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $log->action_type)) }}</td>
                                <td class="text-truncate" style="max-width: 300px;">{{ $log->action_detail }}</td>
                                <td>{{ $log->source_ip }}</td>
                                <td>
                                    <a href="{{ route('core.audit.show', $log->id) }}" class="btn btn-sm btn-outline-info">
                                        <i class="fa fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
