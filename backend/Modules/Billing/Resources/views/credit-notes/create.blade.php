@extends('core::layouts.master')

@section('title', 'Crear Nota de Crédito')
@section('page-title', 'Crear Nota de Crédito')

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-receipt text-primary me-2"></i> Datos de Nota de Crédito
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('billing.credit-notes.store') }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="invoice_id" class="form-label">Recibo <span class="text-danger">*</span></label>
                            @if($invoice)
                                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                <div class="form-control disabled bg-light">{{ $invoice->invoice_number }}</div>
                            @else
                                <select class="form-select" id="invoice_id" name="invoice_id" required>
                                    <option value="">Seleccionar recibo</option>
                                    @foreach($invoices as $selectInvoice)
                                    <option value="{{ $selectInvoice->id }}" data-amount="{{ $selectInvoice->pending_amount }}" data-customer="{{ $selectInvoice->contract->customer->full_name }}">
                                        {{ $selectInvoice->invoice_number }} - {{ $selectInvoice->contract->customer->full_name }} ($ {{ number_format($selectInvoice->pending_amount, 2) }})
                                    </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label for="credit_note_number" class="form-label">Número de Nota de Crédito <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="credit_note_number" name="credit_note_number" value="{{ $creditNoteNumber }}" required>
                        </div>

                        <div class="col-md-6">
                            <label for="amount" class="form-label">Monto <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="amount" name="amount" required @if($invoice) max="{{ $invoice->pending_amount }}" @endif>
                            </div>
                            <div id="amount-feedback" class="form-text text-muted">
                                @if($invoice)
                                    Monto máximo: ${{ number_format($invoice->pending_amount, 2) }}
                                @else
                                    El monto no puede superar el saldo pendiente de la recibo
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="issue_date" class="form-label">Fecha de Emisión <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="issue_date" name="issue_date" value="{{ $issueDate }}" required>
                        </div>

                        <div class="col-md-12">
                            <label for="reason" class="form-label">Motivo <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                        </div>

                        <div class="col-md-12">
                            <label for="notes" class="form-label">Notas adicionales</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>

                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="apply_now" name="apply_now" checked>
                                <label class="form-check-label" for="apply_now">
                                    Aplicar nota de crédito inmediatamente a la recibo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ $invoice ? route('billing.invoices.show', $invoice->id) : route('billing.credit-notes.index') }}" class="btn btn-secondary me-2">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Crear Nota de Crédito
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Información de Recibo -->
        <div class="card mb-4" id="invoice-info-card" @if(!$invoice) style="display: none;" @endif>
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice text-primary me-2"></i> Información de Recibo
                </h5>
            </div>
            <div class="card-body">
                <h6 id="invoice-number">
                    @if($invoice) {{ $invoice->invoice_number }} @else Número de Recibo @endif
                </h6>

                <div class="mb-3">
                    <small class="text-muted">Cliente</small>
                    <div id="customer-name">
                        @if($invoice) {{ $invoice->contract->customer->full_name }} @else Nombre del Cliente @endif
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted">Fecha de Emisión</small>
                        <div id="issue-date">
                            @if($invoice) {{ $invoice->issue_date->format('d/m/Y') }} @else -- @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Estado</small>
                        <div id="status">
                            @if($invoice)
                                @if($invoice->status == 'pending')
                                    <span class="badge bg-warning">Pendiente</span>
                                @elseif($invoice->status == 'partial')
                                    <span class="badge bg-info">Pago Parcial</span>
                                @else
                                    <span class="badge bg-secondary">{{ $invoice->status }}</span>
                                @endif
                            @else
                                --
                            @endif
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-6">
                        <small class="text-muted">Total de Recibo</small>
                        <div id="total-amount">
                            @if($invoice) ${{ number_format($invoice->total_amount, 2) }} @else $0.00 @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Monto Pagado</small>
                        <div id="paid-amount">
                            @if($invoice) ${{ number_format($invoice->paid_amount, 2) }} @else $0.00 @endif
                        </div>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center">
                    <strong>Pendiente:</strong>
                    <span class="h5 mb-0 text-primary" id="pending-amount">
                        @if($invoice) ${{ number_format($invoice->pending_amount, 2) }} @else $0.00 @endif
                    </span>
                </div>
            </div>
        </div>

        <!-- Información -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle text-primary me-2"></i> Información
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i> Las notas de crédito se utilizan para reducir o anular el saldo de una recibo.
                </div>

                <p class="mb-0">
                    Indique el motivo por el cual está creando la nota de crédito. Este motivo quedará registrado en el historial de la recibo.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const invoiceSelect = document.getElementById('invoice_id');
    const amountInput = document.getElementById('amount');

    if (invoiceSelect) {
        invoiceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            if (selectedOption.value) {
                // Obtener datos de la recibo
                const pendingAmount = parseFloat(selectedOption.dataset.amount) || 0;
                const customerName = selectedOption.dataset.customer || '';

                // Actualizar monto sugerido
                amountInput.value = pendingAmount.toFixed(2);
                amountInput.max = pendingAmount;
                document.getElementById('amount-feedback').textContent = `Monto máximo: $${pendingAmount.toFixed(2)}`;

                // Mostrar tarjeta de información
                document.getElementById('invoice-info-card').style.display = 'block';

                // Actualizar campos
                document.getElementById('invoice-number').textContent = selectedOption.textContent.split(' - ')[0];
                document.getElementById('customer-name').textContent = customerName;
                document.getElementById('pending-amount').textContent = `$${pendingAmount.toFixed(2)}`;
            } else {
                // Ocultar tarjeta de información
                document.getElementById('invoice-info-card').style.display = 'none';
            }
        });
    }

    // Validar monto máximo
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            const value = parseFloat(this.value) || 0;
            const max = parseFloat(this.max) || 0;

            if (value > max) {
                this.value = max;
            }
        });
    }
});
</script>
@endpush
