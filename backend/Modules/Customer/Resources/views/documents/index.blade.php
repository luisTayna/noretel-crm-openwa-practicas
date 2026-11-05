@extends('core::layouts.master')

@section('title', 'Documentos')
@section('page-title', 'Gestión de Documentos')

@section('content')
<style>
    .document-summary-card {
        border: 0;
        border-radius: 16px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        padding: 1rem;
        background: #fff;
    }
    .document-summary-card .summary-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #2563eb;
    }
    .document-summary-card .summary-value {
        font-size: 1.45rem;
        font-weight: 800;
        color: #111827;
    }
    .section-title {
        display: flex;
        align-items: center;
        gap: .65rem;
        margin-bottom: 1rem;
        font-weight: 800;
    }
</style>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-file-alt"></i> Documentos
        </div>
        @can('create-customers')
        <a href="{{ route('customer.documents.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Nuevo Documento
        </a>
        @endcan
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="document-summary-card">
                    <div class="d-flex align-items-center gap-3">
                        <span class="summary-icon"><i class="fas fa-paperclip"></i></span>
                        <div>
                            <div class="summary-value">{{ $summary['attachments'] }}</div>
                            <div class="text-muted">Adjuntos subidos</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="document-summary-card">
                    <div class="d-flex align-items-center gap-3">
                        <span class="summary-icon"><i class="fas fa-file-contract"></i></span>
                        <div>
                            <div class="summary-value">{{ $summary['contracts'] }}</div>
                            <div class="text-muted">Contratos del sistema</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="document-summary-card">
                    <div class="d-flex align-items-center gap-3">
                        <span class="summary-icon"><i class="fas fa-file-invoice-dollar"></i></span>
                        <div>
                            <div class="summary-value">{{ $summary['invoices'] }}</div>
                            <div class="text-muted">Facturas / boletas</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <form action="{{ route('customer.documents.index') }}" method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Cliente</label>
                    <select name="customer_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los clientes</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                {{ $customer->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Tipo de adjunto</label>
                    <select name="type_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los adjuntos</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" {{ request('type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Estado de adjunto</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los adjuntos</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pendiente</option>
                        <option value="verified" {{ request('status') == 'verified' ? 'selected' : '' }}>Verificado</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rechazado</option>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <label class="form-label fw-bold d-block">&nbsp;</label>
                    <a href="{{ route('customer.documents.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-sync me-1"></i> Reiniciar
                    </a>
                </div>
            </div>

            <div class="row g-3 mt-2">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Documento comercial</label>
                    <select name="commercial_type" class="form-select" onchange="this.form.submit()">
                        <option value="all" {{ $commercialType === 'all' ? 'selected' : '' }}>Contratos y facturas</option>
                        <option value="contracts" {{ $commercialType === 'contracts' ? 'selected' : '' }}>Solo contratos</option>
                        <option value="invoices" {{ $commercialType === 'invoices' ? 'selected' : '' }}>Solo facturas/boletas</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Estado comercial</label>
                    <select name="commercial_status" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los estados</option>
                        <option value="active" {{ $commercialStatus === 'active' ? 'selected' : '' }}>Contrato activo</option>
                        <option value="cancelled" {{ $commercialStatus === 'cancelled' ? 'selected' : '' }}>Contrato cancelado</option>
                        <option value="paid" {{ $commercialStatus === 'paid' ? 'selected' : '' }}>Factura pagada</option>
                        <option value="pending" {{ $commercialStatus === 'pending' ? 'selected' : '' }}>Factura pendiente</option>
                        <option value="overdue" {{ $commercialStatus === 'overdue' ? 'selected' : '' }}>Factura vencida</option>
                    </select>
                    <small class="text-muted">Aplica a contratos o facturas según el tipo elegido.</small>
                </div>
            </div>
        </form>

        <div class="alert alert-info d-flex align-items-start gap-2">
            <i class="fas fa-info-circle mt-1"></i>
            <div>
                <strong>Importante:</strong> esta sección separa los <strong>documentos adjuntos</strong> subidos manualmente
                de los <strong>documentos comerciales del sistema</strong> como contratos y facturas/boletas.
            </div>
        </div>

        <!-- Tabla de documentos adjuntos -->
        <h5 class="section-title"><i class="fas fa-paperclip text-primary"></i>Documentos adjuntos</h5>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $document)
                    <tr>
                        <td>{{ $document->id }}</td>
                        <td>{{ $document->name }}</td>
                        <td>
                            <a href="{{ route('customer.customers.show', $document->customer_id) }}">
                                {{ $document->customer->full_name }}
                            </a>
                        </td>
                        <td>{{ $document->documentType->name }}</td>
                        <td>{{ $document->upload_date->format('d/m/Y') }}</td>
                        <td>
                            @if($document->status == 'pending')
                                <span class="badge bg-warning">Pendiente</span>
                            @elseif($document->status == 'verified')
                                <span class="badge bg-success">Verificado</span>
                            @elseif($document->status == 'rejected')
                                <span class="badge bg-danger">Rechazado</span>
                            @endif
                        </td>
                        <td>
                            <div class="action-btn-group">
                                <a href="{{ route('customer.documents.show', $document->id) }}" class="action-btn" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <a href="{{ route('customer.documents.download', $document->id) }}" class="action-btn" title="Descargar">
                                    <i class="fas fa-download"></i>
                                </a>
                                
                                @can('edit-customers')
                                <a href="{{ route('customer.documents.edit', $document->id) }}" class="action-btn" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <button type="button" class="action-btn" title="Cambiar Estado" data-bs-toggle="modal" data-bs-target="#statusModal{{ $document->id }}">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                                @endcan
                                
                                @can('delete-customers')
                                <button type="button" class="action-btn" title="Eliminar" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $document->id }}">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @endcan
                            </div>
                            
                            <!-- Modal para cambiar estado -->
                            @can('edit-customers')
                            <div class="modal fade" id="statusModal{{ $document->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Cambiar Estado del Documento</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="{{ route('customer.documents.change-status', $document->id) }}" method="POST">
                                            @csrf
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="status" class="form-label">Estado</label>
                                                    <select name="status" id="status" class="form-select" required>
                                                        <option value="pending" {{ $document->status == 'pending' ? 'selected' : '' }}>Pendiente</option>
                                                        <option value="verified" {{ $document->status == 'verified' ? 'selected' : '' }}>Verificado</option>
                                                        <option value="rejected" {{ $document->status == 'rejected' ? 'selected' : '' }}>Rechazado</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endcan
                            
                            <!-- Modal para eliminar documento -->
                            @can('delete-customers')
                            <div class="modal fade" id="deleteModal{{ $document->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Eliminar Documento</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>¿Está seguro de que desea eliminar el documento <strong>{{ $document->name }}</strong>?</p>
                                            <p class="text-danger"><strong>Atención:</strong> Esta acción no se puede deshacer.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                            <form action="{{ route('customer.documents.destroy', $document->id) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">Eliminar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center">No se encontraron documentos con los criterios de búsqueda</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="d-flex justify-content-center mt-4">
            {{ $documents->appends(request()->query())->links('pagination::bootstrap-4') }}
        </div>

        <hr class="my-4">

        @if($commercialType !== 'invoices')
        <h5 class="section-title"><i class="fas fa-file-contract text-success"></i>Contratos del sistema</h5>
        <div class="table-responsive mb-4">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Plan</th>
                        <th>Inicio</th>
                        <th>Estado</th>
                        <th>Precio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commercialContracts as $contract)
                        <tr>
                            <td>{{ $contract->id }}</td>
                            <td>
                                <a href="{{ route('customer.customers.show', $contract->customer_id) }}">
                                    {{ $contract->customer?->full_name ?: 'Cliente no disponible' }}
                                </a>
                            </td>
                            <td>{{ $contract->plan?->name ?: 'Sin plan' }}</td>
                            <td>{{ $contract->start_date ? $contract->start_date->format('d/m/Y') : '-' }}</td>
                            <td>
                                <span class="badge bg-{{ $contract->status === 'active' ? 'success' : ($contract->status === 'cancelled' ? 'danger' : 'secondary') }}">
                                    {{ ucfirst($contract->status) }}
                                </span>
                            </td>
                            <td>S/ {{ number_format((float) $contract->final_price, 2) }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('contract.contracts.show', $contract->id) }}" class="action-btn" title="Ver contrato">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('contract.contracts.pdf', $contract->id) }}" class="action-btn" title="PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No se encontraron contratos para el filtro seleccionado</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-2">
            {{ $commercialContracts->appends(request()->query())->links('pagination::bootstrap-4') }}
        </div>
        @endif

        @if($commercialType !== 'contracts')
        <h5 class="section-title mt-4"><i class="fas fa-file-invoice-dollar text-warning"></i>Facturas / Boletas del sistema</h5>
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Emisión</th>
                        <th>Vencimiento</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($commercialInvoices as $invoice)
                        <tr>
                            <td>{{ $invoice->id }}</td>
                            <td>{{ $invoice->invoice_number ?: '-' }}</td>
                            <td>
                                @if($invoice->contract?->customer)
                                    <a href="{{ route('customer.customers.show', $invoice->contract->customer_id) }}">
                                        {{ $invoice->contract->customer->full_name }}
                                    </a>
                                @else
                                    Cliente no disponible
                                @endif
                            </td>
                            <td>{{ $invoice->issue_date ? $invoice->issue_date->format('d/m/Y') : '-' }}</td>
                            <td>{{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}</td>
                            <td>
                                <span class="badge bg-{{ $invoice->status === 'paid' ? 'success' : ($invoice->status === 'overdue' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td>S/ {{ number_format((float) $invoice->total_amount, 2) }}</td>
                            <td>
                                <div class="action-btn-group">
                                    <a href="{{ route('billing.invoices.show', $invoice->id) }}" class="action-btn" title="Ver factura">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('billing.invoices.print', $invoice->id) }}" class="action-btn" title="Imprimir">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">No se encontraron facturas/boletas para el filtro seleccionado</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-4">
            {{ $commercialInvoices->appends(request()->query())->links('pagination::bootstrap-4') }}
        </div>
        @endif
    </div>
</div>
@endsection
