@extends('core::layouts.master')

@section('title', 'Validación Yape ' . $intent->intent_number)
@section('page-title', 'Validación Yape')

@section('content')
@php
    $customer = $intent->customer ?: ($intent->invoice?->contract?->customer ?: $intent->contract?->customer);
    $clientEvidence = $intent->clientEvidence;
    $receiverEvent = $intent->receiverEvent;
    $isValidated = $intent->status === \Modules\Billing\Entities\YapePaymentIntent::STATUS_VALIDATED;
    $isCancelled = $intent->status === \Modules\Billing\Entities\YapePaymentIntent::STATUS_CANCELLED;
    $isObserved = $intent->status === \Modules\Billing\Entities\YapePaymentIntent::STATUS_OBSERVED;
    $isHistorical = (bool) data_get($intent->source_payload, 'historical_review.is_historical');
    $canManualValidate = !$isValidated && !$isCancelled && $clientEvidence && !$intent->payment_id;
    $canCancel = !$isValidated && !$isCancelled && !$intent->payment_id;
    $statusStyle = $isValidated ? ['bg' => '#dcfce7', 'color' => '#15803d'] : ($isObserved ? ['bg' => '#ffe4e6', 'color' => '#e11d48'] : ($isCancelled ? ['bg' => '#e5e7eb', 'color' => '#4b5563'] : ['bg' => '#eaf2ff', 'color' => '#2563eb']));
    $receiptDelivery = is_array($clientEvidence?->payload) ? data_get($clientEvidence->payload, 'payment_receipt', []) : [];
    $receiptSentAt = data_get($receiptDelivery, 'sent_at');
    $statusDescription = $isValidated
        ? ($intent->payment_id
            ? 'El pago quedó registrado correctamente.'
            : 'Atención: figura validado, pero no tiene un pago asociado.')
        : ($isObserved
            ? ($intent->observed_reason ?: 'Las evidencias no coincidieron.')
            : 'Esperando coincidencia de WhatsApp, receptor, monto y código.');
@endphp

<style>
    .yape-detail { --purple:#6d28d9; --border:#e9edf3; }
    .yape-detail .detail-card { border:0; border-radius:16px; box-shadow:0 .2rem 1rem rgba(31,51,73,.08); overflow:hidden; }
    .yape-detail .detail-card-header { background:#fff; padding:20px 24px; border-bottom:1px solid #edf0f4; font-weight:700; }
    .yape-detail .detail-card-body { padding:24px; }
    .yape-detail .heading-icon { color:#0d6efd; margin-right:10px; }
    .yape-detail .status-box { background:#fafaff; border-radius:12px; padding:22px; text-align:center; }
    .yape-detail .action-btn { width:100%; border:0; border-radius:10px; padding:12px 16px; font-weight:700; margin-top:12px; }
    .yape-detail .action-manual { color:#fff; background:#ed8b00; }
    .yape-detail .action-cancel { background:#fff; border:1px solid #fda4af; color:#ef4444; }
    .yape-detail .comparison { font-size:.92rem; }
    .yape-detail .comparison thead th { background:#fff; color:#64748b; font-size:.72rem; text-transform:uppercase; padding:12px; border:0; }
    .yape-detail .comparison td { border-top:10px solid #fff; padding:16px 12px; background:#fffaf2; vertical-align:middle; }
    .yape-detail .comparison td:first-child { border-radius:9px 0 0 9px; font-weight:700; }
    .yape-detail .comparison td:last-child { border-radius:0 9px 9px 0; }
    .yape-detail .expected-value { padding:4px 8px; border:1px solid #dce3eb; border-radius:6px; font-weight:700; font-size:.82rem; background:#fff; }
    .yape-detail .value-ok { color:#159a5a; font-weight:700; }
    .yape-detail .value-waiting { color:#7c6b5a; }
    .yape-detail .evidence-image { max-height:430px; width:100%; object-fit:contain; background:#f8fafc; border-radius:8px; }
    .yape-detail .empty-evidence { min-height:300px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; color:#64748b; }
    .yape-detail .key-value td { border:0; padding:7px 0; }
    .yape-detail .key-value td:first-child { width:38%; font-weight:700; color:#334155; }
</style>

<div class="yape-detail">
    @foreach(['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $flash => $class)
        @if(session($flash))<div class="alert alert-{{ $class }}">{{ session($flash) }}</div>@endif
    @endforeach

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="mb-1 fw-bold">{{ $intent->intent_number }}</h3>
            <span class="badge" style="background:{{ $statusStyle['bg'] }};color:{{ $statusStyle['color'] }};font-size:.85rem">{{ $intent->status_label }}</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('billing.yape.preview-receipt', $intent) }}" target="_blank" class="btn btn-primary rounded-pill px-3 fw-bold">
                <i class="fas fa-receipt me-1"></i> Previsualizar recibo térmico
            </a>
            <a href="{{ route('billing.yape.review-queue') }}" class="btn btn-outline-primary rounded-pill px-3"><i class="fas fa-arrow-left me-1"></i> Volver</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="detail-card bg-white mb-4">
                <div class="detail-card-header"><i class="fas fa-sliders-h heading-icon"></i>Acciones de conciliación</div>
                <div class="detail-card-body">
                    <div class="status-box">
                        <div class="text-muted mb-2">Estado de conciliación</div>
                        <span class="badge px-3 py-2" style="background:{{ $statusStyle['bg'] }};color:{{ $statusStyle['color'] }};font-size:.95rem">{{ $intent->status_label }}</span>
                        <p class="small text-muted mb-0 mt-3"><i class="fas fa-info-circle me-1"></i>{{ $statusDescription }}</p>
                    </div>
                    @if($isValidated)
                        <div class="border rounded-3 p-3 mt-3 small">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <strong>Pago registrado</strong>
                                @if($intent->payment)
                                    <a href="{{ route('billing.payments.show', $intent->payment) }}">#{{ $intent->payment->id }}</a>
                                @else
                                    <span class="text-danger">No encontrado</span>
                                @endif
                            </div>
                            <div class="d-flex justify-content-between gap-2">
                                <strong>Constancia WhatsApp</strong>
                                @if($receiptSentAt)
                                    <span class="text-success">Enviada {{ \Carbon\Carbon::parse($receiptSentAt)->format('d/m/Y H:i') }}</span>
                                @else
                                    <span class="text-warning">Pendiente de envío</span>
                                @endif
                            </div>
                        </div>
                        @if($intent->payment_id)
                            <form method="POST" action="{{ route('billing.yape.resend-receipt', $intent) }}" onsubmit="return confirm('¿Deseas reenviar la constancia de pago por WhatsApp?');">
                                @csrf
                                <button class="action-btn btn btn-primary" type="submit"><i class="fab fa-whatsapp me-1"></i> {{ $receiptSentAt ? 'Reenviar constancia' : 'Enviar constancia ahora' }}</button>
                            </form>
                        @endif
                    @endif
                    @if($canManualValidate)
                        <form method="POST" action="{{ route('billing.yape.manual-validate', $intent) }}" onsubmit="return confirm('¿Confirmas que verificaste el pago en la cuenta receptora? Esta acción registrará el pago y enviará la constancia por WhatsApp.');">@csrf<button class="action-btn action-manual" type="submit"><i class="fas fa-check-circle me-1"></i> {{ $isHistorical ? 'Validar pago histórico' : 'Validar manualmente' }}</button></form>
                    @endif

                    <a href="{{ route('billing.yape.preview-receipt', $intent) }}" target="_blank" class="action-btn btn btn-info text-white fw-bold mt-2 text-center text-decoration-none d-block" style="background:#0284c7;border:0;">
                        <i class="fas fa-receipt me-1"></i> Previsualizar recibo térmico
                    </a>

                    @if($canCancel)
                        <form method="POST" action="{{ route('billing.yape.cancel', $intent) }}" onsubmit="return confirm('Esta acción cerrará únicamente el caso Yape. No eliminará pagos ni modificará facturas. ¿Continuar?');">@csrf<label class="small fw-bold mt-3">Motivo de Anular Yape</label><textarea class="form-control" name="reason" required minlength="5" maxlength="500" placeholder="Ej.: Pago ya registrado por otro método"></textarea><div class="form-text">Esta acción cerrará únicamente el caso Yape. No eliminará pagos ni modificará facturas.</div><button class="action-btn action-cancel" type="submit"><i class="fas fa-ban me-1"></i> {{ $isHistorical ? 'Eliminar caso histórico' : 'Anular Yape' }}</button></form>
                    @elseif($intent->payment_id)
                        <div class="alert alert-info small mt-3 mb-0">Este Yape ya generó un pago. Para revertir dinero utiliza el flujo financiero de anulación del pago.</div>
                        <a class="btn btn-outline-primary w-100 mt-2" href="{{ route('billing.payments.show', $intent->payment_id) }}">Ir al pago registrado</a>
                    @endif
                </div>
            </div>

            <div class="detail-card bg-white">
                <div class="detail-card-header"><i class="fas fa-receipt heading-icon"></i>Intent esperado</div>
                <div class="detail-card-body">
                    <table class="table key-value mb-0">
                        <tr><td>Cliente:</td><td>{{ $customer?->full_name ?: $intent->customer_name ?: '-' }}</td></tr>
                        <tr><td>DNI:</td><td>{{ $customer?->identity_document ?: '-' }}</td></tr>
                        <tr><td>WhatsApp:</td><td>{{ $intent->whatsapp_phone ?: '-' }}</td></tr>
                        <tr><td>Monto:</td><td>S/ {{ number_format((float) $intent->expected_amount, 2) }}</td></tr>
                        <tr><td>Recibo:</td><td>@if($intent->invoice)<a href="{{ route('billing.invoices.show', $intent->invoice) }}">{{ $intent->invoice->invoice_number }}</a>@else - @endif</td></tr>
                        <tr><td>Operación:</td><td>{{ $intent->operation_code ?: '-' }}</td></tr>
                        <tr><td>Creado:</td><td>{{ optional($intent->created_at)->format('d/m/Y H:i') }}</td></tr>
                        @if($isHistorical)<tr><td>Tipo de revisión:</td><td><span class="badge bg-warning text-dark">Pago histórico · confirmación manual</span></td></tr>@endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="detail-card bg-white mb-4">
                <div class="detail-card-header"><i class="fas fa-exchange-alt heading-icon"></i>Comparación de datos OCR vs esperado</div>
                <div class="detail-card-body pt-2">
                    <div class="table-responsive"><table class="table comparison mb-0">
                        <thead><tr><th>Campo</th><th>Valor esperado</th><th>WhatsApp cliente</th><th>Receptor Android</th></tr></thead>
                        <tbody>
                            <tr><td><i class="fas fa-coins text-warning me-2"></i>Monto</td><td><span class="expected-value">S/ {{ number_format((float) $intent->expected_amount, 2) }}</span></td><td>@if($clientEvidence)<span class="value-ok">S/ {{ number_format((float) $clientEvidence->parsed_amount, 2) }} <i class="fas fa-check-circle"></i></span>@else<span class="value-waiting">—</span>@endif</td><td>@if($receiverEvent)<span class="value-ok">S/ {{ number_format((float) $receiverEvent->parsed_amount, 2) }} <i class="fas fa-check-circle"></i></span>@else<span class="value-waiting"><i class="fas fa-hourglass-half me-1"></i>Esperando</span>@endif</td></tr>
                            <tr><td><i class="fas fa-key text-warning me-2"></i>Código de operación</td><td><span class="expected-value">{{ $intent->operation_code ?: '—' }}</span></td><td>@if($clientEvidence?->operation_code)<span class="value-ok">{{ $clientEvidence->operation_code }} <i class="fas fa-check-circle"></i></span>@else<span class="value-waiting">—</span>@endif</td><td>@if($receiverEvent?->operation_code)<span class="value-ok">{{ $receiverEvent->operation_code }} <i class="fas fa-check-circle"></i></span>@else<span class="value-waiting"><i class="fas fa-hourglass-half me-1"></i>Esperando</span>@endif</td></tr>
                            <tr><td><i class="fas fa-shield-alt text-warning me-2"></i>Código de seguridad</td><td><span class="expected-value">{{ $intent->security_code ?: '—' }}</span></td><td>{{ $clientEvidence?->security_code ?: '—' }}</td><td>{{ $receiverEvent?->security_code ?: 'Esperando' }}</td></tr>
                        </tbody>
                    </table></div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6"><div class="detail-card bg-white h-100"><div class="detail-card-header d-flex justify-content-between"><span><i class="fab fa-whatsapp text-success me-2"></i>Evidencia WhatsApp</span><span class="badge {{ $clientEvidence ? 'bg-success' : 'bg-warning text-dark' }}">{{ $clientEvidence ? 'Recibido' : 'Pendiente' }}</span></div><div class="detail-card-body">
                    @if($clientEvidence)
                        <img src="{{ route('billing.yape.client-image', $clientEvidence) }}" class="evidence-image mb-3" alt="Captura enviada por WhatsApp">
                        <table class="table key-value mb-0"><tr><td>ID evidencia:</td><td>#{{ $clientEvidence->id }}</td></tr><tr><td>Fecha:</td><td>{{ optional($clientEvidence->occurred_at)->format('d/m/Y H:i') ?: '-' }}</td></tr><tr><td>Confianza OCR:</td><td>{{ number_format((float) $clientEvidence->confidence, 2) }}</td></tr></table>
                    @else
                        <div class="empty-evidence"><i class="fab fa-whatsapp fa-3x text-success mb-3"></i><p class="mb-0">Esperando la captura enviada por WhatsApp.</p></div>
                    @endif
                </div></div></div>
                <div class="col-lg-6"><div class="detail-card bg-white h-100"><div class="detail-card-header d-flex justify-content-between"><span><i class="fas fa-mobile-alt text-primary me-2"></i>Notificación Android</span><span class="badge {{ $receiverEvent ? 'bg-success' : 'bg-warning text-dark' }}">{{ $receiverEvent ? 'Recibido' : 'Pendiente' }}</span></div><div class="detail-card-body">
                    @if($receiverEvent)
                        <img src="{{ route('billing.yape.receiver-image', $receiverEvent) }}" class="evidence-image mb-3" alt="Notificación recibida en Android">
                        <table class="table key-value mb-0"><tr><td>ID evento:</td><td>#{{ $receiverEvent->id }}</td></tr><tr><td>Fecha:</td><td>{{ optional($receiverEvent->occurred_at)->format('d/m/Y H:i') ?: '-' }}</td></tr><tr><td>Confianza OCR:</td><td>{{ number_format((float) $receiverEvent->confidence, 2) }}</td></tr></table>
                    @else
                        <div class="empty-evidence"><i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i><p class="mb-0">Esperando notificación detectada desde la app Android.</p></div>
                    @endif
                </div></div></div>
            </div>
        </div>
    </div>
</div>
@endsection
