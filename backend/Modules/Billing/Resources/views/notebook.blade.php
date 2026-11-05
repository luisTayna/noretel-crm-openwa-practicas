@extends('core::layouts.master')

@section('title', 'Cuaderno de Pagos')
@section('page-title', 'Cuaderno de Pagos')

@section('content')
@if(request()->get('mode') === 'invoice-global-audit')
    @php
        $invoiceAudit = app(\Modules\Billing\Services\InvoiceGlobalAuditService::class)->audit();
    @endphp
    @include('billing::period-repair.invoice-global-audit')
@elseif(request()->get('mode') === 'contract-duplicates')
    @php
        $duplicateContractService = app(\Modules\Contract\Services\DuplicateContractRepairService::class);
        $gregorioCase = $duplicateContractService->previewGregorio();
        $confirmedPhysicalDeletionCases = $duplicateContractService->previewConfirmedPhysicalDeletionCases();
        $duplicateContractCandidates = $duplicateContractService->auditCandidates();
    @endphp
    @include('billing::period-repair.contract-duplicates')
@elseif(request()->get('mode') === 'multimonth-surgical')
    @php
        $surgicalCases = app(\Modules\Billing\Services\MultiMonthPaymentSurgicalRepairService::class)->previewCases();
    @endphp
    @include('billing::period-repair.multimonth-surgical')
@elseif(request()->get('mode') === 'period-repair')
    @php
        $periodRepairService = app(\Modules\Billing\Services\BillingPeriodRepairService::class);
        $groups = $periodRepairService->duplicateGroups();
        $safeGroups = $groups->where('safe', true);
        $manualGroups = $groups->where('safe', false);
    @endphp
    @include('billing::period-repair.content')
@else
<style>
    /* Estilos Premium Glassmorphism */
    .kpi-card {
        border: none;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .kpi-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.9);
        border: none;
        border-radius: 15px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
    }
    .notebook-table th {
        background-color: #f8f9fc !important;
        font-weight: 700;
        text-transform: uppercase;
        color: #4e73df;
        border-bottom: 2px solid #e3e6f0;
    }
    .notebook-table td {
        vertical-align: middle;
        border-bottom: 1px solid #e3e6f0;
    }
    .badge-interactive {
        cursor: pointer;
        transition: transform 0.15s ease, opacity 0.15s ease;
    }
    .badge-interactive:hover {
        transform: scale(1.08);
        opacity: 0.95;
    }
    .cell-empty {
        cursor: pointer;
        color: #ccc;
        transition: background-color 0.2s;
    }
    .cell-empty:hover {
        background-color: #f1f3f9;
        color: #4e73df;
    }
    .toggle-details {
        transition: transform 0.2s;
    }
    .expanded .toggle-details {
        transform: rotate(90deg);
    }
</style>

<!-- Tarjetas KPI Superiores -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card h-100 py-2 border-left-success border-5 border-success border-top-0 border-right-0 border-bottom-0">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Recaudado ({{ $data['year'] }})</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">S/ {{ number_format($data['kpis']['total_collected'], 2) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-coins fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card h-100 py-2 border-left-danger border-5 border-danger border-top-0 border-right-0 border-bottom-0">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Deuda Pendiente Total</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">S/ {{ number_format($data['kpis']['total_pending'], 2) }}</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hand-holding-usd fa-2x text-danger"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card h-100 py-2 border-left-info border-5 border-info border-top-0 border-right-0 border-bottom-0">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Efectividad de Cobro</div>
                        <div class="row no-gutters align-items-center">
                            <div class="col-auto mr-3">
                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800">{{ $data['kpis']['collection_rate'] }}%</div>
                            </div>
                            <div class="col">
                                <div class="progress progress-sm mr-2">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: {{ $data['kpis']['collection_rate'] }}%" aria-valuenow="{{ $data['kpis']['collection_rate'] }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chart-line fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card kpi-card h-100 py-2 border-left-warning border-5 border-warning border-top-0 border-right-0 border-bottom-0">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Recibos Auditados</div>
                        <div class="h6 mb-0 font-weight-bold text-gray-800">
                            <span class="badge bg-success" title="Pagados">{{ $data['kpis']['counts']['paid'] }}</span>
                            <span class="badge bg-warning" title="Pendientes">{{ $data['kpis']['counts']['pending'] }}</span>
                            <span class="badge bg-danger" title="Vencidos">{{ $data['kpis']['counts']['overdue'] }}</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-invoice fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros y Opciones de Tabla -->
<div class="card shadow border-0 mb-4 glass-card">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center justify-content-between">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="clientSearch" class="form-control bg-light border-0" placeholder="Buscar por cliente, DNI, celular, plan...">
                </div>
            </div>
            <div class="col-md-3 text-md-center">
                <div class="form-check form-switch d-inline-block">
                    <input class="form-check-input" type="checkbox" id="debtorsOnlySwitch">
                    <label class="form-check-label fw-semibold text-muted" for="debtorsOnlySwitch">Ver solo con Deuda</label>
                </div>
            </div>
            <div class="col-md-4 text-md-end d-flex align-items-center justify-content-md-end gap-2">
                <form action="{{ route('billing.notebook') }}" method="GET" class="d-inline-flex align-items-center">
                    <label for="year" class="me-2 fw-semibold text-muted mb-0">Año:</label>
                    <select class="form-select form-select-sm w-auto" name="year" id="year" onchange="this.form.submit()">
                        @php $currentYear = Carbon\Carbon::now()->year; @endphp
                        @for($y = $currentYear; $y >= 2024; $y--)
                            <option value="{{ $y }}" {{ $data['year'] == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </form>
                <button class="btn btn-sm btn-outline-secondary" onclick="exportTableToCSV('cuaderno-pagos-{{ $data['year'] }}.csv')">
                    <i class="fas fa-download me-1"></i> Exportar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Cuaderno de Pagos -->
<div class="card shadow border-0 mb-4 glass-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 notebook-table" id="notebook-table" style="font-size: 11px;">
                <thead>
                    <tr>
                        <th style="padding-left: 20px;">Cliente</th>
                        <th>DNI/RUC</th>
                        <th>Celular</th>
                        <th>Plan</th>
                        @foreach($data['months'] as $month)
                            <th class="text-center" style="min-width: 80px;">{{ substr($month, 0, 3) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['matrix'] as $row)
                    @php
                        // Determinar si el cliente tiene alguna deuda
                        $hasDebt = false;
                        foreach($row['months'] as $info) {
                            if ($info && ($info['status'] === 'pending' || $info['status'] === 'partial' || $info['is_overdue'])) {
                                $hasDebt = true;
                                break;
                            }
                        }
                    @endphp
                    <tr class="client-tr {{ $hasDebt ? 'has-debt-row' : 'no-debt-row' }}" id="client-row-{{ $row['contract_id'] }}" data-fullname="{{ mb_strtolower($row['full_name']) }}" data-dni="{{ $row['identity_document'] }}" data-phone="{{ $row['phone'] }}" data-plan="{{ mb_strtolower($row['plan_name']) }}">
                        <td style="padding-left: 20px;">
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-link text-muted p-0 me-2" onclick="toggleDetails({{ $row['contract_id'] }})" id="btn-toggle-{{ $row['contract_id'] }}">
                                    <i class="fas fa-plus-circle toggle-details" id="icon-{{ $row['contract_id'] }}"></i>
                                </button>
                                <div>
                                    <div class="fw-bold text-dark">{{ $row['full_name'] }}</div>
                                    <small class="text-muted">ID: {{ $row['contract_id'] }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $row['identity_document'] }}</td>
                        <td>
                            @if($row['phone'] !== '-')
                                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $row['phone']) }}" target="_blank" class="text-success text-decoration-none fw-semibold">
                                    <i class="fab fa-whatsapp me-1 text-success"></i>{{ $row['phone'] }}
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $row['plan_name'] }}</div>
                            <div class="text-muted small">S/ {{ number_format($row['monthly_price'], 2) }}</div>
                        </td>
                        @for($m = 1; $m <= 12; $m++)
                            @php $info = $row['months'][$m] ?? null; @endphp
                            <td class="text-center">
                                @if($info)
                                    @php
                                        // Armar tooltip detallado
                                        $tooltip = "Recibo N° " . $info['invoice_number'] . "\n";
                                        $tooltip .= "Emitido: " . $info['issue_date'] . "\n";
                                        $tooltip .= "Vence: " . $info['due_date'] . "\n";
                                        $tooltip .= "Total: S/ " . number_format($info['amount'], 2) . "\n";
                                        $tooltip .= "Pendiente: S/ " . number_format($info['pending'], 2);
                                    @endphp
                                    @if($info['status'] === 'paid')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle py-2 px-1 text-decoration-none d-block mx-1 fw-bold badge-interactive" title="{{ $tooltip }}" onclick="openQuickActionModal({{ json_encode($row) }}, {{ json_encode($info) }}, '{{ $data['months'][$m - 1] }}')">
                                            <i class="fas fa-check-circle me-1"></i>S/{{ number_format($info['amount'], 0) }}
                                        </span>
                                    @elseif($info['status'] === 'partial')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle py-2 px-1 text-decoration-none d-block mx-1 fw-bold badge-interactive" title="{{ $tooltip }}" onclick="openQuickActionModal({{ json_encode($row) }}, {{ json_encode($info) }}, '{{ $data['months'][$m - 1] }}')">
                                            <i class="fas fa-adjust me-1"></i>S/{{ number_format($info['amount'] - $info['pending'], 0) }}
                                        </span>
                                    @elseif($info['is_overdue'])
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-2 px-1 text-decoration-none d-block mx-1 fw-bold badge-interactive" title="{{ $tooltip }}" onclick="openQuickActionModal({{ json_encode($row) }}, {{ json_encode($info) }}, '{{ $data['months'][$m - 1] }}')">
                                            <i class="fas fa-times-circle me-1"></i>S/{{ number_format($info['pending'], 0) }}
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle py-2 px-1 text-decoration-none d-block mx-1 fw-bold badge-interactive" title="{{ $tooltip }}" onclick="openQuickActionModal({{ json_encode($row) }}, {{ json_encode($info) }}, '{{ $data['months'][$m - 1] }}')">
                                            <i class="fas fa-exclamation-circle me-1"></i>S/{{ number_format($info['pending'], 0) }}
                                        </span>
                                    @endif
                                @else
                                    <!-- Celda vacía permitiendo Generar Recibo del mes -->
                                    <span class="text-muted fw-light d-block py-1 cell-empty rounded" title="Generar Recibo para {{ $data['months'][$m - 1] }} {{ $data['year'] }}" onclick="openGenerateModal({{ $row['contract_id'] }}, '{{ $row['full_name'] }}', '{{ $data['months'][$m - 1] }} {{ $data['year'] }}')">
                                        -
                                    </span>
                                @endif
                            </td>
                        @endfor
                    </tr>
                    
                    <!-- Fila expansible para detalles adicionales del contrato -->
                    <tr class="details-row d-none bg-light" id="details-row-{{ $row['contract_id'] }}">
                        <td colspan="16" class="p-3">
                            <div class="row mx-0 bg-white p-3 rounded shadow-sm border border-light">
                                <div class="col-md-4 mb-2">
                                    <h6 class="text-primary font-weight-bold mb-2"><i class="fas fa-info-circle me-1"></i>Detalles del Cliente</h6>
                                    <p class="mb-1"><strong>Titular:</strong> {{ $row['full_name'] }}</p>
                                    <p class="mb-1"><strong>DNI/RUC:</strong> {{ $row['identity_document'] }}</p>
                                    <p class="mb-1"><strong>WhatsApp:</strong> 
                                        @if($row['phone'] !== '-')
                                            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $row['phone']) }}" target="_blank" class="text-success text-decoration-none">
                                                {{ $row['phone'] }} <i class="fab fa-whatsapp ms-1"></i>
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </p>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <h6 class="text-primary font-weight-bold mb-2"><i class="fas fa-wifi me-1"></i>Plan Contratado</h6>
                                    <p class="mb-1"><strong>Plan:</strong> {{ $row['plan_name'] }}</p>
                                    <p class="mb-1"><strong>Velocidad:</strong> {{ $row['speed'] }}</p>
                                    <p class="mb-1"><strong>Tecnología:</strong> {{ $row['technology'] }}</p>
                                    <p class="mb-1"><strong>Mensualidad:</strong> S/ {{ number_format($row['monthly_price'], 2) }}</p>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <h6 class="text-primary font-weight-bold mb-2"><i class="fas fa-map-marker-alt me-1"></i>Instalación y Notas</h6>
                                    <p class="mb-1"><strong>Dirección:</strong> {{ $row['address'] }}</p>
                                    <p class="mb-1"><strong>Contrato:</strong> <span class="badge bg-secondary">{{ ucfirst($row['status']) }}</span></p>
                                    <p class="mb-0 text-muted"><strong>Notas:</strong> {{ $row['notes'] }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="16" class="text-center py-5 text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3 text-light-gray"></i>
                            <h5>No se encontraron contratos registrados.</h5>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: Centro de Acciones y Cobro Rápido -->
<div class="modal fade" id="quickActionModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="quickActionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold text-dark" id="quickActionModalLabel"><i class="fas fa-cash-register me-2 text-primary"></i>Centro de Acciones y Cobranza</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <!-- Sección de Información del Recibo -->
                    <div class="col-md-5 border-end pr-md-4">
                        <h6 class="text-primary font-weight-bold mb-3"><i class="fas fa-receipt me-1"></i>Detalles del Recibo</h6>
                        <table class="table table-sm table-borderless style-sm mb-4">
                            <tr>
                                <th class="text-muted ps-0">Cliente:</th>
                                <td id="qa-client-name" class="text-dark fw-bold">-</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Recibo:</th>
                                <td id="qa-invoice-number" class="text-dark">-</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Período:</th>
                                <td id="qa-period" class="text-dark">-</td>
                            </tr>
                            <tr>
                                <th class="text-muted ps-0">Monto Adeudado:</th>
                                <td class="text-danger fw-bold h5 mb-0" id="qa-pending-amount">-</td>
                            </tr>
                        </table>

                        <!-- Acciones Rápidas Externas -->
                        <div class="d-grid gap-2 mb-3">
                            <a href="#" id="qa-whatsapp-link" target="_blank" class="btn btn-success">
                                <i class="fab fa-whatsapp me-2"></i>Notificar por WhatsApp
                            </a>
                            <a href="#" id="qa-detail-link" class="btn btn-outline-primary">
                                <i class="fas fa-external-link-alt me-2"></i>Ver Recibo Completo
                            </a>
                        </div>
                    </div>

                    <!-- Sección de Registro de Pago -->
                    <div class="col-md-7 ps-md-4">
                        <div id="qa-payment-completed-msg" class="alert alert-success text-center py-4 d-none">
                            <i class="fas fa-check-circle fa-3x mb-2"></i>
                            <h5>Este recibo ya se encuentra PAGADO.</h5>
                            <p class="mb-0 text-muted">No se requieren acciones de cobro pendientes.</p>
                        </div>

                        <div id="qa-payment-form-section">
                            <form action="{{ route('billing.payments.store') }}" method="POST" id="quick-payment-form">
                                @csrf
                                <input type="hidden" name="invoice_id" id="form-invoice-id">
                                <input type="hidden" name="payment_mode" id="form-payment-mode" value="single">
                                <input type="hidden" name="months_to_pay" id="form-months-to-pay" value="1">
                                <input type="hidden" name="contract_id" id="form-contract-id">
                                <input type="hidden" name="payment_date" value="{{ date('Y-m-d') }}">
                                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                                <h6 class="text-primary font-weight-bold mb-3"><i class="fas fa-money-bill-wave me-1"></i>Registrar Pago</h6>

                                <!-- Cobro Multimes Alternativo (Checkbox List) -->
                                <div id="multi-month-section" class="d-none mb-3 bg-light p-3 rounded border border-light">
                                    <label class="form-label fw-bold text-dark mb-1"><i class="fas fa-layer-group me-1"></i>Cobro en Lote (Múltiples Meses)</label>
                                    <p class="text-muted mb-2" style="font-size: 10px;">Marca los meses que deseas cobrar en esta sola transacción:</p>
                                    <div id="multi-month-checkboxes" class="d-flex flex-column gap-2" style="max-height: 120px; overflow-y: auto;">
                                        <!-- Generado dinámicamente en JS -->
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="payment_method" class="form-label fw-bold text-muted">Método de Pago</label>
                                    <select class="form-select" id="payment_method" name="payment_method" required onchange="togglePaymentInputs(this.value)">
                                        <option value="cash" selected>Efectivo</option>
                                        <option value="yape">Yape</option>
                                        <option value="transfer">Transferencia Bancaria</option>
                                        <option value="deposit">Depósito</option>
                                        <option value="other">Otro</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="amount" class="form-label fw-bold text-muted">Monto a Cobrar</label>
                                    <input type="number" step="0.01" class="form-control fw-bold h5 bg-light" id="amount" name="amount" readonly required>
                                </div>

                                <!-- Inputs de Efectivo -->
                                <div id="cash-inputs">
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <label for="received_amount" class="form-label fw-bold text-muted">Monto Recibido</label>
                                            <input type="number" step="0.01" class="form-control text-primary fw-bold" id="received_amount" name="received_amount" oninput="calculateChange()">
                                        </div>
                                        <div class="col-6 mb-3">
                                            <label for="change_amount" class="form-label fw-bold text-muted">Vuelto</label>
                                            <input type="text" class="form-control text-muted fw-bold bg-light" id="change_amount" readonly value="S/ 0.00">
                                        </div>
                                    </div>
                                </div>

                                <!-- Inputs de Banco / Referencia -->
                                <div id="bank-inputs" class="d-none mb-3">
                                    <label for="reference" class="form-label fw-bold text-muted" id="ref-label">Número de Operación</label>
                                    <input type="text" class="form-control" id="reference" name="reference">
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label fw-bold text-muted">Notas Internas</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Opcional..."></textarea>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary" id="btn-submit-payment">
                                        <i class="fas fa-save me-2"></i>Procesar Pago
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Generación de Recibo del Mes -->
<div class="modal fade" id="generateInvoiceModal" tabindex="-1" aria-labelledby="generateInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold text-dark" id="generateInvoiceModalLabel"><i class="fas fa-plus me-2 text-success"></i>Emitir Recibo Mensual</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('billing.invoices.generate') }}" method="POST">
                @csrf
                <input type="hidden" name="contract_id" id="gen-contract-id">
                <input type="hidden" name="billing_period" id="gen-billing-period">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body p-4 text-center">
                    <i class="fas fa-file-signature fa-4x text-success mb-3"></i>
                    <h5 class="text-dark fw-bold mb-2">¿Confirmas la generación del recibo?</h5>
                    <p class="text-muted">Se emitirá el recibo mensual para el cliente <strong id="gen-client-name" class="text-dark"></strong> para el período <strong id="gen-period-label" class="text-primary"></strong>.</p>
                </div>
                <div class="modal-footer bg-light justify-content-center border-0 gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-cogs me-1"></i>Generar Recibo</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    // Inicializar Bootstrap Modales
    let quickActionModal;
    let generateInvoiceModal;

    document.addEventListener('DOMContentLoaded', function() {
        // Mover los modales al body para evitar problemas de stacking context / backdrop
        const quickModalEl = document.getElementById('quickActionModal');
        const genModalEl = document.getElementById('generateInvoiceModal');
        if (quickModalEl) document.body.appendChild(quickModalEl);
        if (genModalEl) document.body.appendChild(genModalEl);

        // Asignar instancias de modales
        quickActionModal = new bootstrap.Modal(quickModalEl);
        generateInvoiceModal = new bootstrap.Modal(genModalEl);

        // Configurar buscador de clientes en tiempo real
        const clientSearch = document.getElementById('clientSearch');
        clientSearch.addEventListener('keyup', filterTable);

        // Configurar switch de deudores
        const debtorsOnlySwitch = document.getElementById('debtorsOnlySwitch');
        debtorsOnlySwitch.addEventListener('change', filterTable);

        // Validar formulario de pago al enviar
        const formPayment = document.getElementById('quick-payment-form');
        formPayment.addEventListener('submit', function(e) {
            const method = document.getElementById('payment_method').value;
            const amount = parseFloat(document.getElementById('amount').value);
            
            if (method === 'cash') {
                const received = parseFloat(document.getElementById('received_amount').value);
                if (isNaN(received) || received < amount) {
                    e.preventDefault();
                    alert('El monto recibido no puede ser menor al monto a cobrar.');
                }
            } else if (method !== 'yape') {
                const reference = document.getElementById('reference').value.trim();
                if (reference === '') {
                    e.preventDefault();
                    alert('Debe ingresar el número de referencia u operación para este método de pago.');
                }
            }
        });
    });

    // Expandir/Colapsar detalles adicionales
    function toggleDetails(contractId) {
        const row = document.getElementById('client-row-' + contractId);
        const detailsRow = document.getElementById('details-row-' + contractId);
        const icon = document.getElementById('icon-' + contractId);

        if (detailsRow.classList.contains('d-none')) {
            detailsRow.classList.remove('d-none');
            row.classList.add('expanded');
            icon.classList.replace('fa-plus-circle', 'fa-minus-circle');
        } else {
            detailsRow.classList.add('d-none');
            row.classList.remove('expanded');
            icon.classList.replace('fa-minus-circle', 'fa-plus-circle');
        }
    }

    // Filtrar tabla por búsqueda y switch de deudores
    function filterTable() {
        const query = document.getElementById('clientSearch').value.toLowerCase().trim();
        const showDebtorsOnly = document.getElementById('debtorsOnlySwitch').checked;
        const rows = document.querySelectorAll('.client-tr');

        rows.forEach(row => {
            const contractId = row.id.replace('client-row-', '');
            const detailsRow = document.getElementById('details-row-' + contractId);
            
            const fullName = row.getAttribute('data-fullname');
            const dni = row.getAttribute('data-dni');
            const phone = row.getAttribute('data-phone');
            const plan = row.getAttribute('data-plan');

            const matchesQuery = fullName.includes(query) || dni.includes(query) || phone.includes(query) || plan.includes(query);
            const matchesDebtor = !showDebtorsOnly || row.classList.contains('has-debt-row');

            if (matchesQuery && matchesDebtor) {
                row.classList.remove('d-none');
            } else {
                row.classList.add('d-none');
                // Ocultar expansible si se ocultó el padre
                if (detailsRow) detailsRow.classList.add('d-none');
                row.classList.remove('expanded');
                const icon = document.getElementById('icon-' + contractId);
                if (icon) icon.classList.replace('fa-minus-circle', 'fa-plus-circle');
            }
        });
    }

    // Abrir modal para generar recibo
    function openGenerateModal(contractId, clientName, periodLabel) {
        document.getElementById('gen-contract-id').value = contractId;
        document.getElementById('gen-billing-period').value = periodLabel;
        document.getElementById('gen-client-name').innerText = clientName;
        document.getElementById('gen-period-label').innerText = periodLabel;
        
        generateInvoiceModal.show();
    }

    // Alternar campos según método de pago en el modal
    function togglePaymentInputs(method) {
        const cashSection = document.getElementById('cash-inputs');
        const bankSection = document.getElementById('bank-inputs');
        const refLabel = document.getElementById('ref-label');
        const refInput = document.getElementById('reference');
        const receivedInput = document.getElementById('received_amount');

        if (method === 'cash') {
            cashSection.classList.remove('d-none');
            bankSection.classList.add('d-none');
            refInput.removeAttribute('required');
            receivedInput.setAttribute('required', 'required');
            calculateChange();
        } else {
            cashSection.classList.add('d-none');
            bankSection.classList.remove('d-none');
            receivedInput.removeAttribute('required');
            
            if (method === 'yape') {
                refLabel.innerText = "Código de Operación Yape (Opcional)";
                refInput.removeAttribute('required');
            } else {
                refLabel.innerText = "Número de Referencia/Operación (Requerido)";
                refInput.setAttribute('required', 'required');
            }
        }
    }

    // Calcular vuelto de pago
    function calculateChange() {
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const received = parseFloat(document.getElementById('received_amount').value) || 0;
        const changeDiv = document.getElementById('change_amount');

        if (received >= amount) {
            const change = received - amount;
            changeDiv.value = "S/ " + change.toFixed(2);
            changeDiv.classList.replace('text-danger', 'text-success');
        } else {
            changeDiv.value = "Monto insuficiente";
            changeDiv.classList.replace('text-success', 'text-danger');
        }
    }

    // Abrir modal de cobro y acciones rápidas
    function openQuickActionModal(contractRow, invoiceInfo, monthName) {
        // Detalles informativos
        document.getElementById('qa-client-name').innerText = contractRow.full_name;
        document.getElementById('qa-invoice-number').innerText = invoiceInfo.invoice_number;
        document.getElementById('qa-period').innerText = monthName + " " + {{ $data['year'] }};
        document.getElementById('qa-pending-amount').innerText = "S/ " + parseFloat(invoiceInfo.pending).toFixed(2);

        // Enlace WhatsApp
        const waPhone = contractRow.phone.replace(/\D+/g, '');
        const cleanPhone = waPhone.length === 9 ? '51' + waPhone : waPhone;
        const msg = "Hola " + contractRow.full_name.split(' ')[0] + ", te saludamos de NoreTEL. Tu recibo de " + monthName + " por S/ " + parseFloat(invoiceInfo.pending).toFixed(2) + " se encuentra pendiente. Por favor realiza tu pago a la brevedad. Gracias.";
        document.getElementById('qa-whatsapp-link').href = "https://wa.me/" + cleanPhone + "?text=" + encodeURIComponent(msg);

        // Enlace Detalle
        document.getElementById('qa-detail-link').href = "/billing/invoices/" + invoiceInfo.invoice_id;

        // Configuración de formulario
        document.getElementById('form-invoice-id').value = invoiceInfo.invoice_id;
        document.getElementById('form-contract-id').value = contractRow.contract_id;
        document.getElementById('form-payment-mode').value = "single";
        document.getElementById('form-months-to-pay').value = "1";
        document.getElementById('amount').value = parseFloat(invoiceInfo.pending).toFixed(2);
        document.getElementById('received_amount').value = parseFloat(invoiceInfo.pending).toFixed(2);

        // Limpiar inputs
        document.getElementById('reference').value = "";
        document.getElementById('notes').value = "";

        // Ajustar visualización si está pagado
        const formSection = document.getElementById('qa-payment-form-section');
        const completedMsg = document.getElementById('qa-payment-completed-msg');

        if (invoiceInfo.status === 'paid') {
            formSection.classList.add('d-none');
            completedMsg.classList.remove('d-none');
            document.getElementById('qa-pending-amount').innerText = "S/ 0.00 (Pagado)";
            document.getElementById('qa-pending-amount').classList.replace('text-danger', 'text-success');
        } else {
            formSection.classList.remove('d-none');
            completedMsg.classList.add('d-none');
            document.getElementById('qa-pending-amount').classList.replace('text-success', 'text-danger');
            
            // Cargar y evaluar sección multimes (pagos en lote)
            setupMultiMonthSection(contractRow, invoiceInfo);
        }

        togglePaymentInputs(document.getElementById('payment_method').value);
        quickActionModal.show();
    }

    // Configurar sección multimes en base a deudas del cliente
    function setupMultiMonthSection(contractRow, activeInvoiceInfo) {
        const multiSection = document.getElementById('multi-month-section');
        const checkboxesDiv = document.getElementById('multi-month-checkboxes');
        
        // Buscar todos los meses que están pendientes/vencidos del cliente en el cuaderno
        const unpaidMonths = [];
        const monthsList = @json($data['months']);
        
        for (let m = 1; m <= 12; m++) {
            const inf = contractRow.months[m];
            if (inf && inf.status !== 'paid') {
                unpaidMonths.push({
                    monthNum: m,
                    monthName: monthsList[m - 1],
                    invoice_id: inf.invoice_id,
                    pending: parseFloat(inf.pending),
                    invoice_number: inf.invoice_number
                });
            }
        }

        // Si hay 2 o más meses con deudas, mostramos la sección multimes
        if (unpaidMonths.length >= 2) {
            multiSection.classList.remove('d-none');
            checkboxesDiv.innerHTML = "";

            unpaidMonths.forEach((item, index) => {
                const isCurrent = (item.invoice_id === activeInvoiceInfo.invoice_id);
                
                const div = document.createElement('div');
                div.className = "form-check";
                div.innerHTML = `
                    <input class="form-check-input multi-pay-chk" type="checkbox" value="${item.pending}" 
                           id="chk-month-${item.monthNum}" data-invoiceid="${item.invoice_id}" 
                           ${isCurrent ? 'checked disabled' : ''} onchange="recalculateMultiPay()">
                    <label class="form-check-label text-dark" for="chk-month-${item.monthNum}">
                        <strong>${item.monthName}:</strong> S/ ${item.pending.toFixed(2)} <small class="text-muted">(${item.invoice_number})</small>
                    </label>
                `;
                checkboxesDiv.appendChild(div);
            });
        } else {
            multiSection.classList.add('d-none');
        }
    }

    // Recalcular montos al seleccionar/deseleccionar meses de pago masivo
    function recalculateMultiPay() {
        const chks = document.querySelectorAll('.multi-pay-chk');
        let total = 0;
        let count = 0;

        chks.forEach(chk => {
            if (chk.checked) {
                total += parseFloat(chk.value);
                count++;
            }
        });

        // Actualizar monto
        document.getElementById('amount').value = total.toFixed(2);
        document.getElementById('received_amount').value = total.toFixed(2);

        // Si es mayor a 1, activar flujo multimes
        const formPayMode = document.getElementById('form-payment-mode');
        const formMonthsToPay = document.getElementById('form-months-to-pay');
        const formInvoiceId = document.getElementById('form-invoice-id');

        if (count > 1) {
            formPayMode.value = "multi_month";
            formMonthsToPay.value = count;
            
            // En multimes de PaymentController, el invoice_id puede ir nulo si hay contract_id
            formInvoiceId.value = ""; 
        } else {
            formPayMode.value = "single";
            formMonthsToPay.value = "1";
            
            // Restaurar id de factura única activa
            const activeChk = document.querySelector('.multi-pay-chk[disabled]');
            if (activeChk) {
                formInvoiceId.value = activeChk.getAttribute('data-invoiceid');
            }
        }

        calculateChange();
    }

    // Función para exportar tabla a CSV
    window.exportTableToCSV = function(filename) {
        let csv = [];
        const rows = document.querySelectorAll('#notebook-table tr');

        for (let i = 0; i < rows.length; i++) {
            // Saltamos las filas colapsibles de detalles adicionales
            if (rows[i].classList.contains('details-row')) {
                continue;
            }

            const row = [], cols = rows[i].querySelectorAll('td, th');

            for (let j = 0; j < cols.length; j++) {
                let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/(\s\s)/gm, ' ').trim();
                data = data.replace(/"/g, '""');
                row.push('"' + data + '"');
            }

            csv.push(row.join(','));
        }

        const csvFile = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const downloadLink = document.createElement('a');

        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = 'none';

        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    };
</script>
@endpush
