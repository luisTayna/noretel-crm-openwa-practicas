@extends('core::layouts.master')

@section('title', 'Pago de varios servicios')
@section('page-title', 'Pago de varios servicios')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Pago de varios servicios</h1>
        <p class="text-muted mb-0">{{ $customer->full_name }} · Cliente #{{ $customer->id }} · {{ $customer->identity_document }}</p>
    </div>
    <a href="{{ route('customer.customers.show', $customer->id) }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form method="POST" action="{{ route('billing.payments.customer.store', $customer->id) }}" id="multiServicePaymentForm">
    @csrf
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold"><i class="fas fa-wifi text-primary me-2"></i>Seleccione los recibos que pagará</div>
        <div class="card-body">
            @forelse($pendingInvoices->groupBy('contract_id') as $contractId => $contractInvoices)
                @php($contract = $contractInvoices->first()->contract)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div><strong>Servicio #{{ $contractId }}</strong> · {{ $contract?->plan?->name ?: 'Sin plan' }}</div>
                        <span class="badge bg-light text-dark">Mensualidad {{ 'S/ '.number_format((float) $contract?->final_price, 2) }}</span>
                    </div>
                    @foreach($contractInvoices as $invoice)
                        <label class="d-flex justify-content-between align-items-center border-top py-3 mb-0">
                            <span>
                                <input type="checkbox" class="form-check-input me-2 invoice-check" name="invoice_ids[]" value="{{ $invoice->id }}" data-amount="{{ number_format((float) $invoice->pending_amount, 2, '.', '') }}" @checked(in_array($invoice->id, old('invoice_ids', [])))>
                                <strong>{{ $invoice->invoice_number ?: 'Recibo #'.$invoice->id }}</strong>
                                <span class="text-muted ms-2">{{ $invoice->billing_period }}</span>
                            </span>
                            <strong class="text-danger">S/ {{ number_format((float) $invoice->pending_amount, 2) }}</strong>
                        </label>
                    @endforeach
                </div>
            @empty
                <div class="text-center text-muted py-5">Este cliente no tiene recibos pendientes.</div>
            @endforelse
            @error('invoice_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>

    @if($pendingInvoices->isNotEmpty())
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-bold"><i class="fas fa-cash-register text-success me-2"></i>Datos del pago</div>
        <div class="card-body">
            <div class="alert alert-primary d-flex justify-content-between align-items-center">
                <span>Total seleccionado</span><strong class="h4 mb-0" id="selectedTotal">S/ 0.00</strong>
            </div>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Fecha *</label><input type="date" class="form-control" name="payment_date" value="{{ old('payment_date', $paymentDate) }}" required></div>
                <div class="col-md-4"><label class="form-label">Método *</label><select class="form-select" name="payment_method" id="paymentMethod" required><option value="">Seleccione</option>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4" id="receivedGroup"><label class="form-label">Monto recibido</label><div class="input-group"><span class="input-group-text">S/</span><input type="number" class="form-control" name="received_amount" id="receivedAmount" value="{{ old('received_amount') }}" min="0" step="0.01"></div>@error('received_amount')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-6" id="referenceGroup"><label class="form-label">Referencia / operación</label><input class="form-control" name="reference" value="{{ old('reference') }}">@error('reference')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">Vuelto</label><div class="form-control bg-light" id="changeAmount">S/ 0.00</div></div>
                <div class="col-12"><label class="form-label">Notas</label><textarea class="form-control" name="notes" rows="2">{{ old('notes') }}</textarea></div>
            </div>
        </div>
        <div class="card-footer bg-white text-end"><button type="submit" class="btn btn-success" id="submitPayment" disabled><i class="fas fa-save"></i> Registrar pago conjunto</button></div>
    </div>
    @endif
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks = Array.from(document.querySelectorAll('.invoice-check'));
    const totalLabel = document.getElementById('selectedTotal');
    const method = document.getElementById('paymentMethod');
    const received = document.getElementById('receivedAmount');
    const receivedGroup = document.getElementById('receivedGroup');
    const referenceGroup = document.getElementById('referenceGroup');
    const changeLabel = document.getElementById('changeAmount');
    const submit = document.getElementById('submitPayment');
    let total = 0;

    function refresh() {
        total = checks.filter(check => check.checked).reduce((sum, check) => sum + Number(check.dataset.amount || 0), 0);
        totalLabel.textContent = 'S/ ' + total.toFixed(2);
        const isCash = method.value === 'cash';
        receivedGroup.style.display = isCash ? '' : 'none';
        referenceGroup.style.display = method.value && !isCash ? '' : 'none';
        received.required = isCash;
        const change = isCash ? Math.max(Number(received.value || 0) - total, 0) : 0;
        changeLabel.textContent = 'S/ ' + change.toFixed(2);
        submit.disabled = total <= 0 || !method.value;
    }

    checks.forEach(check => check.addEventListener('change', refresh));
    method.addEventListener('change', refresh);
    received.addEventListener('input', refresh);
    refresh();
});
</script>
@endpush
