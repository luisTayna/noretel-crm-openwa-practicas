@extends('core::layouts.master')

@section('title', 'Revisión Yape')
@section('page-title', 'Revisión Yape')

@section('content')
@php
    $waitingClient = \Modules\Billing\Entities\YapePaymentIntent::STATUS_WAITING_CLIENT;
    $waitingReceiver = \Modules\Billing\Entities\YapePaymentIntent::STATUS_WAITING_RECEIVER;
    $observed = \Modules\Billing\Entities\YapePaymentIntent::STATUS_OBSERVED;
    $totalPending = $customerQueues->sum('count');
    $activeTab = $tab === 'review' ? 'review' : 'awaiting-dni';
@endphp

<div class="container-fluid py-4 yape-review">
    <div class="mb-4">
        <h1 class="h3 mb-1">Revisión de pagos Yape</h1>
        <p class="text-muted mb-0">Las capturas sin identificación y los pagos que requieren revisión se gestionan por separado.</p>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'awaiting-dni' ? 'active' : '' }}" href="{{ route('billing.yape.review-queue', ['tab' => 'awaiting-dni']) }}">Capturas sin DNI <span class="badge bg-primary ms-1">{{ $awaitingDniPaginator->total() }}</span></a></li>
        <li class="nav-item"><a class="nav-link {{ $activeTab === 'review' ? 'active' : '' }}" href="{{ route('billing.yape.review-queue', ['tab' => 'review']) }}">Pagos por revisar <span class="badge bg-secondary ms-1">{{ $totalPending }}</span></a></li>
    </ul>

    @if($activeTab === 'awaiting-dni')
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="summary-card"><strong>{{ $awaitingDniPaginator->total() }}</strong><span>Operaciones esperando DNI</span></div></div>
            <div class="col-6 col-lg-3"><div class="summary-card"><strong>{{ $awaitingDniSummary['physical_captures'] ?? 0 }}</strong><span>Capturas físicas</span></div></div>
            <div class="col-6 col-lg-3"><div class="summary-card"><strong>{{ $awaitingDniSummary['ready'] ?? 0 }}</strong><span>Listas para reanudar</span></div></div>
            <div class="col-6 col-lg-3"><div class="summary-card"><strong>{{ $awaitingDniSummary['unidentified'] ?? 0 }}</strong><span>Sin identificar</span></div></div>
        </div>

        <form method="GET" action="{{ route('billing.yape.review-queue') }}" class="card card-body border-0 shadow-sm mb-4">
            <input type="hidden" name="tab" value="awaiting-dni">
            <div class="row g-3 align-items-end">
                <div class="col-lg-5"><label class="form-label">Buscar</label><input class="form-control" name="search" value="{{ $search }}" placeholder="Cliente, DNI, teléfono, operación o monto"></div>
                <div class="col-lg-3"><label class="form-label">Estado</label><select class="form-select" name="case_status"><option value="all">Todos</option><option value="ready" @selected($caseStatus === 'ready')>Listos</option><option value="review" @selected($caseStatus === 'review')>Requieren revisión</option><option value="unidentified" @selected($caseStatus === 'unidentified')>Sin identificar</option><option value="duplicates" @selected($caseStatus === 'duplicates')>Capturas repetidas</option></select></div>
                <div class="col-lg-2"><label class="form-label">Por página</label><select class="form-select" name="per_page">@foreach([10,15,20] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach</select></div>
                <div class="col-lg-2"><button class="btn btn-primary w-100">Aplicar</button></div>
            </div>
        </form>

        @forelse($awaitingDniCases as $case)
            @php $evidence = $case->primaryEvidence; $candidate = $case->primaryCandidate; $intent = $case->existingIntent; $payment = $case->existingPayments->first() ?: $intent?->payment; @endphp
            <article class="case-card mb-4">
                <div class="d-flex flex-wrap justify-content-between gap-3"><div><h2 class="h5 mb-1">{{ $candidate?->name ?? 'Cliente no identificado' }}</h2><div class="text-muted">{{ $case->physicalEvidenceCount }} captura(s) · {{ optional($case->occurredAt)->format('d/m/Y H:i') }}</div></div><div class="text-end"><div class="text-muted small">Monto</div><div class="fs-4 fw-bold">S/ {{ number_format((float) $case->amount, 2) }}</div></div></div>
                <div class="row g-3 mt-2"><div class="col-md-3"><div class="label">Operación</div><div class="value">{{ $evidence->operation_code ?: 'No disponible' }}</div></div><div class="col-md-3"><div class="label">Captura recibida</div><a target="_blank" href="{{ route('billing.yape.client-image', $evidence) }}">Ver captura</a></div><div class="col-md-3"><div class="label">Receptor</div><div class="value">{{ $case->receiverEvent ? 'Encontrado' : 'Pendiente' }}</div></div><div class="col-md-3"><div class="label">Teléfono confirmado</div><div class="value">{{ data_get($case->phoneResolution, 'maskedPhone') ?: 'No confirmado' }}</div></div></div>
                @if($candidate)<div class="small text-muted mt-3">DNI {{ $candidate->maskedDni }} · Teléfono {{ $candidate->maskedPhone }}</div>@endif
                <div class="alert alert-light border mt-3 mb-3"><strong>{{ $case->state->explanation }}</strong><div class="small text-muted">{{ $case->state->recommendation }}</div></div>
                @if($payment)<a class="btn btn-outline-primary" href="{{ route('billing.payments.show', $payment) }}">Ver pago existente</a>
                @elseif($intent)<a class="btn btn-outline-primary" href="{{ route('billing.yape.show', $intent) }}">Ver pago por revisar</a>
                @elseif($case->canResume && auth()->user()->hasPermission('create', 'payments'))
                    <form method="POST" action="{{ route('billing.yape.review.resume', $evidence) }}" class="resume-form">@csrf<label class="form-label fw-semibold" for="dni-{{ $evidence->id }}">DNI del titular</label><div class="input-group"><input id="dni-{{ $evidence->id }}" class="form-control" name="dni" inputmode="numeric" pattern="\d{8}" maxlength="8" required placeholder="8 dígitos"><button class="btn btn-primary" type="submit">Confirmar y procesar</button></div><div class="form-text">Los casos exactos continúan por la conciliación Yape normal; los ambiguos pasan a Pagos por revisar.</div></form>
                @endif
                @if(!$payment && auth()->user()->hasPermission('delete', 'payments'))
                    <form method="POST" data-cancel-case action="{{ route('billing.yape.review.cancel', $evidence) }}" class="resume-form mt-3" onsubmit="return confirm('Esta acción cerrará el caso Yape completo{{ $case->physicalEvidenceCount > 1 ? ' (' . $case->physicalEvidenceCount . ' capturas)' : '' }}. No eliminará pagos ni modificará facturas. ¿Continuar?');">@csrf<label class="form-label fw-semibold">Motivo de Anular Yape</label><select class="form-select mb-2" name="reason" required><option value="">Seleccionar motivo</option><option>Pago ya registrado por otro método</option><option>Captura duplicada</option><option>Operación ya procesada</option><option>No corresponde al cliente</option><option>Captura de prueba</option><option>Cliente confirma que no corresponde</option><option>Otro</option></select><textarea class="form-control mb-2 d-none" name="reason_other" minlength="5" maxlength="500" placeholder="Explique el motivo (mínimo 5 caracteres)"></textarea><div class="form-text">Cierra el caso Yape y sus reenvíos. No elimina pagos ni modifica facturas.</div><button class="btn btn-outline-danger mt-2" type="submit">Anular Yape</button></form>
                @endif
                <details class="technical mt-3"><summary>Detalles técnicos</summary><div class="small mt-2">Evidencia principal #{{ $evidence->id }} · Reenvíos: {{ $case->resends->pluck('id')->map(fn($id) => '#'.$id)->join(', ') ?: 'Ninguno' }} · Receiver: {{ $case->receiverEvent?->id ? '#'.$case->receiverEvent->id : 'No encontrado' }} · Chat: {{ $evidence->source_chat_name ?: 'No disponible' }}</div></details>
            </article>
        @empty
            <div class="alert alert-success">No hay capturas esperando DNI con estos filtros.</div>
        @endforelse
        <div class="d-flex justify-content-center">{{ $awaitingDniPaginator->appends(request()->except('case_page'))->links() }}</div>
    @else
        <div class="row g-2 mb-3">
            <div class="col-md-3"><a class="text-decoration-none" href="{{ route('billing.yape.review-queue', ['tab' => 'review']) }}"><div class="summary-card"><span>PENDIENTES</span><strong>{{ $totalPending }}</strong></div></a></div>
            <div class="col-md-3"><a class="text-decoration-none" href="{{ route('billing.yape.review-queue', ['tab' => 'review', 'status' => $waitingClient]) }}"><div class="summary-card"><span>FALTA WHATSAPP</span><strong>{{ $summary[$waitingClient] ?? 0 }}</strong></div></a></div>
            <div class="col-md-3"><a class="text-decoration-none" href="{{ route('billing.yape.review-queue', ['tab' => 'review', 'status' => $waitingReceiver]) }}"><div class="summary-card"><span>ESPERANDO RECEPTOR</span><strong>{{ $summary[$waitingReceiver] ?? 0 }}</strong></div></a></div>
            <div class="col-md-3"><a class="text-decoration-none" href="{{ route('billing.yape.review-queue', ['tab' => 'review', 'status' => $observed]) }}"><div class="summary-card"><span>OBSERVADOS</span><strong>{{ $summary[$observed] ?? 0 }}</strong></div></a></div>
        </div>
        <form method="GET" action="{{ route('billing.yape.review-queue') }}" class="card card-body border-0 shadow-sm mb-3"><input type="hidden" name="tab" value="review"><div class="row g-2 align-items-center"><div class="col-lg-4"><input name="search" value="{{ $search }}" class="form-control" placeholder="Buscar cliente, DNI o teléfono..."></div><div class="col-lg-3"><select name="status" class="form-select"><option value="">Estado: Todos</option><option value="{{ $waitingClient }}" @selected($status === $waitingClient)>Falta WhatsApp</option><option value="{{ $waitingReceiver }}" @selected($status === $waitingReceiver)>Esperando receptor</option><option value="{{ $observed }}" @selected($status === $observed)>Observado</option></select></div><div class="col-lg-auto"><button class="btn btn-outline-primary" type="submit">Aplicar filtro</button></div>@if($status || $search)<div class="col-lg-auto"><a class="btn btn-light border" href="{{ route('billing.yape.review-queue', ['tab' => 'review']) }}">Limpiar</a></div>@endif</div></form>
        <div class="card border-0 shadow-sm overflow-hidden"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Cliente</th><th>Monto</th><th>Fecha</th><th>WhatsApp</th><th>Receptor Android</th><th>Operación</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>
        @forelse($customerQueues as $queue)
            @php $intent = $queue->firstIntent; $clientReceived = (bool) $intent->client_evidence_id; $receiverReceived = (bool) $intent->receiver_event_id; $isObserved = $intent->status === $observed; $isHistorical = (bool) data_get($intent->source_payload, 'historical_review.is_historical'); @endphp
            <tr><td><strong>{{ $queue->name }}</strong><div class="small text-muted">{{ $queue->phone ?: 'Sin WhatsApp' }}</div></td><td>S/ {{ number_format((float) $intent->expected_amount, 2) }}</td><td>{{ optional($intent->created_at)->format('d/m/Y H:i') }}</td><td>{!! $clientReceived ? '<span class="badge bg-success">Recibido</span>' : '<span class="badge bg-danger">No encontrado</span>' !!}</td><td>{!! $receiverReceived ? '<span class="badge bg-success">Recibido</span>' : '<span class="badge bg-warning text-dark">Esperando</span>' !!}</td><td>{{ $intent->operation_code ?: '—' }}</td><td>@if($isHistorical)<span class="badge bg-warning text-dark">Histórico · confirmar</span>@elseif($isObserved)<span class="badge bg-danger">Observado</span>@elseif($clientReceived && $receiverReceived)<span class="badge bg-success">Listo para validar</span>@else<span class="badge bg-primary">Esperando receptor</span>@endif</td><td class="text-end"><a class="btn btn-sm btn-primary" href="{{ route('billing.yape.show', $intent) }}">Revisar</a></td></tr>
        @empty<tr><td colspan="8" class="text-center text-muted py-5">No hay pagos Yape pendientes con estos filtros.</td></tr>@endforelse
        </tbody></table></div></div>
    @endif
</div>
<style>.summary-card,.case-card{background:#fff;border:1px solid #e8edf3;border-radius:14px;padding:20px;box-shadow:0 8px 25px rgba(15,23,42,.06)}.summary-card strong{display:block;font-size:1.7rem}.summary-card span,.label{color:#64748b;font-size:.78rem;font-weight:700}.label{text-transform:uppercase}.value{font-weight:700}.resume-form{max-width:560px}.technical summary{cursor:pointer;color:#64748b}</style>
@endsection

@push('scripts')
<script>
// "Otro" is the only reason that needs a written explanation. The server enforces
// this too; this only avoids a pointless round trip for the operator.
document.querySelectorAll('form[data-cancel-case]').forEach(function (form) {
    var reason = form.querySelector('[name="reason"]');
    var explanation = form.querySelector('[name="reason_other"]');

    if (!reason || !explanation) {
        return;
    }

    var sync = function () {
        var needsExplanation = reason.value === 'Otro';
        explanation.required = needsExplanation;
        explanation.classList.toggle('d-none', !needsExplanation);
    };

    reason.addEventListener('change', sync);
    sync();
});
</script>
@endpush
