@extends('operations::layouts.master')

@section('title', 'Órdenes de instalación')
@section('page-title', 'Operaciones · Órdenes de instalación')

@push('styles')
<style>
    /* Noretel Next-Gen Styles for Operations */
    .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        border-radius: 20px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.03);
    }
    
    .glass-card .card-header {
        background: transparent;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        font-weight: 700;
        color: #1e293b;
        padding: 1.25rem 1.5rem;
    }

    .form-control-glass, .form-select-glass {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 12px;
        padding: 0.6rem 1rem;
        transition: all 0.2s;
    }
    .form-control-glass:focus, .form-select-glass:focus {
        background: #fff;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-glass-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }
    .btn-glass-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 15px rgba(37, 99, 235, 0.3);
        color: #fff;
    }

    .table-glass {
        --bs-table-bg: transparent;
        margin-bottom: 0;
    }
    .table-glass th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        font-weight: 600;
        border-bottom: 2px solid rgba(0,0,0,0.03);
        background: transparent;
        padding: 1rem 1.5rem;
    }
    .table-glass td {
        vertical-align: middle;
        border-bottom: 1px solid rgba(0,0,0,0.03);
        background: transparent;
        padding: 1rem 1.5rem;
    }

    /* Soft Badges */
    .badge-soft {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.3px;
    }
    .badge-soft-primary { background: #eff6ff; color: #2563eb; }
    .badge-soft-success { background: #ecfdf5; color: #059669; }
    .badge-soft-warning { background: #fffbeb; color: #d97706; }
    .badge-soft-danger { background: #fef2f2; color: #dc2626; }
    .badge-soft-info { background: #ecfeff; color: #0891b2; }
    .badge-soft-secondary { background: #f8fafc; color: #64748b; }
    
    .action-icon-btn {
        width: 32px; height: 32px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        text-decoration: none;
    }
    .action-icon-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold" style="color: #0f172a;">Órdenes de instalación</h1>
            <p class="text-muted mb-0">Flujo operativo cliente → orden de instalación → instalación → contrato día 30 → recibo mensual → pago.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('operations.onboarding-orders.create') }}" class="btn btn-glass-primary px-4">
                <i class="fas fa-clipboard-list me-2"></i> Nueva orden de instalación
            </a>
            <a href="{{ route('customer.customers.create') }}" class="btn btn-light border px-4" style="border-radius: 12px; font-weight: 600;">
                <i class="fas fa-user-plus me-2"></i> Nuevo cliente
            </a>
        </div>
    </div>

    <!-- Buscador -->
    <div class="card glass-card mb-4 border-0">
        <div class="card-body p-4">
            <form method="GET" action="{{ route('operations.onboarding') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="search" class="form-label text-muted fw-semibold small">Buscar cliente</label>
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-0 position-absolute" style="z-index: 5; top: 5px; color: #94a3b8;"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" id="search" class="form-control form-control-glass ps-5" value="{{ $search }}" placeholder="Nombre, documento, correo o teléfono">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label text-muted fw-semibold small">Estado operativo</label>
                    <select name="status" id="status" class="form-select form-select-glass">
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-glass-primary flex-fill">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                    <a href="{{ route('operations.onboarding') }}" class="btn btn-light" style="border-radius: 12px; font-weight: 600; color: #64748b;">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    @if(isset($contractQueue) && $contractQueue->isNotEmpty())
        <div class="card glass-card border-0 mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clock me-2 text-primary"></i> Contratos pendientes para el día 30</span>
                <span class="badge bg-white text-primary border shadow-sm px-3 py-2 rounded-pill">{{ $contractQueue->count() }} en cola</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-glass align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Solicitud</th>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Fecha contrato</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($contractQueue as $order)
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $order->order_number }}</div>
                                        <div class="small text-muted">{{ $order->status_label }}</div>
                                    </td>
                                    <td>
                                        <div class="fw-medium text-dark">{{ optional($order->customer)->full_name }}</div>
                                        <div class="small text-muted">
                                            {{ optional(optional($order->installation)->technician)->full_name ?: (optional($order->technician)->full_name ?: 'Técnico se asigna al agendar') }}
                                        </div>
                                    </td>
                                    <td>{{ optional($order->plan)->name ?: 'Sin plan' }}</td>
                                    <td>
                                        <span class="badge-soft {{ $order->expected_contract_date && $order->expected_contract_date->isFuture() ? 'badge-soft-info' : 'badge-soft-primary' }}">
                                            {{ optional($order->expected_contract_date)->format('d/m/Y') ?: 'Sin fecha' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($order->expected_contract_date && $order->expected_contract_date->isFuture())
                                            <a href="{{ route('operations.customers.flow', $order->customer_id) }}" class="btn btn-sm btn-light border" style="border-radius:10px;">
                                                Ver flujo
                                            </a>
                                        @else
                                            <form method="POST" action="{{ route('operations.onboarding-orders.generate-contract', $order->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary" style="border-radius:10px;">
                                                    <i class="fas fa-file-contract me-1"></i> Generar contrato
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Tabla -->
    <div class="card glass-card border-0">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-stream me-2 text-primary"></i> Clientes en flujo</span>
            <span class="badge bg-white text-primary border shadow-sm px-3 py-2 rounded-pill">{{ $customers->total() }} registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-glass align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Estado</th>
                                <th>Orden / Plan</th>
                            <th>Contrato / Recibo</th>
                            <th>Siguiente acción</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customers as $customer)
                            @php
                                $flow = $flows[$customer->id];
                                // Map old bootstrap colors to soft colors
                                $softClass = in_array($flow['status_class'], ['primary', 'success', 'warning', 'danger', 'secondary', 'info']) 
                                    ? 'badge-soft-'.$flow['status_class'] 
                                    : 'badge-soft-secondary';
                                $order = $flow['onboarding_order'] ?? null;
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle text-white d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #0ea5e9, #3b82f6); font-weight: 600;">
                                            {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark">{{ $customer->full_name }}</div>
                                            <div class="small text-muted">{{ $customer->identity_document ?: 'Sin documento' }} · {{ $customer->phone ?: 'Sin teléfono' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-soft {{ $softClass }}"><i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> {{ $flow['status_label'] }}</span>
                                </td>
                                <td>
                                    @if($order)
                                        <div class="fw-medium text-dark">{{ $order->order_number }}</div>
                                        <div class="small text-muted"><i class="fas fa-wifi me-1"></i> {{ optional($order->plan)->name ?: 'Sin plan' }}</div>
                                    @elseif($flow['contract'])
                                        <div class="fw-medium text-dark">Contrato #{{ $flow['contract']->id }}</div>
                                        <div class="small text-muted"><i class="fas fa-wifi me-1"></i> {{ optional($flow['contract']->plan)->name ?: 'Sin plan' }}</div>
                                    @else
                                        <span class="text-muted small"><i class="fas fa-clipboard-list me-1"></i> Sin orden</span>
                                    @endif
                                </td>
                                <td>
                                    @if($flow['contract'])
                                        <div class="fw-medium text-dark">Contrato #{{ $flow['contract']->id }}</div>
                                        <div class="small text-muted">{{ ucfirst($flow['contract']->status) }}</div>
                                    @endif
                                    @if($flow['invoice'])
                                        <div class="fw-medium text-dark">{{ $flow['invoice']->invoice_number }}</div>
                                        <div class="small text-muted">{{ ucfirst($flow['invoice']->status) }} · <strong>S/ {{ number_format((float) $flow['invoice']->pending_amount, 2) }}</strong></div>
                                    @elseif(!$flow['contract'])
                                        <span class="text-muted small"><i class="fas fa-receipt me-1"></i> Sin contrato/recibo</span>
                                    @else
                                        <span class="text-muted small"><i class="fas fa-receipt me-1"></i> Sin recibo</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-{{ $flow['status_class'] }} fw-medium" style="font-size: 0.9rem;">
                                        <i class="fas {{ $flow['next_action']['icon'] }} me-1"></i>
                                        {{ $flow['next_action']['title'] }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('operations.customers.flow', $customer->id) }}" class="action-icon-btn shadow-sm" data-bs-toggle="tooltip" title="Ver flujo">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted mb-2"><i class="fas fa-search fa-2x opacity-50"></i></div>
                                    <h6 class="text-muted">No hay clientes que coincidan con los filtros.</h6>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($customers->hasPages())
            <div class="card-footer bg-transparent border-0 pt-3 pb-4">
                {{ $customers->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endsection
