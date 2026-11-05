@extends('core::layouts.master')

@section('title', 'Detalles del Contrato')
@section('page-title', 'Detalle del Contrato #' . $contract->id)

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-contract"></i> Información del Contrato</h5>
                <div>
                    <a href="{{ route('operations.customers.flow', $contract->customer_id) }}" class="btn btn-sm btn-info me-2 text-white" title="Ver flujo operativo" style="padding: 0.4rem 1rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fas fa-route"></i> Flujo Operativo
                    </a>

                    @if(auth()->user()->canEditInModule('contracts'))
                    <a href="{{ route('contract.contracts.edit', $contract->id) }}" class="btn btn-primary btn-sm">
                        <i class="fa fa-edit"></i> Editar
                    </a>
                    @endif

                    @if($contract->canBeRenewed() && auth()->user()->canEditInModule('contracts'))
                    <a href="{{ route('contract.contracts.renew-form', $contract->id) }}" class="btn btn-success btn-sm">
                        <i class="fa fa-sync-alt"></i> Renovar
                    </a>
                    @endif

                    @if($contract->canBeCancelled() && auth()->user()->canEditInModule('contracts'))
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelContractModal">
                        <i class="fa fa-ban"></i> Anular
                    </button>
                    @endif

                    @if(auth()->user()->canDeleteInModule('contracts'))
                    <form action="{{ route('contract.contracts.destroy', $contract->id) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este contrato? Esta acción no se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="fa fa-trash"></i> Eliminar
                        </button>
                    </form>
                    @endif

                    <a href="{{ route('contract.contracts.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información General</h6>
                        <table class="table table-striped table-bordered">
                            <tr>
                                <th width="30%">Estado</th>
                                <td>
                                    @if($contract->status == 'active')
                                        <span class="badge bg-success">Activo</span>
                                    @elseif($contract->status == 'pending_installation')
                                        <span class="badge bg-warning">Pendiente de instalación</span>
                                    @elseif($contract->status == 'expired')
                                        <span class="badge bg-danger">Vencido</span>
                                    @elseif($contract->status == 'cancelled')
                                        <span class="badge bg-secondary">Cancelado</span>
                                    @elseif($contract->status == 'renewed')
                                        <span class="badge bg-info">Renovado</span>
                                    @else
                                        <span class="badge bg-dark">{{ $contract->status }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Fecha de Inicio</th>
                                <td>{{ $contract->start_date->format('d/m/Y') }}</td>
                            </tr>
                            <tr>
                                <th>Fecha de Fin</th>
                                <td>{{ $contract->end_date ? $contract->end_date->format('d/m/Y') : 'Indefinido' }}</td>
                            </tr>
                            <tr>
                                <th>Precio Mensual</th>
                                <td>S/ {{ number_format($contract->final_price, 2) }}</td>
                            </tr>
                            <tr>
                                <th>IP Asignada</th>
                                <td>{{ $contract->assigned_ip ?: 'No asignada' }}</td>
                            </tr>
                            <tr>
                                <th>VLAN</th>
                                <td>{{ $contract->vlan ?: 'No asignada' }}</td>
                            </tr>
                            <tr>
                                <th>Fecha de Creación</th>
                                <td>{{ $contract->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th>Última Actualización</th>
                                <td>{{ $contract->updated_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Información del Cliente</h6>
                        <table class="table table-striped table-bordered">
                            <tr>
                                <th width="30%">Cliente</th>
                                <td>
                                    <a href="{{ route('customer.customers.show', $contract->customer_id) }}">
                                        {{ $contract->customer->first_name }} {{ $contract->customer->last_name }}
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th>Documento</th>
                                <td>{{ $contract->customer->identity_document }}</td>
                            </tr>
                            <tr>
                                <th>Teléfono</th>
                                <td>{{ $contract->customer->phone }}</td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td>{{ $contract->customer->email }}</td>
                            </tr>
                            <tr>
                                <th>Dirección</th>
                                <td>
                                    @if($contract->customer->addresses->count() > 0)
                                        @php $address = $contract->customer->addresses->where('is_primary', true)->first() ?: $contract->customer->addresses->first(); @endphp
                                        {{ $address->street }} {{ $address->number }}, {{ $address->city }}, {{ $address->state }}
                                    @else
                                        No registrada
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Segmento</th>
                                <td>{{ $contract->customer->segment ?: 'No categorizado' }}</td>
                            </tr>
                            <tr>
                                <th>Puntaje de Crédito</th>
                                <td>{{ $contract->customer->credit_score ?: 'No evaluado' }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6>Información del Plan</h6>
                        <table class="table table-striped table-bordered">
                            <tr>
                                <th width="30%">Plan</th>
                                <td>{{ $contract->plan->name }}</td>
                            </tr>
                            <tr>
                                <th>Servicio</th>
                                <td>{{ $contract->plan->service->name }}</td>
                            </tr>
                            <tr>
                                <th>Tecnología</th>
                                <td>{{ $contract->plan->service->technology_label }}</td>
                            </tr>
                            <tr>
                                <th>Velocidad de Descarga</th>
                                <td>{{ $contract->plan->download_speed }} Mbps</td>
                            </tr>
                            <tr>
                                <th>Velocidad de Subida</th>
                                <td>{{ $contract->plan->upload_speed }} Mbps</td>
                            </tr>
                            <tr>
                                <th>Precio Base</th>
                                <td>S/ {{ number_format($contract->plan->price, 2) }}</td>
                            </tr>
                            <tr>
                                <th>Características</th>
                                <td>{{ is_array($contract->plan->features) ? implode(', ', $contract->plan->features) : $contract->plan->features }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Información Técnica</h6>
                        <table class="table table-striped table-bordered">
                            {{-- <tr>
                                <th width="30%">Nodo</th>
                                <td>{{ $contract->node->name }}</td>
                            </tr> --}}
                            {{-- <tr>
                                <th>Ubicación del Nodo</th>
                                <td>{{ $contract->node->location }}</td>
                            </tr> --}}
                            {{-- <tr>
                                <th>Capacidad</th>
                                <td>{{ $contract->node->used_capacity }}/{{ $contract->node->total_capacity }}</td>
                            </tr> --}}
                            {{-- <tr>
                                <th>Tipo de Conexión</th>
                                <td>{{ $contract->node->connection_type }}</td>
                            </tr> --}}
                            <tr>
                                <th>SLA</th>
                                <td>
                                    @if($contract->sla)
                                        <span class="badge bg-{{ $contract->sla->level_color }}">{{ $contract->sla->service_level }}</span>
                                        {{ $contract->sla->name }}
                                    @else
                                        No definido
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Tiempo de Respuesta</th>
                                <td>
                                    @if($contract->sla)
                                        {{ $contract->sla->response_time }} horas
                                    @else
                                        No definido
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Tiempo de Resolución</th>
                                <td>
                                    @if($contract->sla)
                                        {{ $contract->sla->resolution_time }} horas
                                    @else
                                        No definido
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                @if($contract->contractedServices->count() > 0)
                <div class="row mt-4">
                    <div class="col-12">
                        <h6>Servicios Adicionales</h6>
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Servicio</th>
                                    <th>Descripción</th>
                                    <th>Configuración</th>
                                    <th>Precio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($contract->contractedServices as $service)
                                <tr>
                                    <td>{{ $service->additionalService->name }}</td>
                                    <td>{{ $service->additionalService->description }}</td>
                                    <td>
                                        @if($service->configuration)
                                            <ul class="mb-0">
                                                @foreach($service->configuration as $key => $value)
                                                    <li><strong>{{ $key }}:</strong> {{ $value }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-muted">Sin configuración</span>
                                        @endif
                                    </td>
                                    <td>S/ {{ number_format($service->price, 2) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Sección de Instalaciones -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-tools"></i> Instalaciones</h5>
                @if($contract->status == 'pending_installation' && !$contract->hasActiveInstallation() && auth()->user()->canCreateInModule('installations'))
                <a href="{{ route('contract.installations.create', ['contract_id' => $contract->id]) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Programar Instalación
                </a>
                @endif
            </div>
            <div class="card-body">
                @if($installations->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Técnico</th>
                                <th>Fecha Programada</th>
                                <th>Fecha Completada</th>
                                <th>Ruta</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($installations as $installation)
                            <tr>
                                <td>{{ $installation->id }}</td>
                                <td>{{ $installation->technician ? $installation->technician->first_name . ' ' . $installation->technician->last_name : 'No asignado' }}</td>
                                <td>{{ $installation->scheduled_date->format('d/m/Y H:i') }}</td>
                                <td>{{ $installation->completed_date ? $installation->completed_date->format('d/m/Y H:i') : 'Pendiente' }}</td>
                                <td>{{ $installation->route ? $installation->route->zone . ' (' . $installation->route->date->format('d/m/Y') . ')' : 'Sin ruta' }}</td>
                                <td>
                                    <span class="badge bg-{{ $installation->status_color }}">
                                        @if($installation->status == 'scheduled')
                                            Programada
                                        @elseif($installation->status == 'in_progress')
                                            En progreso
                                        @elseif($installation->status == 'completed')
                                            Completada
                                        @elseif($installation->status == 'cancelled')
                                            Cancelada
                                        @else
                                            {{ $installation->status }}
                                        @endif
                                    </span>
                                    @if($installation->is_late)
                                        <span class="badge bg-danger">Atrasada</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('contract.installations.show', $installation->id) }}" class="btn btn-sm btn-info">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @if($installation->status != 'completed' && $installation->status != 'cancelled' && auth()->user()->canEditInModule('installations'))
                                    <a href="{{ route('contract.installations.edit', $installation->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    @endif
                                    @if($installation->status == 'scheduled' || $installation->status == 'in_progress')
                                    <a href="{{ route('contract.installations.complete-form', $installation->id) }}" class="btn btn-sm btn-success">
                                        <i class="fa fa-check"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info">
                    No hay instalaciones registradas para este contrato.
                    @if($contract->status == 'pending_installation' && auth()->user()->canCreateInModule('installations'))
                    <a href="{{ route('contract.installations.create', ['contract_id' => $contract->id]) }}" class="alert-link">Programar instalación</a>.
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Sección de Facturas -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-invoice-dollar"></i> Facturas</h5>
                @if($contract->status == 'active' && auth()->user()->canCreateInModule('invoices'))
                <a href="{{ route('billing.invoices.create', ['contract_id' => $contract->id]) }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> Nueva Factura
                </a>
                @endif
            </div>
            <div class="card-body">
                @if($invoices && $invoices->count() > 0)
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Número</th>
                                <th>Fecha Emisión</th>
                                <th>Fecha Vencimiento</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->id }}</td>
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->issue_date->format('d/m/Y') }}</td>
                                <td>{{ $invoice->due_date->format('d/m/Y') }}</td>
                                <td>S/ {{ number_format($invoice->amount, 2) }}</td>
                                <td>
                                    @if($invoice->status == 'paid')
                                        <span class="badge bg-success">Pagada</span>
                                    @elseif($invoice->status == 'pending')
                                        <span class="badge bg-warning">Pendiente</span>
                                    @elseif($invoice->status == 'overdue')
                                        <span class="badge bg-danger">Vencida</span>
                                    @elseif($invoice->status == 'cancelled')
                                        <span class="badge bg-secondary">Cancelada</span>
                                    @else
                                        <span class="badge bg-dark">{{ $invoice->status }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="btn btn-sm btn-info">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    @if($invoice->status == 'pending' || $invoice->status == 'overdue')
                                    <a href="{{ route('billing.payments.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-sm btn-success">
                                        <i class="fa fa-money-bill"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="alert alert-info">
                    No hay facturas registradas para este contrato.
                    @if($contract->status == 'active' && auth()->user()->canCreateInModule('invoices'))
                    <a href="{{ route('billing.invoices.create', ['contract_id' => $contract->id]) }}" class="alert-link">Crear factura</a>.
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal para anular contrato -->
<div class="modal fade" id="cancelContractModal" tabindex="-1" aria-labelledby="cancelContractModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelContractModalLabel">Anular Contrato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('contract.contracts.cancel', $contract->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i> Estás a punto de anular el contrato #{{ $contract->id }} del cliente <strong>{{ $contract->customer->first_name }} {{ $contract->customer->last_name }}</strong>.
                    </div>
                    <p>Esta acción cambiará el estado del contrato a <span class="badge bg-secondary">Cancelado</span>. Considera que:</p>
                    <ul>
                        <li>El cliente no podrá seguir utilizando el servicio.</li>
                        <li>No se generarán nuevas facturas para este contrato.</li>
                        <li>Esta acción no elimina el contrato del sistema.</li>
                    </ul>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motivo de la anulación <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        <div class="form-text">Por favor, detalla el motivo de la anulación de este contrato.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Anulación</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

