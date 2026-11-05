@extends('core::layouts.app')

@section('title', 'Pagos Pendientes')

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">
        <i class="fas fa-file-invoice-dollar"></i> Pagos Pendientes de Aprobación
    </h1>

    <div class="card shadow">
        <div class="card-body">
            @if($payments->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Fecha</th>
                            <th>Recibo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments as $payment)
                        <tr>
                            <td>#{{ $payment->id }}</td>
                            <td>
                                <strong>{{ $payment->customer->user->name }}</strong><br>
                                <small class="text-muted">{{ $payment->customer->user->email }}</small>
                            </td>
                            <td><strong>S/{{ number_format($payment->amount, 2) }}</strong></td>
                            <td>
                                <span class="badge badge-info">{{ ucfirst($payment->payment_method) }}</span>
                            </td>
                            <td>{{ $payment->payment_date->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ Storage::url($payment->receipt_path) }}"
                                   target="_blank"
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-image"></i> Ver
                                </a>
                            </td>
                            <td>
                                <form action="{{ route('billing.payments.approve', $payment) }}"
                                      method="POST"
                                      class="d-inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-sm btn-success"
                                            onclick="return confirm('¿Aprobar este pago?')">
                                        <i class="fas fa-check"></i> Aprobar
                                    </button>
                                </form>

                                <form action="{{ route('billing.payments.reject', $payment) }}"
                                      method="POST"
                                      class="d-inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('¿Rechazar este pago?')">
                                        <i class="fas fa-times"></i> Rechazar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $payments->links() }}
            @else
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No hay pagos pendientes</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
