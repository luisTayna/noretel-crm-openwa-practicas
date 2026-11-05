@extends('core::layouts.master')

@section('title', 'Yape por validar')
@section('page-title', 'Yape por validar')

@section('content')
@php
    $badgeClasses = [
        \Modules\Billing\Entities\YapePaymentIntent::STATUS_WAITING_CLIENT => 'bg-warning text-dark',
        \Modules\Billing\Entities\YapePaymentIntent::STATUS_WAITING_RECEIVER => 'bg-info text-dark',
        \Modules\Billing\Entities\YapePaymentIntent::STATUS_VALIDATED => 'bg-success',
        \Modules\Billing\Entities\YapePaymentIntent::STATUS_OBSERVED => 'bg-danger',
        \Modules\Billing\Entities\YapePaymentIntent::STATUS_CANCELLED => 'bg-secondary',
    ];
@endphp

<div class="row mb-4">
    @foreach($summary as $summaryStatus => $count)
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold">{{ $statuses[$summaryStatus] ?? $summaryStatus }}</div>
                    <div class="h3 mb-0">{{ $count }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-mobile-alt me-2"></i> Validaciones Yape
        </h6>
        <a href="{{ route('billing.payments.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Nuevo cobro
        </a>
    </div>

    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('billing.yape.index') }}" class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Buscar</label>
                <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Intent, operación, cliente, WhatsApp o recibo">
            </div>
            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">
                    <i class="fas fa-search"></i>
                </button>
                <a href="{{ route('billing.yape.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Aquí NO se sube captura manual. El intent se valida cruzando la captura recibida por WhatsApp/OpenWA con la notificación del Yape receptor en Android.
        </div>

        @if(isset($manualReviewEvidences) && $manualReviewEvidences->count())
            <div class="alert alert-warning">
                <div class="fw-bold mb-2">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Capturas Yape en revisión manual
                </div>
                <div class="small mb-3">
                    Estas capturas no se pagaron automáticamente porque el DNI enviado no existe o superó los intentos permitidos.
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 bg-white">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>WhatsApp</th>
                                <th>Monto</th>
                                <th>Operación</th>
                                <th>Cód. seguridad</th>
                                <th>Último DNI</th>
                                <th>Intentos</th>
                                <th>Creado</th>
                                <th>Captura</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($manualReviewEvidences as $evidence)
                                @php($dniResolution = $evidence->payload['dni_resolution'] ?? [])
                                <tr>
                                    <td>#{{ $evidence->id }}</td>
                                    <td>{{ $evidence->whatsapp_phone ?: $evidence->source_chat_name ?: '-' }}</td>
                                    <td>S/ {{ number_format((float) $evidence->parsed_amount, 2) }}</td>
                                    <td>{{ $evidence->operation_code ?: '-' }}</td>
                                    <td>{{ $evidence->security_code ?: '-' }}</td>
                                    <td>{{ $dniResolution['last_dni'] ?? '-' }}</td>
                                    <td>{{ $dniResolution['attempts'] ?? '-' }}</td>
                                    <td>{{ optional($evidence->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('billing.yape.client-image', $evidence->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($intents->count())
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Intent</th>
                            <th>Cliente</th>
                            <th>Recibo</th>
                            <th>Monto</th>
                            <th>Operación</th>
                            <th>Estado</th>
                            <th>Evidencias</th>
                            <th>Creado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($intents as $intent)
                            <tr>
                                <td>
                                    <strong>{{ $intent->intent_number }}</strong>
                                    <div class="small text-muted">{{ $intent->payment_mode === 'multi_month' ? 'Multimes' : 'Individual' }}</div>
                                </td>
                                <td>
                                    {{ optional($intent->customer)->full_name ?: $intent->customer_name ?: '-' }}
                                    <div class="small text-muted">{{ $intent->whatsapp_phone ?: 'Sin WhatsApp' }}</div>
                                </td>
                                <td>
                                    @if($intent->invoice)
                                        <a href="{{ route('billing.invoices.show', $intent->invoice->id) }}">{{ $intent->invoice->invoice_number }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>S/ {{ number_format((float) $intent->expected_amount, 2) }}</td>
                                <td>{{ $intent->operation_code ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $badgeClasses[$intent->status] ?? 'bg-secondary' }}">
                                        {{ $intent->status_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $intent->client_evidence_id ? 'bg-success' : 'bg-light text-dark border' }}">WhatsApp</span>
                                    <span class="badge {{ $intent->receiver_event_id ? 'bg-success' : 'bg-light text-dark border' }}">Receptor</span>
                                </td>
                                <td>{{ optional($intent->created_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('billing.yape.show', $intent->id) }}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{ $intents->links() }}
        @else
            <div class="text-center py-5 text-muted">
                <i class="fas fa-mobile-alt fa-3x mb-3"></i>
                <p class="mb-0">No hay validaciones Yape con los filtros actuales.</p>
            </div>
        @endif
    </div>
</div>
@endsection
