@extends('core::layouts.master')

@section('title', 'Contratos')
@section('page-title', 'Gestión de Contratos')

@section('content')
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3 mb-md-0">
        <div class="card h-100 bg-primary text-white">
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <h5 class="card-title">Contratos Activos</h5>
                <h2 class="mb-0">{{ $contractsByStatus['active'] ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3 mb-md-0">
        <div class="card h-100 bg-warning text-dark">
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <h5 class="card-title">Por Vencer</h5>
                <h2 class="mb-0">{{ $nearExpirationCount }}</h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3 mb-md-0">
        <div class="card h-100 bg-danger text-white">
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <h5 class="card-title">Vencidos</h5>
                <h2 class="mb-0">{{ $expiredCount }}</h2>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100 bg-info text-white">
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <h5 class="card-title">Pendientes</h5>
                <h2 class="mb-0">{{ $contractsByStatus['pending_installation'] ?? 0 }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0">Listado de Contratos</h5>
        <div>
            <button type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="collapse" href="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                <i class="fa fa-filter"></i> Filtros
            </button>
            @if(auth()->user()->canCreateInModule('contracts'))
            <a href="{{ route('contract.contracts.create') }}" class="btn btn-primary btn-sm">
                <i class="fa fa-plus"></i> Nuevo Contrato
            </a>
            @endif
        </div>
    </div>
    
    <div class="collapse" id="filterCollapse">
        <div class="card-body border-bottom">
            <form action="{{ route('contract.contracts.index') }}" method="GET" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Buscar cliente</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" placeholder="Nombre o documento" name="search" value="{{ request('search') }}">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fa fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Estado</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Activo</option>
                            <option value="pending_installation" {{ request('status') == 'pending_installation' ? 'selected' : '' }}>Pendiente de instalación</option>
                            <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Vencido</option>
                            <option value="renewed" {{ request('status') == 'renewed' ? 'selected' : '' }}>Renovado</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                            <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>Suspendido</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="customer_id" class="form-label">Cliente</label>
                        @if(isset($customers))
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">Todos los clientes</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}" {{ request('customer_id') == $customer->id ? 'selected' : '' }}>
                                    {{ $customer->first_name }} {{ $customer->last_name }} ({{ $customer->identity_document }})
                                </option>
                            @endforeach
                        </select>
                        @endif
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Filtrar
                            </button>
                            <a href="{{ route('contract.contracts.index') }}" class="btn btn-outline-secondary">
                                <i class="fa fa-refresh"></i> Reiniciar
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="contractsTable">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Plan</th>
                        <th>Fechas</th>
                        <th>Estado</th>
                        <th>Precio</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contracts as $contract)
                    <tr>
                        <td>{{ $contract->id }}</td>
                        <td>
                            <a href="{{ route('customer.customers.show', $contract->customer_id) }}" class="text-decoration-none">
                                {{ $contract->customer->first_name }} {{ $contract->customer->last_name }}
                            </a>
                        </td>
                        <td>{{ $contract->plan->name }}</td>
                        <td>
                            <small>
                                <strong>Inicio:</strong> {{ $contract->start_date->format('d/m/Y') }}<br>
                                <strong>Fin:</strong> {{ $contract->end_date ? $contract->end_date->format('d/m/Y') : 'Indefinido' }}
                            </small>
                        </td>
                        <td>
                            @switch($contract->status)
                                @case('active')
                                    <span class="badge bg-success">Activo</span>
                                    @break
                                @case('pending_installation')
                                    <span class="badge bg-warning text-dark">Pendiente</span>
                                    @break
                                @case('expired')
                                    <span class="badge bg-danger">Vencido</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge bg-secondary">Cancelado</span>
                                    @break
                                @case('renewed')
                                    <span class="badge bg-info">Renovado</span>
                                    @break
                                @case('suspended')
                                    <span class="badge bg-dark">Suspendido</span>
                                    @break
                                @default
                                    <span class="badge bg-dark">{{ $contract->status }}</span>
                            @endswitch
                        </td>
                        <td>{{ number_format($contract->final_price, 2) }}</td>
                        <td>
                            <div class="btn-group btn-group-sm float-end">
                                <a href="{{ route('contract.contracts.show', $contract->id) }}" class="btn btn-outline-info" title="Ver detalles">
                                    <i class="fa fa-eye"></i>
                                </a>

                                @if(auth()->user()->canEditInModule('contracts'))
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa fa-cog"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('contract.contracts.edit', $contract->id) }}">
                                                <i class="fa fa-edit me-2"></i> Editar
                                            </a>
                                        </li>
                                        
                                        @if($contract->status == 'pending_installation')
                                        <li>
                                            <a class="dropdown-item" href="{{ route('contract.installations.create', ['contract_id' => $contract->id]) }}">
                                                <i class="fa fa-tools me-2"></i> Programar Instalación
                                            </a>
                                        </li>
                                        @endif
                                        
                                        @if($contract->canBeRenewed())
                                        <li>
                                            <a class="dropdown-item" href="{{ route('contract.contracts.renew-form', $contract->id) }}">
                                                <i class="fa fa-sync-alt me-2"></i> Renovar
                                            </a>
                                        </li>
                                        @endif
                                        
                                        @if($contract->canBeCancelled())
                                        <li>
                                            <button type="button" class="dropdown-item text-warning" 
                                                    onclick="confirmCancel({{ $contract->id }}, '{{ $contract->customer->first_name }} {{ $contract->customer->last_name }}')">
                                                <i class="fa fa-ban me-2"></i> Anular
                                            </button>
                                        </li>
                                        @endif
                                        
                                        @if(auth()->user()->canDeleteInModule('contracts'))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger"
                                                    onclick="confirmDelete({{ $contract->id }}, '{{ $contract->customer->first_name }} {{ $contract->customer->last_name }}')">
                                                <i class="fa fa-trash me-2"></i> Eliminar
                                            </button>
                                        </li>
                                        @endif
                                    </ul>
                                </div>
                                @endif
                            </div>

                            <!-- Los modales se han eliminado y reemplazado por alertas JavaScript -->


                            <!-- Los modales se han eliminado y reemplazado por alertas JavaScript -->

                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="text-muted">
                                <i class="fa fa-search fa-2x mb-3 opacity-50"></i>
                                <p>No se encontraron contratos con los criterios de búsqueda.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <div class="d-flex justify-content-center mt-4">
            {{ $contracts->links('pagination::bootstrap-4') }}
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-submit on select change
        document.querySelectorAll('#status, #customer_id').forEach(function(select) {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });
    });
    
    // Función para confirmar cancelación de contrato
    function confirmCancel(contractId, customerName) {
        if (confirm("¿Estás seguro de que deseas anular el contrato de " + customerName + "?")) {
            let reason = prompt("Por favor, ingrese el motivo de la anulación:");
            
            if (reason) {
                // Crear un formulario dinámico
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = @json(route('contract.contracts.cancel', ['id' => '__ID__'])).replace('__ID__', contractId);
                
                // Campo CSRF
                let csrfField = document.createElement('input');
                csrfField.type = 'hidden';
                csrfField.name = '_token';
                csrfField.value = '{{ csrf_token() }}';
                
                // Campo de razón
                let reasonField = document.createElement('input');
                reasonField.type = 'hidden';
                reasonField.name = 'reason';
                reasonField.value = reason;
                
                // Agregar campos al formulario
                form.appendChild(csrfField);
                form.appendChild(reasonField);
                
                // Agregar formulario al documento y enviarlo
                document.body.appendChild(form);
                form.submit();
            }
        }
    }
    
    // Función para confirmar eliminación de contrato
    function confirmDelete(contractId, customerName) {
        if (confirm("¿Estás seguro de que deseas eliminar el contrato de " + customerName + "?\n\nATENCIÓN: Esta acción no se puede deshacer.")) {
            // Crear un formulario dinámico
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = @json(route('contract.contracts.destroy', ['id' => '__ID__'])).replace('__ID__', contractId);
            
            // Campo CSRF
            let csrfField = document.createElement('input');
            csrfField.type = 'hidden';
            csrfField.name = '_token';
            csrfField.value = '{{ csrf_token() }}';
            
            // Campo DELETE method
            let methodField = document.createElement('input');
            methodField.type = 'hidden';
            methodField.name = '_method';
            methodField.value = 'DELETE';
            
            // Agregar campos al formulario
            form.appendChild(csrfField);
            form.appendChild(methodField);
            
            // Agregar formulario al documento y enviarlo
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
@endpush
@endsection
