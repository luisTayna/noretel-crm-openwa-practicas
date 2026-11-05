@extends('core::layouts.master')

@section('title', 'Gestión de Pagos')
@section('page-title', 'Gestión de Pagos')

@section('content')
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">Pagos Registrados</h6>
        <div class="dropdown no-arrow">
            <a class="btn btn-primary btn-sm" href="{{ route('billing.payments.create') }}">
                <i class="fas fa-plus fa-sm text-white-50"></i>
                Registrar Pago
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('billing.payments.index') }}" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" name="search" value="{{ $search }}"
                       placeholder="Referencia o # Recibo">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select class="form-control" name="status">
                    <option value="">Todos</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ $status == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Método</label>
                <select class="form-control" name="payment_method">
                    <option value="">Todos</option>
                    @foreach($paymentMethods ?? [] as $key => $label)
                        <option value="{{ $key }}" {{ $paymentMethod == $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="from" value="{{ $from }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="to" value="{{ $to }}">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">
                    <i class="fas fa-search"></i>
                </button>
                <a href="{{ route('billing.payments.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla de Pagos -->
    <div class="card-body">
        @if($payments->count() > 0)
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Recibo</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                        <tr>
                            <td>
                                <strong>{{ $payment->reference ?: 'N/A' }}</strong>
                                @if($payment->transaction_id)
                                    <br><small class="text-muted">ID: {{ $payment->transaction_id }}</small>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('billing.invoices.show', $payment->invoice_id) }}" class="text-decoration-none">
                                    {{ $payment->invoice->invoice_number }}
                                </a>
                            </td>
                            <td>
                                <div>
                                    <strong>{{ $payment->invoice->contract->customer->full_name }}</strong>
                                    @if($payment->invoice->contract->customer->company_name)
                                        <br><small class="text-muted">{{ $payment->invoice->contract->customer->company_name }}</small>
                                    @endif
                                </div>
                            </td>
                            <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                            <td>
                                <strong>${{ number_format($payment->amount, 2) }}</strong>
                            </td>
                            <td>
                                <span class="badge bg-info">{{ $payment->payment_method_name }}</span>
                            </td>
                            <td>
                                @switch($payment->status)
                                    @case('completed')
                                        <span class="badge bg-success">Completado</span>
                                        @break
                                    @case('pending')
                                        <span class="badge bg-warning">Pendiente</span>
                                        @break
                                    @case('failed')
                                        <span class="badge bg-danger">Fallido</span>
                                        @break
                                    @case('refunded')
                                        <span class="badge bg-secondary">Reembolsado</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">{{ ucfirst($payment->status) }}</span>
                                @endswitch
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('billing.payments.show', $payment->id) }}"
                                       class="btn btn-sm btn-outline-primary" title="Ver Detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @if($payment->status === 'pending')
                                        <a href="{{ route('billing.payments.edit', $payment->id) }}"
                                           class="btn btn-sm btn-outline-warning" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endif

                                    @if($payment->status === 'completed')
                                        <a href="{{ route('billing.payments.print', $payment->id) }}"
                                           class="btn btn-sm btn-outline-success" title="Imprimir Recibo" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                    <p class="small text-muted mb-0">
                        Mostrando {{ $payments->firstItem() }} a {{ $payments->lastItem() }}
                        de {{ $payments->total() }} resultados
                    </p>
                </div>
                <div>
                    {{ $payments->withQueryString()->links() }}
                </div>
            </div>
        @else
            <div class="text-center py-4">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No se encontraron pagos</h5>
                <p class="text-muted">No hay pagos registrados con los filtros seleccionados.</p>
                <a href="{{ route('billing.payments.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Registrar Primer Pago
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Resumen Estadístico -->
@if($payments->count() > 0)
<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Total Cobrado
                        </div>
                        <div class="h5 mb-0 font-weight-bold">
                            ${{ number_format($payments->where('status', 'completed')->sum('amount'), 2) }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-success text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Pagos Completados
                        </div>
                        <div class="h5 mb-0 font-weight-bold">
                            {{ $payments->where('status', 'completed')->count() }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-warning text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Pagos Pendientes
                        </div>
                        <div class="h5 mb-0 font-weight-bold">
                            {{ $payments->where('status', 'pending')->count() }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card bg-danger text-white shadow">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-uppercase mb-1">
                            Pagos Fallidos
                        </div>
                        <div class="h5 mb-0 font-weight-bold">
                            {{ $payments->where('status', 'failed')->count() }}
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
