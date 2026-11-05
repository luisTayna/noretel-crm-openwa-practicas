@extends('core::layouts.master')

@section('title', 'Registrar Pago')
@section('page-title', 'Registrar Pago')

@section('content')
@php
    $selectedPendingAmount = $invoice ? (float) $invoice->pending_amount : 0;
    $selectedContract = $contract ?? optional($invoice)->contract;
    $selectedCustomer = optional($selectedContract)->customer;
    $selectedPaymentMode = old('payment_mode', $paymentMode ?? 'single');
    $pendingDebtTotal = isset($contractPendingInvoices) ? (float) $contractPendingInvoices->sum(fn ($pendingInvoice) => (float) $pendingInvoice->pending_amount) : 0;
    $suggestedMultiAmount = $pendingDebtTotal > 0 ? $pendingDebtTotal : (float) ($monthlyAmount ?? 0);

    $cleanPeriodHelper = function (?string $billingPeriod) {
        $billingPeriod = trim((string) $billingPeriod);
        if ($billingPeriod === '') {
            return '';
        }
        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $billingPeriod, $matches, PREG_SET_ORDER)) {
            try {
                $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
                $day = (int) $targetMatch[1];
                $month = (int) $targetMatch[2];
                $year = (int) $targetMatch[3];
                $date = \Carbon\Carbon::create($year, $month, $day);
                $monthName = ucfirst($date->locale('es')->translatedFormat('F'));
                return "{$monthName} {$year}";
            } catch (\Throwable) {
                return $billingPeriod;
            }
        }
        return $billingPeriod;
    };
    $periodIsOverdue = function ($pendingInvoice): bool {
        if ($pendingInvoice->isOverdue()) {
            return true;
        }

        $periodDate = $pendingInvoice->issue_date?->copy()->startOfMonth();
        $rawPeriod = (string) ($pendingInvoice->billing_period ?? '');

        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $rawPeriod, $matches, PREG_SET_ORDER)) {
            $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
            $periodDate = \Carbon\Carbon::create((int) $targetMatch[3], (int) $targetMatch[2], 1)->startOfMonth();
        }

        return $periodDate && $periodDate->lt(now()->startOfMonth());
    };
    $selectedInvoiceIsOverdue = $invoice ? $periodIsOverdue($invoice) : false;
@endphp

<style>
    .payment-shell {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 420px;
        gap: 1.5rem;
    }

    .payment-card {
        border: 0;
        border-radius: 1.25rem;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .08);
        overflow: hidden;
    }

    .payment-card .card-header {
        background: #fff;
        border-bottom: 1px solid #eef2f7;
        padding: 1.35rem 1.5rem;
    }

    .payment-card .card-body {
        padding: 1.5rem;
    }

    .payment-section-title {
        color: #0f172a;
        font-size: .82rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
        margin-bottom: 1rem;
    }

    .payment-summary-box {
        background: linear-gradient(135deg, #f8fafc, #eef6ff);
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
    }

    .payment-summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .65rem 0;
        border-bottom: 1px solid rgba(148, 163, 184, .25);
    }

    .payment-summary-row:last-child {
        border-bottom: 0;
    }

    .payment-summary-row span {
        color: #64748b;
    }

    .payment-summary-row strong {
        color: #0f172a;
        font-size: 1.05rem;
    }

    .payment-summary-row.total strong {
        color: #dc2626;
        font-size: 1.35rem;
    }

    .payment-summary-row.change strong {
        color: #059669;
        font-size: 1.35rem;
    }

    .payment-help {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: .85rem;
        color: #475569;
        padding: .9rem 1rem;
    }

    .cash-only,
    .yape-only {
        transition: opacity .2s ease;
    }

    .payment-mode-card {
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1rem;
        background: #fff;
        cursor: pointer;
        height: 100%;
        transition: all 0.2s ease;
    }

    .payment-mode-card input {
        margin-right: .5rem;
    }

    .payment-mode-card.active {
        border-color: #2563eb;
        background-color: #eff6ff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .multi-month-panel {
        border: 1px solid #dbeafe;
        border-radius: 1rem;
        background: #eff6ff;
        padding: 1.25rem;
    }

    .bg-danger-soft {
        background-color: rgba(239, 68, 68, 0.1);
        color: #dc2626;
        border: 1px solid rgba(239, 68, 68, 0.15);
    }
    
    .bg-warning-soft {
        background-color: rgba(245, 158, 11, 0.15);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
    
    .bg-success-soft {
        background-color: rgba(16, 185, 129, 0.1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.15);
    }

    .period-item {
        border: 1px solid #e2e8f0;
        border-radius: 0.75rem !important;
        background: #fff;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .period-item:hover {
        border-color: #3b82f6;
        background-color: #f8fafc;
    }

    .period-item.selected {
        border-color: #2563eb;
        background-color: #eff6ff;
    }

    .single-invoice-card {
        border: 1px solid #cbd5e1;
        border-radius: 0.75rem;
        background: #f8fafc;
        padding: 1rem;
        margin-top: 0.5rem;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    @media (max-width: 991.98px) {
        .payment-shell {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="payment-shell">
    <div>
        <div class="card payment-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center gap-3">
                <h5 class="mb-0">
                    <i class="fas fa-money-bill-wave text-primary me-2"></i> Caja de Cobranza
                </h5>
                @if($selectedCustomer)
                    <a href="{{ route('customer.customers.show', $selectedCustomer->id) }}" class="btn btn-outline-primary btn-sm text-nowrap">
                        <i class="fas fa-arrow-left me-1"></i> Volver al detalle del cliente
                    </a>
                @endif
            </div>
            <div class="card-body">
                <form action="{{ route('billing.payments.store') }}" method="POST" id="payment-form" enctype="multipart/form-data">
                    @csrf
                    @if($selectedContract)
                        <input type="hidden" name="contract_id" value="{{ $selectedContract->id }}">
                    @endif

                    <div class="payment-section-title">Tipo de pago</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="payment-mode-card">
                                <input type="radio" name="payment_mode" value="single" {{ $selectedPaymentMode === 'single' ? 'checked' : '' }}>
                                <strong>Pago de recibo actual</strong>
                                <span class="d-block text-muted small mt-1">Aplica el pago solo al recibo seleccionado.</span>
                            </label>
                        </div>
                        <div class="col-md-6">
                            <label class="payment-mode-card">
                                <input type="radio" name="payment_mode" value="multi_month" {{ $selectedPaymentMode === 'multi_month' ? 'checked' : '' }}>
                                <strong>Pago de varios meses</strong>
                                <span class="d-block text-muted small mt-1">Paga deuda antigua y genera meses futuros si corresponde.</span>
                            </label>
                        </div>
                    </div>

                    <div class="payment-section-title">Recibo pendiente</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <label for="invoice_id" class="form-label">Recibo pendiente <span class="text-danger">*</span></label>
                            @if($invoice)
                                <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                                <div class="single-invoice-card d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $invoice->invoice_number }}</div>
                                        <div class="text-muted small">Período: {{ $cleanPeriodHelper($invoice->billing_period ?: $invoice->issue_date->format('m/Y')) }}</div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge {{ $selectedInvoiceIsOverdue ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning' }} me-2">
                                            {{ $selectedInvoiceIsOverdue ? 'Vencido' : 'Pendiente' }}
                                        </span>
                                        <strong class="text-primary font-monospace">S/ {{ number_format((float) $invoice->pending_amount, 2) }}</strong>
                                    </div>
                                </div>
                            @else
                                <select class="form-select @error('invoice_id') is-invalid @enderror" id="invoice_id" name="invoice_id" required>
                                    <option value="">Seleccionar recibo pendiente</option>
                                    @foreach($pendingInvoices as $pendingInvoice)
                                    <option
                                        value="{{ $pendingInvoice->id }}"
                                        data-amount="{{ $pendingInvoice->pending_amount }}"
                                        data-total="{{ $pendingInvoice->total_amount }}"
                                        data-paid="{{ $pendingInvoice->paid_amount }}"
                                        data-customer="{{ $pendingInvoice->contract->customer->full_name }}"
                                        data-period="{{ $cleanPeriodHelper($pendingInvoice->billing_period ?: optional($pendingInvoice->issue_date)->format('m/Y')) }}"
                                        {{ old('invoice_id') == $pendingInvoice->id ? 'selected' : '' }}
                                    >
                                        {{ $pendingInvoice->invoice_number }} - {{ $pendingInvoice->contract->customer->full_name }} (S/ {{ number_format($pendingInvoice->pending_amount, 2) }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('invoice_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div id="single-invoice-display-container" style="display: none;"></div>
                            @endif
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Registrado por</label>
                            <div class="form-control disabled bg-light">
                                {{ optional(auth()->user())->name ?? optional(auth()->user())->email ?? 'Usuario actual' }}
                            </div>
                        </div>
                    </div>

                    <div class="multi-month-panel mb-4" id="multi-month-panel" style="display: none;">
                        <div class="payment-section-title mb-2">
                            <i class="fas fa-calendar-alt text-primary me-2"></i> Selector de Períodos
                        </div>
                        <p class="text-muted small mb-3">
                            Marque los meses que desea pagar. La selección se realiza en orden consecutivo para mantener la consistencia de la facturación.
                        </p>

                        <div class="periods-checklist mb-3" id="periods-checklist">
                            @if(isset($contractPendingInvoices) && $contractPendingInvoices->isNotEmpty())
                                @foreach($contractPendingInvoices as $index => $pendingContractInvoice)
                                    @php
                                        $rawPeriod = $pendingContractInvoice->billing_period ?: optional($pendingContractInvoice->issue_date)->format('m/Y');
                                        $lastMonthYear = '';
                                        if (preg_match_all('/(\d{2})\/(\d{2})\/(\d{4})/', $rawPeriod, $matches, PREG_SET_ORDER)) {
                                            $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
                                            $lastMonthYear = "{$targetMatch[2]}/{$targetMatch[3]}"; // MM/YYYY
                                        } else {
                                            $lastMonthYear = optional($pendingContractInvoice->issue_date)->format('m/Y');
                                        }
                                        $periodName = $cleanPeriodHelper($rawPeriod);
                                        $isOverdue = $periodIsOverdue($pendingContractInvoice);
                                    @endphp
                                    <div class="list-group-item period-item d-flex align-items-center justify-content-between p-3 border rounded mb-2 shadow-sm selected" 
                                         data-type="pending" 
                                         data-index="{{ $index }}" 
                                         data-amount="{{ (float)$pendingContractInvoice->pending_amount }}"
                                         data-period="{{ $periodName }}"
                                         data-period-raw="{{ $lastMonthYear }}"
                                         data-invoice-id="{{ $pendingContractInvoice->id }}">
                                        <div class="d-flex align-items-center">
                                            <div class="form-check me-3">
                                                <input class="form-check-input period-checkbox" type="checkbox" value="{{ $pendingContractInvoice->id }}" id="chk-period-{{ $index }}" data-index="{{ $index }}" checked>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">{{ $periodName }}</div>
                                                <div class="text-muted small">Recibo: {{ $pendingContractInvoice->invoice_number }}</div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge {{ $isOverdue ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning' }} me-2">
                                                {{ $isOverdue ? 'Vencido' : 'Pendiente' }}
                                            </span>
                                            <strong class="text-primary font-monospace">S/ {{ number_format((float) $pendingContractInvoice->pending_amount, 2) }}</strong>
                                            <div class="period-discount-wrapper mt-2 text-start">
                                                <label class="form-label small mb-1" for="discount-period-{{ $pendingContractInvoice->id }}">Descuento</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">S/</span>
                                                    <input
                                                        type="text"
                                                        inputmode="decimal"
                                                        autocomplete="off"
                                                        class="form-control period-discount-input @error('invoice_discounts.'.$pendingContractInvoice->id) is-invalid @enderror"
                                                        id="discount-period-{{ $pendingContractInvoice->id }}"
                                                        name="invoice_discounts[{{ $pendingContractInvoice->id }}]"
                                                        value="{{ old('invoice_discounts.'.$pendingContractInvoice->id) }}"
                                                        placeholder="0.00"
                                                        data-invoice-id="{{ $pendingContractInvoice->id }}"
                                                        aria-label="Descuento para {{ $periodName }}"
                                                    >
                                                </div>
                                                <div class="period-discount-feedback form-text text-danger d-none"></div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-center p-3 text-muted bg-white rounded border border-dashed">
                                    No hay recibos pendientes en este contrato. Puede registrar pagos por adelantado a continuación.
                                </div>
                            @endif
                            
                            <!-- Contenedor para futuros meses adelantados -->
                            <div id="future-periods-list"></div>
                        </div>

                        <!-- Botón para añadir meses de prepago -->
                        <div class="text-start mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" id="btn-add-future">
                                <i class="fas fa-plus me-1"></i> Agregar mes adelantado (Prepago)
                            </button>
                        </div>

                        <!-- Input oculto para enviar cantidad de meses al backend -->
                        <input type="hidden" name="months_to_pay" id="months_to_pay" value="{{ old('months_to_pay', 1) }}" data-monthly-amount="{{ number_format((float) ($monthlyAmount ?? 0), 2, '.', '') }}">
                    </div>

                    <div class="payment-section-title">Datos del pago</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Monto a aplicar <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    id="amount"
                                    name="amount"
                                    required
                                    value="{{ old('amount', $selectedPaymentMode === 'multi_month' ? $suggestedMultiAmount : ($invoice ? $invoice->pending_amount : '')) }}"
                                    data-pending="{{ old('pending_amount', $selectedPendingAmount) }}"
                                >
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div id="amount-feedback" class="form-text text-muted">
                                @if($invoice)
                                    Importe pendiente de este recibo: S/ {{ number_format($invoice->pending_amount, 2) }}
                                @else
                                    Seleccione un recibo pendiente para sugerir el importe pendiente.
                                @endif
                            </div>

                            <div class="col-12 multi-month-only mt-2" id="live-distribution-container" style="display: none;">
                                <div class="alert alert-info border-0 shadow-sm rounded-3 p-3 mb-0" id="live-distribution-summary">
                                    <div class="fw-bold mb-1 text-primary"><i class="fas fa-calculator me-1"></i> Desglose de aplicación del pago:</div>
                                    <div id="live-distribution-details" class="small text-dark"></div>
                                </div>
                                <div class="form-check form-switch bg-light p-2.5 rounded border mt-2" id="overpayment-credit-option" style="display: none;">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="save_overpayment_as_credit" id="save_overpayment_as_credit" value="1">
                                    <label class="form-check-label fw-bold text-primary small" for="save_overpayment_as_credit">
                                        <i class="fas fa-wallet me-1"></i> Registrar excedente como Saldo a Favor del cliente
                                    </label>
                                    <div class="form-text text-muted small ms-4">
                                        Si se activa, el dinero sobrante no se entregará como vuelto sino que se abonará a la bolsa de Saldo a Favor del cliente.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 single-discount-only">
                            <label for="discount_amount" class="form-label">Descuento fijo</label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control @error('discount_amount') is-invalid @enderror"
                                    id="discount_amount"
                                    name="discount_amount"
                                    value="{{ old('discount_amount', 0) }}"
                                >
                                @error('discount_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div id="discount-feedback" class="form-text">Se descuenta del saldo pendiente del recibo.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">Método de pago <span class="text-danger">*</span></label>
                            <select class="form-select @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method" required>
                                <option value="">Seleccionar método</option>
                                @foreach($paymentMethods as $key => $value)
                                <option value="{{ $key }}" {{ old('payment_method') === $key ? 'selected' : '' }}>{{ $value }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 discount-reason-only">
                            <label for="discount_reason" class="form-label">Motivo del descuento <span class="text-muted">(opcional)</span></label>
                            <input type="text" class="form-control @error('discount_reason') is-invalid @enderror" id="discount_reason" name="discount_reason" value="{{ old('discount_reason') }}" maxlength="1000" placeholder="Ej. ajuste comercial">
                            @error('discount_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 cash-only">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="received_amount" class="form-label mb-0">Monto recibido del cliente <span class="text-danger cash-required">*</span></label>
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-primary" id="btn-exact-amount" style="font-size: 0.78rem;">
                                    <i class="fas fa-magic me-1"></i> Monto exacto
                                </button>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    autocomplete="off"
                                    class="form-control @error('received_amount') is-invalid @enderror"
                                    id="received_amount"
                                    name="received_amount"
                                    value="{{ old('received_amount') }}"
                                    placeholder="0.00"
                                >
                                @error('received_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">Solo aplica para pagos en efectivo.</div>
                        </div>

                        <div class="col-md-6 cash-only">
                            <label for="change_amount" class="form-label">Vuelto</label>
                            <div class="input-group">
                                <span class="input-group-text">S/</span>
                                <input type="text" class="form-control bg-light" id="change_amount" value="0.00" readonly>
                            </div>
                            <div class="form-text">Se calcula automáticamente y no afecta el recibo pendiente.</div>
                        </div>

                        <div class="col-md-3">
                            <label for="payment_date" class="form-label">Fecha de pago <span class="text-danger">*</span></label>
                            <input type="date" class="form-control @error('payment_date') is-invalid @enderror" id="payment_date" name="payment_date" value="{{ old('payment_date', $paymentDate) }}" required>
                            @error('payment_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="payment_time" class="form-label">Hora de pago <span class="text-muted">(opcional)</span></label>
                            <input type="time" class="form-control @error('payment_time') is-invalid @enderror" id="payment_time" name="payment_time" value="{{ old('payment_time', date('H:i')) }}">
                            @error('payment_time')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="reference" class="form-label">Referencia / Nro. operación <span class="text-danger reference-required d-none">*</span></label>
                            <input type="text" class="form-control @error('reference') is-invalid @enderror" id="reference" name="reference" value="{{ old('reference') }}" placeholder="Ej. Yape 123456, transferencia, voucher">
                            @error('reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Obligatorio para pagos digitales (Yape, Transferencia, etc.).</div>
                        </div>

                        <div class="col-md-12">
                            <label for="notes" class="form-label">Comentario o saludo para el Recibo <span class="text-muted">(Impreso en el ticket)</span></label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" placeholder="Ej. ¡Felices Fiestas Patrias le desea NoreTEL!, u otra observación de cobranza">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Este mensaje o nota se imprimirá destacado en el pie del Recibo de Pago.</div>
                        </div>
                    </div>

                    <div class="payment-help mt-4">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        El sistema aplicará el monto al recibo pendiente. Si el pago es en efectivo, el vuelto queda guardado como detalle de caja, no como abono adicional.
                    </div>

                    <div class="mt-4 text-end">
                        <a href="{{ $invoice ? route('billing.invoices.show', $invoice->id) : route('billing.payments.index') }}" class="btn btn-secondary me-2">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </a>
                        <button type="button" class="btn btn-outline-info me-2" id="btn-preview-receipt" data-bs-toggle="modal" data-bs-target="#previewReceiptModal">
                            <i class="fas fa-eye me-1"></i> Previsualizar Recibo
                        </button>
                        <button type="submit" class="btn btn-primary" id="submit-payment">
                            <i class="fas fa-save me-1"></i> Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <aside>
        <div class="card payment-card mb-4" id="invoice-info-card" @if(!$invoice) style="display: none;" @endif>
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice text-primary me-2"></i> Información del recibo
                </h5>
            </div>
            <div class="card-body">
                <h6 id="invoice-number">
                    @if($invoice) {{ $invoice->invoice_number }} @else Número de recibo @endif
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
                        <small class="text-muted">Fecha de Vencimiento</small>
                        <div id="due-date">
                            @if($invoice) {{ $invoice->due_date->format('d/m/Y') }} @else -- @endif
                        </div>
                    </div>
                </div>

                <div class="payment-summary-box">
                    <div class="payment-summary-row">
                        <span>Total del recibo</span>
                        <strong id="total-amount">@if($invoice) S/ {{ number_format($invoice->total_amount, 2) }} @else S/ 0.00 @endif</strong>
                    </div>
                    <div class="payment-summary-row">
                        <span>Pagado antes</span>
                        <strong id="paid-amount">@if($invoice) S/ {{ number_format($invoice->paid_amount, 2) }} @else S/ 0.00 @endif</strong>
                    </div>
                    <div class="payment-summary-row total">
                        <span>Pendiente</span>
                        <strong id="pending-amount">@if($invoice) S/ {{ number_format($invoice->pending_amount, 2) }} @else S/ 0.00 @endif</strong>
                    </div>
                    <div class="payment-summary-row">
                        <span>Descuento</span>
                        <strong id="summary-discount">S/ {{ number_format((float) old('discount_amount', 0), 2) }}</strong>
                    </div>
                    <div class="payment-summary-row">
                        <span>Monto a aplicar</span>
                        <strong id="summary-applied">S/ {{ number_format((float) old('amount', $selectedPendingAmount), 2) }}</strong>
                    </div>
                    <div class="payment-summary-row">
                        <span>Recibido</span>
                        <strong id="summary-received">S/ 0.00</strong>
                    </div>
                    <div class="payment-summary-row change">
                        <span>Vuelto</span>
                        <strong id="summary-change">S/ 0.00</strong>
                    </div>
                </div>

                @if($invoice && $invoice->status == 'pending' && $periodIsOverdue($invoice))
                <div class="alert alert-danger mt-3 mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i> Recibo vencido. Corresponde a un período anterior al mes actual.
                </div>
                @endif
            </div>
        </div>

        <div class="card payment-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-shield-alt text-primary me-2"></i> Reglas de cobranza
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i> El monto aplicado no debe superar el importe pendiente.
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i> En efectivo, registre cuánto entregó el cliente.
                    </li>
                    <li>
                        <i class="fas fa-check-circle text-success me-2"></i> En pagos digitales, ingrese referencia para conciliación.
                    </li>
                    <li class="mt-2">
                        <i class="fas fa-check-circle text-success me-2"></i> En Yape, el pago queda pendiente hasta validar WhatsApp y la notificación del receptor.
                    </li>
                </ul>
            </div>
        </div>
    </aside>
</div>

<!-- MODAL VISTA PREVIA TICKET TÉRMICO (A NIVEL RAÍZ DE LA PÁGINA) -->
<div class="modal fade" id="previewReceiptModal" tabindex="-1" aria-labelledby="previewReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="previewReceiptModalLabel"><i class="fas fa-receipt me-2 text-warning"></i>Vista Previa de Recibo de Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-light text-center py-4">
                <div class="ticket-preview-box text-start mx-auto p-3 shadow-sm bg-white border rounded" style="width: 280px; font-family: 'Courier New', Courier, monospace; font-size: 11px; line-height: 1.35; color: #000;">
                    <div class="text-center fw-bold fs-5 mb-0">NORETEL</div>
                    <div class="text-center fw-bold text-uppercase fs-6">RECIBO DE PAGO</div>
                    <div class="text-center small">Internet Service Provider</div>
                    <div class="text-center small">Cusco, Perú</div>
                    <div class="border-top border-dashed my-2"></div>
                    <div class="d-flex justify-content-between"><span>COMPROBANTE:</span><strong>#PREVIA</strong></div>
                    <div class="d-flex justify-content-between"><span>FECHA:</span><strong id="prev-date">--/--/----</strong></div>
                    <div class="d-flex justify-content-between"><span>HORA:</span><strong id="prev-time">--:--</strong></div>
                    <div class="border-top border-dashed my-2"></div>
                    <div class="fw-bold text-uppercase mb-1">CLIENTE</div>
                    <div>{{ $contract->customer->full_name ?? ($invoice->contract->customer->full_name ?? ($selectedCustomer->full_name ?? ($customer->full_name ?? 'Cliente NoreTEL'))) }}</div>
                    <div>DNI/RUC: {{ $contract->customer->identity_document ?? ($invoice->contract->customer->identity_document ?? ($selectedCustomer->identity_document ?? ($customer->identity_document ?? '-'))) }}</div>
                    <div>Contrato: #{{ $contract->id ?? ($invoice->contract->id ?? ($selectedContract->id ?? '-')) }}</div>
                    <div class="border-top border-dashed my-2"></div>
                    <div class="fw-bold text-uppercase mb-1">PAGO</div>
                    <div class="d-flex justify-content-between"><span>Método:</span><strong id="prev-method">Efectivo</strong></div>
                    <div class="d-flex justify-content-between"><span>Operación:</span><strong id="prev-ref">-</strong></div>
                    <div class="d-flex justify-content-between"><span>Estado:</span><strong class="text-success">PAGADO</strong></div>
                    <div class="border-top border-dashed my-2"></div>
                    <div class="fw-bold text-uppercase mb-1">DETALLE</div>
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong id="prev-concept">Servicio de Internet</strong>
                            <div class="small text-muted" id="prev-period">{{ $cleanPeriodHelper($invoice?->billing_period) ?: 'Mes actual' }}</div>
                        </div>
                        <strong id="prev-amount">S/ 0.00</strong>
                    </div>
                    <div class="border-top border-dashed my-2"></div>
                    <div class="d-flex justify-content-between fw-bold fs-6"><span>TOTAL RECIBIDO:</span><strong class="text-success" id="prev-total">S/ 0.00</strong></div>
                    <div id="prev-note-container" class="d-none">
                        <div class="border-top border-dashed my-2"></div>
                        <div class="text-center fw-bold small text-primary" id="prev-note"></div>
                    </div>
                    <div class="border-top border-dashed my-2"></div>
                    <div class="text-center small text-muted mt-2">Gracias por confiar en NoreTEL</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar Vista Previa</button>
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
    const discountInput = document.getElementById('discount_amount');
    const receivedInput = document.getElementById('received_amount');
    const changeInput = document.getElementById('change_amount');
    const methodSelect = document.getElementById('payment_method');
    const paymentModeInputs = document.querySelectorAll('input[name="payment_mode"]');
    const multiMonthPanel = document.getElementById('multi-month-panel');
    const monthsHiddenInput = document.getElementById('months_to_pay');
    const referenceRequired = document.querySelector('.reference-required');
    const cashRequired = document.querySelector('.cash-required');
    const cashFields = document.querySelectorAll('.cash-only');
    const yapeFields = document.querySelectorAll('.yape-only');
    const discountFields = document.querySelectorAll('.single-discount-only');
    const discountReasonFields = document.querySelectorAll('.discount-reason-only');
    const summaryApplied = document.getElementById('summary-applied');
    const summaryDiscount = document.getElementById('summary-discount');
    const summaryReceived = document.getElementById('summary-received');
    const summaryChange = document.getElementById('summary-change');
    const submitPayment = document.getElementById('submit-payment');
    const btnAddFuture = document.getElementById('btn-add-future');
    const futurePeriodsList = document.getElementById('future-periods-list');
    const advancePaymentBasePeriod = @json($advancePaymentBasePeriod ?? null);

    const formatMoney = (value) => `S/ ${(Number(value) || 0).toFixed(2)}`;
    const isCashMethod = () => methodSelect && methodSelect.value === 'cash';
    const isYapeMethod = () => methodSelect && methodSelect.value === 'yape';
    const currentPaymentMode = () => document.querySelector('input[name="payment_mode"]:checked')?.value || 'single';
    let previousPaymentMode = currentPaymentMode();
    
    const monthlyAmount = () => {
        return parseFloat(monthsHiddenInput?.dataset.monthlyAmount || '0') || 0;
    };

    function currentAmount() {
        return parseFloat(amountInput.value) || 0;
    }

    function currentReceived() {
        return parseFloat(receivedInput.value) || 0;
    }

    function currentDiscount() {
        return parseFloat(discountInput?.value) || 0;
    }

    function periodDiscountForItem(item, showValidation = false) {
        const input = item?.querySelector('.period-discount-input');
        const feedback = item?.querySelector('.period-discount-feedback');
        const pending = parseFloat(item?.dataset.amount || '0') || 0;
        const rawValue = (input?.value || '').trim();
        const parsed = rawValue === '' ? 0 : Number(rawValue.replace(',', '.'));
        const invalid = rawValue !== '' && (!Number.isFinite(parsed) || parsed < 0 || parsed > pending);

        if (input && showValidation) {
            const message = invalid ? `Ingrese un descuento entre S/ 0.00 y ${formatMoney(pending)}.` : '';
            input.setCustomValidity(message);
            input.classList.toggle('is-invalid', invalid);

            if (feedback) {
                feedback.textContent = message;
                feedback.classList.toggle('d-none', !invalid);
            }
        }

        return invalid ? 0 : parsed;
    }

    function currentMultiDiscount() {
        return Array.from(document.querySelectorAll('.period-checkbox:checked'))
            .reduce((total, checkbox) => {
                return total + periodDiscountForItem(checkbox.closest('.period-item'));
            }, 0);
    }

    function pendingAmount() {
        return parseFloat(amountInput.dataset.pending || '0') || 0;
    }

    function applyDiscountToAmount() {
        if (!discountInput || currentPaymentMode() !== 'single') return;

        const pending = pendingAmount();
        const discount = currentPaymentMode() === 'multi_month' ? currentMultiDiscount() : currentDiscount();
        const discountedAmount = Math.max(pending - discount, 0);

        discountInput.max = pending.toFixed(2);
        amountInput.value = discountedAmount.toFixed(2);
        document.getElementById('amount-feedback').textContent = discount > 0
            ? `Pendiente: ${formatMoney(pending)}. Descuento: ${formatMoney(discount)}. Pago: ${formatMoney(discountedAmount)}.`
            : `Importe pendiente de este recibo: ${formatMoney(pending)}`;

        const discountFeedback = document.getElementById('discount-feedback');
        if (discountFeedback) {
            discountFeedback.textContent = `Máximo permitido: ${formatMoney(pending)}.`;
        }
    }

    function updateLiveDistribution() {
        const liveContainer = document.getElementById('live-distribution-container');
        const liveDetails = document.getElementById('live-distribution-details');
        const overpaymentOption = document.getElementById('overpayment-credit-option');

        if (!liveContainer || !liveDetails) return;

        if (currentPaymentMode() !== 'multi_month') {
            liveContainer.style.display = 'none';
            return;
        }

        const checkedBoxes = Array.from(document.querySelectorAll('.period-checkbox:checked'));
        if (checkedBoxes.length === 0) {
            liveContainer.style.display = 'none';
            return;
        }

        let inputAmount = currentAmount();
        let remaining = inputAmount;
        let detailsHtml = '<ul class="list-unstyled mb-0">';
        let totalDebt = 0;

        checkedBoxes.forEach(cb => {
            const item = cb.closest('.period-item');
            if (!item) return;

            const periodName = item.dataset.period || 'Período';
            const pending = parseFloat(item.dataset.amount || '0') || 0;
            const discount = periodDiscountForItem(item);
            const netPending = Math.max(pending - discount, 0);

            totalDebt += netPending;

            if (remaining <= 0) {
                detailsHtml += `<li class="text-muted mb-1"><i class="fas fa-circle text-secondary me-1" style="font-size:7px;"></i> <strong>${periodName}:</strong> Queda pendiente S/ ${netPending.toFixed(2)}</li>`;
            } else if (remaining >= netPending - 0.009) {
                detailsHtml += `<li class="text-success mb-1"><i class="fas fa-check-circle me-1"></i> <strong>${periodName}:</strong> S/ ${netPending.toFixed(2)} <span class="badge bg-success text-white">Pagado 100%</span></li>`;
                remaining = Math.max(remaining - netPending, 0);
            } else {
                const partialPaid = remaining;
                const partialPending = netPending - partialPaid;
                detailsHtml += `<li class="text-warning mb-1"><i class="fas fa-adjust me-1"></i> <strong>${periodName}:</strong> Abonado S/ ${partialPaid.toFixed(2)} <span class="badge bg-warning text-dark">Pago parcial</span> · <span class="text-danger fw-bold">Saldo restante: S/ ${partialPending.toFixed(2)}</span></li>`;
                remaining = 0;
            }
        });

        detailsHtml += '</ul>';

        let overpayment = 0;
        const received = isCashMethod() ? currentReceived() : 0;
        const effectiveTotal = Math.max(inputAmount, received);

        if (effectiveTotal > totalDebt + 0.009) {
            overpayment = effectiveTotal - totalDebt;
            if (overpaymentOption) {
                overpaymentOption.style.display = '';
                const saveCredit = document.getElementById('save_overpayment_as_credit')?.checked;
                if (saveCredit) {
                    detailsHtml += `<div class="mt-2 text-primary fw-bold p-2 bg-white rounded border border-info"><i class="fas fa-wallet me-1"></i> Excedente de S/ ${overpayment.toFixed(2)} se guardará como <u>Saldo a Favor</u> del cliente.</div>`;
                } else {
                    detailsHtml += `<div class="mt-2 text-success fw-bold p-2 bg-white rounded border border-success"><i class="fas fa-hand-holding-usd me-1"></i> Excedente de S/ ${overpayment.toFixed(2)} para entregar como Vuelto (o active la casilla abajo para abonar como Saldo a Favor).</div>`;
                }
            }
        } else if (overpaymentOption) {
            overpaymentOption.style.display = 'none';
        }

        liveDetails.innerHTML = detailsHtml;
        liveContainer.style.display = '';
    }

    function recalculate() {
        const amount = currentAmount();
        const discount = currentDiscount();
        const received = isCashMethod() ? currentReceived() : 0;
        const saveCredit = document.getElementById('save_overpayment_as_credit')?.checked;
        const change = (isCashMethod() && !saveCredit) ? Math.max(received - amount, 0) : 0;

        changeInput.value = change.toFixed(2);
        summaryApplied.textContent = formatMoney(amount);
        summaryDiscount.textContent = formatMoney(discount);
        summaryReceived.textContent = formatMoney(received);
        summaryChange.textContent = saveCredit && received > amount ? 'S/ 0.00 (Saldo a Favor)' : formatMoney(change);

        updateLiveDistribution();
    }

    function toggleMethodFields() {
        const cash = isCashMethod();
        const yape = isYapeMethod();
        const requiresPaymentDetails = currentAmount() > 0;

        cashFields.forEach((field) => {
            field.style.display = cash ? '' : 'none';
        });

        yapeFields.forEach((field) => {
            field.style.display = yape ? '' : 'none';
        });

        receivedInput.required = cash && requiresPaymentDetails;
        if (!cash) {
            receivedInput.value = '';
        } else if (cash && (!receivedInput.value || parseFloat(receivedInput.value) <= 0)) {
            if (currentAmount() > 0) {
                receivedInput.value = currentAmount().toFixed(2);
            }
        }

        if (cashRequired) {
            cashRequired.classList.toggle('d-none', !cash);
        }

        if (referenceRequired) {
            referenceRequired.classList.toggle('d-none', cash || !methodSelect.value || !requiresPaymentDetails);
        }

        methodSelect.required = requiresPaymentDetails;

        if (submitPayment) {
            submitPayment.innerHTML = '<i class="fas fa-save me-1"></i> Registrar Pago';
        }

        recalculate();
    }

    function toggleDiscountFields() {
        const singleAvailable = currentPaymentMode() === 'single';
        const multiAvailable = currentPaymentMode() === 'multi_month';

        discountFields.forEach((field) => {
            field.style.display = singleAvailable ? '' : 'none';
        });

        discountReasonFields.forEach((field) => {
            field.style.display = (singleAvailable || multiAvailable) ? '' : 'none';
        });

        if (!singleAvailable && discountInput) {
            discountInput.value = '0';
        }

        if (discountInput) {
            discountInput.disabled = !singleAvailable;
        }

        document.querySelectorAll('.period-discount-input').forEach((input) => {
            const checkbox = input.closest('.period-item')?.querySelector('.period-checkbox');
            input.disabled = !multiAvailable || !checkbox?.checked;
        });

        if (!singleAvailable && !multiAvailable) {
            document.getElementById('discount_reason').value = '';
        }

        applyDiscountToAmount();
        recalculate();
    }

    function updatePaymentModeCards() {
        paymentModeInputs.forEach((input) => {
            const card = input.closest('.payment-mode-card');
            if (card) {
                if (input.checked) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            }
        });
    }

    function bindPeriodEvents() {
        const items = document.querySelectorAll('.period-item');
        items.forEach(item => {
            if (item.dataset.hasListener) return;
            item.dataset.hasListener = 'true';

            item.addEventListener('click', function(e) {
                if (e.target.classList.contains('period-checkbox') || e.target.closest('.btn-remove-future') || e.target.closest('.period-discount-wrapper')) {
                    return;
                }
                
                const checkbox = this.querySelector('.period-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    handleCheckboxFIFO(checkbox);
                }
            });

            const checkbox = item.querySelector('.period-checkbox');
            if (checkbox) {
                checkbox.addEventListener('change', function() {
                    handleCheckboxFIFO(this);
                });
            }
        });
    }

    function handleCheckboxFIFO(checkbox) {
        const index = parseInt(checkbox.dataset.index, 10);
        const isChecked = checkbox.checked;
        const allCheckboxes = Array.from(document.querySelectorAll('.period-checkbox'));
        
        if (isChecked) {
            allCheckboxes.forEach(cb => {
                const cbIdx = parseInt(cb.dataset.index, 10);
                if (cbIdx <= index) {
                    cb.checked = true;
                    cb.closest('.period-item')?.classList.add('selected');
                }
            });
        } else {
            allCheckboxes.forEach(cb => {
                const cbIdx = parseInt(cb.dataset.index, 10);
                if (cbIdx >= index) {
                    cb.checked = false;
                    cb.closest('.period-item')?.classList.remove('selected');
                }
            });
        }

        updateCalculationsFromChecklist();
    }

    function updateCalculationsFromChecklist(formatDiscounts = true) {
        if (currentPaymentMode() !== 'multi_month') return;

        const checkedBoxes = Array.from(document.querySelectorAll('.period-checkbox:checked'));
        let totalPending = 0;
        let totalDiscount = 0;

        document.querySelectorAll('.period-discount-input').forEach((input) => {
            const checkbox = input.closest('.period-item')?.querySelector('.period-checkbox');
            input.disabled = isYapeMethod() || !checkbox?.checked;

            if (input.disabled) {
                input.setCustomValidity('');
                input.classList.remove('is-invalid');
                input.closest('.period-item')?.querySelector('.period-discount-feedback')?.classList.add('d-none');
            }
        });
        
        checkedBoxes.forEach(cb => {
            const item = cb.closest('.period-item');
            if (item) {
                const discountInput = item.querySelector('.period-discount-input');
                const pending = parseFloat(item.dataset.amount || '0');
                const discount = periodDiscountForItem(item, true);

                if (discountInput && formatDiscounts && discountInput.value !== '' && !discountInput.validationMessage) {
                    discountInput.value = discount.toFixed(2);
                }

                totalPending += pending;
                totalDiscount += discount;
            }
        });

        const totalMonths = checkedBoxes.length;
        
        const totalAmount = Math.max(totalPending - totalDiscount, 0);
        amountInput.value = totalAmount.toFixed(2);
        monthsHiddenInput.value = totalMonths;

        document.getElementById('amount-feedback').textContent = totalDiscount > 0
            ? `Deuda de ${totalMonths} mes(es): ${formatMoney(totalPending)}. Descuento: ${formatMoney(totalDiscount)}. A cobrar: ${formatMoney(totalAmount)}.`
            : `Monto calculado para ${totalMonths} mes(es): ${formatMoney(totalAmount)}`;
        
        recalculate();
    }

    if (btnAddFuture) {
        btnAddFuture.addEventListener('click', function() {
            const allItems = Array.from(document.querySelectorAll('.period-item'));
            let lastPeriodRaw = '';
            let nextIndex = 0;

            if (allItems.length > 0) {
                const lastItem = allItems[allItems.length - 1];
                lastPeriodRaw = lastItem.dataset.periodRaw;
                nextIndex = parseInt(lastItem.dataset.index, 10) + 1;
            } else if (advancePaymentBasePeriod) {
                lastPeriodRaw = advancePaymentBasePeriod;
            }

            const nextPeriodRaw = getNextPeriodString(lastPeriodRaw);
            const nextPeriodLiteral = getLiteralMonth(nextPeriodRaw);
            const price = monthlyAmount();

            const futureHtml = `
                <div class="list-group-item period-item d-flex align-items-center justify-content-between p-3 border rounded mb-2 shadow-sm selected" 
                     data-type="future" 
                     data-index="${nextIndex}" 
                     data-amount="${price}" 
                     data-period="${nextPeriodLiteral}"
                     data-period-raw="${nextPeriodRaw}">
                    <div class="d-flex align-items-center">
                        <div class="form-check me-3">
                            <input class="form-check-input period-checkbox" type="checkbox" id="chk-period-${nextIndex}" data-index="${nextIndex}" checked>
                        </div>
                        <div>
                            <div class="fw-bold text-dark">${nextPeriodLiteral}</div>
                            <div class="text-muted small">Pago Adelantado (Prepago)</div>
                        </div>
                    </div>
                    <div class="text-end d-flex align-items-center">
                        <span class="badge bg-success-soft text-success me-2">Adelantado</span>
                        <strong class="text-primary font-monospace me-2">S/ ${price.toFixed(2)}</strong>
                        <button type="button" class="btn-close btn-remove-future" aria-label="Close" style="font-size: 0.75rem;"></button>
                    </div>
                </div>
            `;

            futurePeriodsList.insertAdjacentHTML('beforeend', futureHtml);
            
            bindPeriodEvents();
            
            const newFutureItem = futurePeriodsList.lastElementChild;
            const removeBtn = newFutureItem.querySelector('.btn-remove-future');
            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    const currentIdx = parseInt(newFutureItem.dataset.index, 10);
                    const allFutureItems = Array.from(futurePeriodsList.querySelectorAll('.period-item'));
                    
                    allFutureItems.forEach(item => {
                        const itemIdx = parseInt(item.dataset.index, 10);
                        if (itemIdx >= currentIdx) {
                            item.remove();
                        }
                    });

                    updateCalculationsFromChecklist();
                });
            }

            const newCheckbox = newFutureItem.querySelector('.period-checkbox');
            if (newCheckbox) {
                handleCheckboxFIFO(newCheckbox);
            }
        });
    }

    function getNextPeriodString(lastPeriod) {
        if (!lastPeriod) {
            const now = new Date();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const y = now.getFullYear();
            return `${m}/${y}`;
        }
        const parts = lastPeriod.split('/');
        let month = parseInt(parts[0], 10);
        let year = parseInt(parts[1], 10);
        month++;
        if (month > 12) {
            month = 1;
            year++;
        }
        return `${String(month).padStart(2, '0')}/${year}`;
    }

    function getLiteralMonth(periodRaw) {
        if (!periodRaw) return '';
        const parts = periodRaw.split('/');
        const monthNum = parseInt(parts[0], 10);
        const year = parts[1];
        const monthNames = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        return `${monthNames[monthNum - 1]} ${year}`;
    }

    function togglePaymentMode() {
        const mode = currentPaymentMode();
        const isMultiMonth = mode === 'multi_month';
        const modeChanged = previousPaymentMode !== mode;

        if (modeChanged) {
            receivedInput.value = '';
            changeInput.value = '0.00';
        }

        updatePaymentModeCards();

        if (multiMonthPanel) {
            multiMonthPanel.style.display = isMultiMonth ? '' : 'none';
        }

        if (invoiceSelect) {
            invoiceSelect.required = !isMultiMonth;
        }

        if (isMultiMonth) {
            amountInput.setAttribute('readonly', 'readonly');
            updateCalculationsFromChecklist();
        } else {
            amountInput.removeAttribute('readonly');
            if (invoiceSelect) {
                if (invoiceSelect.value) {
                    invoiceSelect.dispatchEvent(new Event('change'));
                } else {
                    amountInput.value = '';
                    document.getElementById('amount-feedback').textContent = 'Seleccione un recibo pendiente para sugerir el importe pendiente.';
                }
            } else {
                // El recibo está preseleccionado a nivel de PHP (invoiceSelect es null)
                const pendingAmount = parseFloat(amountInput.dataset.pending || '0');
                if (pendingAmount > 0) {
                    if (!amountInput.value) {
                        amountInput.value = pendingAmount.toFixed(2);
                    }
                    document.getElementById('amount-feedback').textContent = `Importe pendiente de este recibo: ${formatMoney(pendingAmount)}`;
                }
            }
        }

        toggleDiscountFields();
        toggleMethodFields();
        previousPaymentMode = mode;
    }

    function renderSingleInvoiceCard(invoiceNumber, customerName, amount, isOverdue, period) {
        const container = document.getElementById('single-invoice-display-container');
        if (!container) return;

        if (!invoiceNumber) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        const badgeClass = isOverdue ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning';
        const badgeText = isOverdue ? 'Vencido' : 'Pendiente';

        container.innerHTML = `
            <div class="single-invoice-card d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-bold text-dark">${invoiceNumber}</div>
                    <div class="text-muted small">Período: ${period} | Cliente: ${customerName}</div>
                </div>
                <div class="text-end">
                    <span class="badge ${badgeClass} me-2">${badgeText}</span>
                    <strong class="text-primary font-monospace">${formatMoney(amount)}</strong>
                </div>
            </div>
        `;
        container.style.display = 'block';
    }

    if (invoiceSelect) {
        invoiceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            if (selectedOption && selectedOption.value) {
                const pendingAmount = parseFloat(selectedOption.dataset.amount) || 0;
                const totalAmount = parseFloat(selectedOption.dataset.total) || pendingAmount;
                const paidAmount = parseFloat(selectedOption.dataset.paid) || 0;
                const customerName = selectedOption.dataset.customer || '';
                const invoiceNum = selectedOption.textContent.split(' - ')[0].trim();
                
                const textOption = selectedOption.textContent;
                const periodMatch = textOption.match(/\d{2}\/\d{4}/);
                const period = periodMatch ? periodMatch[0] : new Date().toLocaleDateString('es-PE', { month: '2-digit', year: 'numeric' });
                
                const isOverdue = textOption.toLowerCase().includes('vencido') || textOption.toLowerCase().includes('overdue');

                if (currentPaymentMode() === 'single') {
                    amountInput.value = pendingAmount.toFixed(2);
                    amountInput.dataset.pending = pendingAmount.toFixed(2);
                    applyDiscountToAmount();
                    
                    renderSingleInvoiceCard(invoiceNum, customerName, pendingAmount, isOverdue, period);
                }

                document.getElementById('invoice-info-card').style.display = 'block';
                document.getElementById('invoice-number').textContent = invoiceNum;
                document.getElementById('customer-name').textContent = customerName;
                document.getElementById('total-amount').textContent = formatMoney(totalAmount);
                document.getElementById('paid-amount').textContent = formatMoney(paidAmount);
                document.getElementById('pending-amount').textContent = formatMoney(pendingAmount);

                toggleMethodFields();
            } else {
                renderSingleInvoiceCard(null);
                document.getElementById('invoice-info-card').style.display = 'none';
            }
        });
    }

    amountInput.addEventListener('input', () => {
        recalculate();
        toggleMethodFields();
    });
    discountInput.addEventListener('input', () => {
        applyDiscountToAmount();
        toggleMethodFields();
    });
    document.querySelectorAll('.period-discount-input').forEach((input) => {
        input.addEventListener('input', () => updateCalculationsFromChecklist(false));
        input.addEventListener('blur', () => updateCalculationsFromChecklist(true));
    });
    receivedInput.addEventListener('input', recalculate);
    const btnExactAmount = document.getElementById('btn-exact-amount');
    if (btnExactAmount) {
        btnExactAmount.addEventListener('click', function() {
            if (receivedInput && amountInput) {
                receivedInput.value = (parseFloat(amountInput.value) || 0).toFixed(2);
                recalculate();
            }
        });
    }
    methodSelect.addEventListener('change', () => {
        toggleMethodFields();
        toggleDiscountFields();
    });
    
    paymentModeInputs.forEach((input) => {
        input.addEventListener('change', togglePaymentMode);
    });

    const previewModalEl = document.getElementById('previewReceiptModal');
    if (previewModalEl) {
        document.body.appendChild(previewModalEl);
        previewModalEl.addEventListener('show.bs.modal', function () {
            const rawDate = document.getElementById('payment_date')?.value || '';
            let formattedDate = rawDate;
            if (rawDate) {
                const parts = rawDate.split('-');
                if (parts.length === 3) formattedDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
            document.getElementById('prev-date').textContent = formattedDate || '--/--/----';
            document.getElementById('prev-time').textContent = document.getElementById('payment_time')?.value || '--:--';
            
            const methodSel = document.getElementById('payment_method');
            const selectedMethodText = methodSel && methodSel.selectedIndex >= 0 && methodSel.value ? methodSel.options[methodSel.selectedIndex].text : 'Efectivo';
            document.getElementById('prev-method').textContent = selectedMethodText || 'Efectivo';
            document.getElementById('prev-ref').textContent = document.getElementById('reference')?.value || '-';

            const amountVal = parseFloat(amountInput.value) || 0;
            document.getElementById('prev-amount').textContent = 'S/ ' + amountVal.toFixed(2);
            document.getElementById('prev-total').textContent = 'S/ ' + amountVal.toFixed(2);

            let currentPeriod = 'Mes actual';
            if (currentPaymentMode() === 'single') {
                const selectedOpt = invoiceSelect && invoiceSelect.selectedIndex >= 0 ? invoiceSelect.options[invoiceSelect.selectedIndex] : null;
                if (selectedOpt && selectedOpt.dataset && selectedOpt.dataset.period) {
                    currentPeriod = selectedOpt.dataset.period;
                } else {
                    const infoCardPeriod = document.querySelector('.single-invoice-card .text-muted');
                    if (infoCardPeriod) {
                        const match = infoCardPeriod.textContent.match(/Período:\s*([^|]+)/i);
                        if (match) currentPeriod = match[1].trim();
                    }
                }
            } else {
                const checkedPeriods = Array.from(document.querySelectorAll('.period-checkbox:checked'))
                    .map(cb => {
                        const periodLabelEl = cb.closest('.period-item')?.querySelector('.fw-bold, .period-label');
                        return periodLabelEl ? periodLabelEl.textContent.trim() : '';
                    })
                    .filter(Boolean);
                if (checkedPeriods.length > 0) {
                    currentPeriod = checkedPeriods.join(', ');
                }
            }
            const prevPeriodEl = document.getElementById('prev-period');
            if (prevPeriodEl) prevPeriodEl.textContent = currentPeriod;

            const noteVal = (document.getElementById('notes')?.value || '').trim();
            const noteContainer = document.getElementById('prev-note-container');
            const noteText = document.getElementById('prev-note');
            if (noteVal) {
                noteText.textContent = noteVal;
                noteContainer.classList.remove('d-none');
            } else {
                noteContainer.classList.add('d-none');
            }
        });
    }

    amountInput?.addEventListener('input', recalculate);
    receivedInput?.addEventListener('input', recalculate);
    document.getElementById('save_overpayment_as_credit')?.addEventListener('change', recalculate);

    updatePaymentModeCards();
    bindPeriodEvents();
    toggleMethodFields();
    togglePaymentMode();
    recalculate();

    if (invoiceSelect && invoiceSelect.value) {
        invoiceSelect.dispatchEvent(new Event('change'));
    }
});
</script>
@endpush
