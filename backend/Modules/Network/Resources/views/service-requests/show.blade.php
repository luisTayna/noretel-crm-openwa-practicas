@extends('core::layouts.master')

@section('title', 'Solicitud: ' . $serviceRequest->ticket_number)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-ticket-alt"></i> Solicitud: {{ $serviceRequest->ticket_number }}
        </h1>
        <a href="{{ route('network.service-requests.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="row">
        {{-- Información de la Solicitud --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Información de la Solicitud</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%">Ticket:</th>
                            <td><strong>{{ $serviceRequest->ticket_number }}</strong></td>
                        </tr>
                        <tr>
                            <th>Tipo:</th>
                            <td>{{ $serviceRequest->type_label }}</td>
                        </tr>
                        <tr>
                            <th>Estado:</th>
                            <td>
                                <span class="badge badge-{{
                                    $serviceRequest->status === 'completed' ? 'success' :
                                    ($serviceRequest->status === 'failed' ? 'danger' :
                                    ($serviceRequest->status === 'in_progress' ? 'info' : 'warning'))
                                }}">
                                    {{ $serviceRequest->status_label }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Prioridad:</th>
                            <td>
                                <span class="badge badge-{{
                                    $serviceRequest->priority === 'critical' || $serviceRequest->priority === 'high' ? 'danger' :
                                    ($serviceRequest->priority === 'medium' ? 'warning' : 'secondary')
                                }}">
                                    {{ $serviceRequest->priority_label }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Automatizada:</th>
                            <td>
                                @if($serviceRequest->is_automated)
                                    <span class="badge badge-info">Sí</span>
                                @else
                                    <span class="badge badge-secondary">No</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Creada:</th>
                            <td>{{ $serviceRequest->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        @if($serviceRequest->completed_at)
                        <tr>
                            <th>Completada:</th>
                            <td>{{ $serviceRequest->completed_at->format('d/m/Y H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Tiempo de resolución:</th>
                            <td><strong>{{ $serviceRequest->resolution_time }} minutos</strong></td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Cliente y Router --}}
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cliente y Router</h6>
                </div>
                <div class="card-body">
                    <h6>Cliente:</h6>
                    @if($serviceRequest->customer)
                    <p class="mb-2">
                        <strong>{{ $serviceRequest->customer->user->name ?? 'N/A' }}</strong><br>
                        <small class="text-muted">{{ $serviceRequest->customer->user->email ?? '' }}</small>
                    </p>
                    @else
                    <p class="text-muted">No disponible</p>
                    @endif

                    <hr>

                    <h6>Router:</h6>
                    @if($serviceRequest->router)
                    <p class="mb-2">
                        <a href="{{ route('network.routers.show', $serviceRequest->router) }}">
                            <strong>{{ $serviceRequest->router->name }}</strong>
                        </a><br>
                        <small class="text-muted">IP: {{ $serviceRequest->router->ip_address }}</small><br>
                        <small class="text-muted">Zona: {{ $serviceRequest->router->zone }}</small>
                    </p>
                    @else
                    <p class="text-muted">No disponible</p>
                    @endif

                    <hr>

                    <h6>Técnico Asignado:</h6>
                    @if($serviceRequest->assignedTechnician)
                    <p class="mb-0">
                        <strong>{{ $serviceRequest->assignedTechnician->name }}</strong><br>
                        <small class="text-muted">{{ $serviceRequest->assignedTechnician->email }}</small>
                    </p>
                    @else
                    <p class="text-muted mb-0">Sin asignar</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Descripción y Notas --}}
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Descripción y Notas</h6>
                </div>
                <div class="card-body">
                    <h6>Descripción del Cliente:</h6>
                    <p>{{ $serviceRequest->description }}</p>

                    @if($serviceRequest->customer_notes)
                    <hr>
                    <h6>Notas del Cliente:</h6>
                    <p>{{ $serviceRequest->customer_notes }}</p>
                    @endif

                    @if($serviceRequest->resolution_notes)
                    <hr>
                    <h6>Notas de Resolución:</h6>
                    <p class="text-success">{{ $serviceRequest->resolution_notes }}</p>
                    @endif

                    @if($serviceRequest->technical_notes)
                    <hr>
                    <h6>Notas Técnicas:</h6>
                    <p class="text-info">{{ $serviceRequest->technical_notes }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Acciones --}}
    @if($serviceRequest->status === 'pending' || $serviceRequest->status === 'in_progress')
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Acciones</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        {{-- Asignar Técnico --}}
                        @if(!$serviceRequest->assigned_to)
                        <div class="col-md-4">
                            <form action="{{ route('network.service-requests.assign', $serviceRequest) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label>Asignar a Técnico:</label>
                                    <select name="technician_id" class="form-control" required>
                                        <option value="">Seleccionar...</option>
                                        {{-- Aquí irían los técnicos disponibles --}}
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-user-plus"></i> Asignar
                                </button>
                            </form>
                        </div>
                        @endif

                        {{-- Completar --}}
                        <div class="col-md-4">
                            <form action="{{ route('network.service-requests.complete', $serviceRequest) }}" method="POST">
                                @csrf
                                <div class="form-group">
                                    <label>Notas de Resolución:</label>
                                    <textarea name="resolution_notes" class="form-control" rows="2" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-check"></i> Marcar como Completado
                                </button>
                            </form>
                        </div>

                        {{-- Cancelar --}}
                        <div class="col-md-4">
                            <form action="{{ route('network.service-requests.cancel', $serviceRequest) }}"
                                  method="POST"
                                  onsubmit="return confirm('¿Está seguro de cancelar esta solicitud?')">
                                @csrf
                                <div class="form-group">
                                    <label>Motivo de Cancelación:</label>
                                    <textarea name="reason" class="form-control" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger btn-block">
                                    <i class="fas fa-times"></i> Cancelar Solicitud
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Reintentar si falló --}}
    @if($serviceRequest->status === 'failed')
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow border-warning">
                <div class="card-body text-center">
                    <h5 class="text-warning">Esta solicitud falló</h5>
                    <p>Puede reintentarla o asignarla a un técnico</p>
                    <form action="{{ route('network.service-requests.retry', $serviceRequest) }}"
                          method="POST"
                          class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Reintentar Automáticamente
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
