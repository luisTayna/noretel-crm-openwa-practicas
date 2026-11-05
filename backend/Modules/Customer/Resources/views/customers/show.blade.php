@extends('core::layouts.master')

@section('title', 'Detalle de Cliente')
@section('page-title', 'Detalle de Cliente')

@section('content')
@php
    $money = fn($value) => 'S/ ' . number_format((float) $value, 2);
    $statusLabels = [
        'active' => 'Activo', 'inactive' => 'Inactivo', 'suspended' => 'Suspendido', 'cancelled' => 'Cancelado',
        'paid' => 'Pagada', 'pending' => 'Pendiente', 'partial' => 'Parcial', 'overdue' => 'Vencida', 'void' => 'Anulada',
    ];
    $statusColors = [
        'active' => 'success', 'inactive' => 'secondary', 'suspended' => 'warning', 'cancelled' => 'danger',
        'paid' => 'success', 'pending' => 'warning', 'partial' => 'info', 'overdue' => 'danger', 'void' => 'secondary',
    ];
    $primaryAddress = $customer->addresses->firstWhere('is_primary', true) ?: $customer->addresses->first();
    // Compatibilidad con controladores antiguos retenidos por OPcache en cPanel.
    $activeContracts = isset($activeContracts) ? $activeContracts : $contracts->where('status', 'active')->values();
    $activeInvoices = $invoices->whereNotIn('status', ['void', 'cancelled'])->values();
    $voidInvoices = $invoices->whereIn('status', ['void', 'cancelled'])->values();
    $billingSummary = array_merge($billingSummary, [
        'active_contract_count' => $activeContracts->count(),
        'registered_contract_count' => $contracts->count(),
        'historical_contract_count' => $contracts->count() - $activeContracts->count(),
        'active_invoice_count' => $activeInvoices->count(),
        'void_invoice_count' => $voidInvoices->count(),
    ]);

    $cleanPeriod = function ($billingPeriod) {
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
    $timelineDate = function ($value) {
        if (!$value) {
            return '-';
        }

        $date = \Carbon\Carbon::parse($value)->locale('es');
        $format = $date->format('H:i') === '00:00'
            ? 'j \\d\\e F \\d\\e Y'
            : 'j \\d\\e F \\d\\e Y \\a \\l\\a\\s H:i';

        return ucfirst($date->translatedFormat($format));
    };
@endphp

<style>
    .customer-hero { background: linear-gradient(135deg, #0b132b 0%, #1c2541 45%, #0284c7 100%); border-radius: 22px; color: #fff; padding: 1.6rem; box-shadow: 0 18px 40px rgba(11, 19, 43, 0.25); }
    .hero-glass-box { background: rgba(11, 19, 43, 0.45); border: 1px solid rgba(255, 255, 255, 0.16); border-radius: 16px; backdrop-filter: blur(8px); padding: 1.25rem; }
    .hero-debt-amount { font-size: 2.35rem; font-weight: 900; color: #fbbf24; text-shadow: 0 0 15px rgba(251, 191, 36, 0.35); }
    .hero-period-badge { background: rgba(251, 191, 36, 0.18); color: #fef08a; border: 1px solid rgba(251, 191, 36, 0.4); padding: 0.4rem 0.9rem; border-radius: 20px; font-weight: 700; font-size: 0.85rem; display: inline-block; }
    .customer-card { border: 0; border-radius: 18px; box-shadow: 0 12px 30px rgba(15, 23, 42, .08); overflow: hidden; }
    .customer-card .card-header { background: #fff; border-bottom: 1px solid #eef2f7; font-weight: 700; padding: 1rem 1.25rem; }
    .metric-card { background: #fff; border-radius: 18px; padding: 1.1rem; box-shadow: 0 10px 26px rgba(15, 23, 42, .08); min-height: 120px; }
    .metric-card .metric-icon { width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; background: #eff6ff; color: #2563eb; }
    .metric-value { font-size: 1.55rem; font-weight: 800; color: #111827; }
    .metric-label { color: #64748b; font-size: .85rem; }
    .info-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .72rem 0; border-bottom: 1px solid #f1f5f9; }
    .info-row:last-child { border-bottom: 0; }
    .info-row span:first-child { color: #64748b; font-weight: 700; }
    .timeline-item { position: relative; padding-left: 2.25rem; padding-bottom: 1rem; }
    .timeline-item:before { content: ''; position: absolute; left: .75rem; top: 1.7rem; bottom: 0; width: 1px; background: #e2e8f0; }
    .timeline-item:last-child:before { display: none; }
    .timeline-icon { position: absolute; left: 0; top: 0; width: 1.55rem; height: 1.55rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: .75rem; }
    .table-sm td, .table-sm th { vertical-align: middle; }
    .badge-missing { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; font-weight: 700; padding: 0.35rem 0.65rem; border-radius: 8px; font-size: 0.8rem; }
</style>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1 text-gray-800">{{ $customer->full_name }}</h1>
        <div class="text-muted">
            Cliente #{{ $customer->id }} · 
            @if($customer->identity_document)
                {{ $customer->identity_document }}
            @else
                <span class="badge-missing"><i class="fas fa-exclamation-circle me-1"></i> Sin documento</span>
            @endif
        </div>
    </div>
    <div class="action-btn-group mt-3 mt-sm-0">
        @if($pendingInvoices->isNotEmpty() && (Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['payments.create', 'payments.manage'])))
            @php
                $paymentCreateUrl = \Illuminate\Support\Facades\Route::has('billing.payments.customer.create')
                    ? route('billing.payments.customer.create', $customer->id)
                    : route('billing.payments.create', ['invoice_id' => $pendingInvoices->first()->id]);
            @endphp
            <a href="{{ $paymentCreateUrl }}" class="btn btn-sm btn-warning me-2">
                <i class="fas fa-cash-register"></i> Pago de servicios
            </a>
        @endif
        @can('create-contracts')
            <a href="{{ route('contract.contracts.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-success me-2">
                <i class="fas fa-plus-circle"></i> Agregar servicio
            </a>
        @endcan
        <a href="{{ route('operations.customers.flow', $customer->id) }}" class="btn btn-sm btn-info me-2 text-white"><i class="fas fa-route"></i> Flujo Operativo</a>
        @can('edit-customers')
            <a href="{{ route('customer.customers.edit', $customer->id) }}" class="action-btn primary" title="Editar Cliente"><i class="fas fa-edit"></i></a>
        @endcan
        @if($customer->active && auth()->user()->canEditInModule('customers'))
            <form id="deactivate-customer-{{ $customer->id }}" action="{{ route('customer.customers.deactivate', $customer->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="action-btn" title="Desactivar Cliente" onclick="return confirm('¿Desea desactivar este cliente y conservar su historial?');"><i class="fas fa-user-slash"></i></button>
            </form>
        @endif
        @if(auth()->user()->canDeleteInModule('customers'))
            @if(\Illuminate\Support\Facades\Route::has('customer.customers.merge'))
                <a href="{{ route('customer.customers.merge', $customer->id) }}" class="action-btn" title="Consolidar clientes duplicados"><i class="fas fa-object-group"></i></a>
            @endif
            <form action="{{ route('customer.customers.destroy', $customer->id) }}" method="POST" class="d-inline" data-customer-name="{{ $customer->full_name }}" data-contract-count="{{ $customer->contracts_count }}" onsubmit="return confirmCustomerDelete(this);">
                @csrf @method('DELETE')
                <input type="hidden" name="delete_deletable_contracts" value="0">
                <input type="hidden" name="force_delete_related_history" value="0">
                <button type="submit" class="action-btn" title="Eliminar Cliente"><i class="fas fa-trash"></i></button>
            </form>
        @endif
        <a href="{{ route('customer.customers.index') }}" class="action-btn" title="Volver"><i class="fas fa-arrow-left"></i></a>
    </div>
</div>

<div class="customer-hero mb-4">
    <div class="row align-items-center">
        <div class="col-lg-6">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="badge bg-{{ $customer->active ? 'success' : 'danger' }}">{{ $customer->active ? 'Cliente activo' : 'Cliente inactivo' }}</span>
                <span class="badge bg-light text-dark">{{ $customer->customer_type === 'business' ? 'Empresa' : 'Individual' }}</span>
                <span class="badge bg-light text-dark">{{ $customer->segment ?: 'Sin segmento' }}</span>
                @if($customer->bi_segment)
                    @php
                        $biBadges = [
                            'vip' => ['bg' => 'warning text-dark', 'icon' => 'fas fa-crown', 'label' => 'VIP / Excelente'],
                            'regular' => ['bg' => 'info text-white', 'icon' => 'fas fa-user-check', 'label' => 'Estable / Regular'],
                            'churn_risk' => ['bg' => 'danger text-white', 'icon' => 'fas fa-exclamation-triangle', 'label' => 'Riesgo de Churn'],
                            'new' => ['bg' => 'primary text-white', 'icon' => 'fas fa-seedling', 'label' => 'Nuevo en Observación'],
                        ];
                        $biInfo = $biBadges[$customer->bi_segment] ?? ['bg' => 'secondary text-white', 'icon' => 'fas fa-tag', 'label' => ucfirst($customer->bi_segment)];
                    @endphp
                    <span class="badge bg-{{ $biInfo['bg'] }}"><i class="{{ $biInfo['icon'] }} me-1"></i> BI: {{ $biInfo['label'] }}</span>
                @endif
            </div>
            <h2 class="h4 mb-2 text-white fw-bold">
                @if($activeContracts->count() > 1)
                    {{ $activeContracts->count() }} servicios activos
                @else
                    {{ $activeContract?->plan?->name ?: 'Sin plan activo registrado' }}
                @endif
            </h2>
            <p class="mb-0 text-white-50">
                @if($primaryAddress)
                    <i class="fas fa-map-marker-alt me-1 text-info"></i> {{ trim($primaryAddress->street . ' ' . $primaryAddress->number) }}, {{ $primaryAddress->city }}
                @else
                    <span class="badge-missing"><i class="fas fa-map-marker-alt me-1"></i> Sin dirección principal</span>
                @endif
            </p>
        </div>
        <div class="col-lg-6 mt-4 mt-lg-0">
            <div class="hero-glass-box">
                <div class="row align-items-center">
                    <div class="col-12 text-center text-lg-end mb-2">
                        <div class="metric-label text-white-50 text-uppercase fw-bold" style="font-size:0.78rem; letter-spacing:1.2px;">Deuda Pendiente de Cobro</div>
                        <div class="hero-debt-amount mb-1">
                            {{ $money($billingSummary['pending_total']) }}
                        </div>
                        @if($billingSummary['next_due_invoice'])
                            <div class="hero-period-badge shadow-sm">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Corresponde a: {{ $cleanPeriod($billingSummary['next_due_invoice']->billing_period) ?: 'Mes actual' }}
                                @if($billingSummary['next_due_invoice']->due_date)
                                    (Vence: {{ $billingSummary['next_due_invoice']->due_date->format('d/m/Y') }})
                                @endif
                            </div>
                        @else
                            <span class="badge bg-success text-white px-3 py-2 fw-bold shadow-sm" style="font-size: 0.85rem;">
                                <i class="fas fa-check-circle me-1"></i> Cliente al día / Sin deudas pendientes
                            </span>
                        @endif
                    </div>
                    <div class="col-12 mt-2 pt-2 border-top border-white-25">
                        <div class="d-flex justify-content-around text-center">
                            <div>
                                <div class="metric-label text-white-50 small">Saldo a favor</div>
                                <div class="h5 fw-bold mb-0 text-success" style="color: #34d399 !important;">{{ $money($billingSummary['credit_balance_total'] ?? 0) }}</div>
                            </div>
                            <div class="border-start border-white-25 ps-3">
                                <div class="metric-label text-white-50 small">Pagado histórico</div>
                                <div class="h5 fw-bold mb-0 text-white-75" style="color: #cbd5e1 !important;">{{ $money($billingSummary['paid_total']) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $currentYear = date('Y');
    $monthsList = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
    ];

    $spanishMonthsMap = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
    ];

    $parsePeriodKey = function ($rawPeriod) use ($spanishMonthsMap, $cleanPeriod) {
        $raw = trim((string)$rawPeriod);
        if ($raw === '') return null;

        $cleanedStr = $cleanPeriod($raw);
        $cleanLower = strtolower($cleanedStr);

        foreach ($spanishMonthsMap as $mName => $mNum) {
            if (str_contains($cleanLower, $mName)) {
                if (preg_match('/(20\d{2})/', $cleanLower, $yMatch)) {
                    return $yMatch[1] . '-' . sprintf('%02d', $mNum);
                }
            }
        }

        if (preg_match_all('/(\d{1,2})[\/-](\d{1,2})[\/-](20\d{2})/', $raw, $matches, PREG_SET_ORDER)) {
            $targetMatch = count($matches) > 1 ? $matches[1] : $matches[0];
            $month = (int) $targetMatch[2];
            $year = (int) $targetMatch[3];
            return $year . '-' . sprintf('%02d', $month);
        }

        if (preg_match('/(20\d{2})[\/-](\d{1,2})/', $raw, $m)) {
            return $m[1] . '-' . sprintf('%02d', (int)$m[2]);
        }

        return null;
    };

    $periodStatusMap = [];

    foreach ($invoices as $inv) {
        $periodKey = $parsePeriodKey($inv->billing_period)
            ?: ($inv->due_date ? $inv->due_date->format('Y-m') : ($inv->issue_date ? $inv->issue_date->format('Y-m') : null));

        if ($periodKey) {
            $periodStatusMap[$periodKey] = [
                'status' => $inv->status,
                'amount' => $inv->status === 'paid' ? $inv->total_amount : $inv->pending_amount,
                'invoice_id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'is_overdue' => $inv->status !== 'paid' && $inv->due_date && $inv->due_date->isPast(),
            ];
        }
    }

    $allPaymentsCollection = isset($payments) && count($payments) ? $payments : $recentPayments;
    foreach ($allPaymentsCollection as $payment) {
        if ($payment->status === 'completed') {
            $pPeriod = $payment->invoice?->billing_period ?: $payment->notes;
            $periodKey = $parsePeriodKey($pPeriod);

            if (!$periodKey && $payment->invoice) {
                $periodKey = $parsePeriodKey($payment->invoice->billing_period)
                    ?: ($payment->invoice->due_date ? $payment->invoice->due_date->format('Y-m') : ($payment->invoice->issue_date ? $payment->invoice->issue_date->format('Y-m') : null));
            }

            if ($periodKey) {
                $periodStatusMap[$periodKey] = [
                    'status' => 'paid',
                    'amount' => $payment->amount,
                    'invoice_id' => $payment->invoice_id,
                    'invoice_number' => $payment->invoice?->invoice_number,
                    'is_overdue' => false,
                ];
            }
        }
    }
@endphp

<!-- MATRIZ VISUAL DE COBRANZA POR MES DEL AÑO -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card customer-card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2 bg-light">
                <span><i class="fas fa-calendar-alt me-2 text-primary"></i><strong>Estado de Pagos por Mes del Servicio ({{ $currentYear }})</strong></span>
                <div class="small">
                    <span class="badge bg-success me-1">🟢 Pagado</span>
                    <span class="badge bg-danger me-1">🔴 Deuda</span>
                    <span class="badge bg-warning text-dark me-1">🟡 Por vencer</span>
                    <span class="badge bg-light text-dark border">⚪ Sin emisión</span>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-nowrap gap-2 overflow-auto pb-2" style="scrollbar-width: thin;">
                    @foreach($monthsList as $mNum => $mName)
                        @php
                            $mKey = sprintf('%s-%02d', $currentYear, $mNum);
                            $monthData = $periodStatusMap[$mKey] ?? null;
                            $mCurrentNum = (int)date('n');
                        @endphp
                        <div class="text-center p-2 rounded border shadow-sm" style="min-width: 95px; flex: 1; {{ $mNum == $mCurrentNum ? 'border: 2px solid #0284c7 !important; background: #f0f9ff;' : 'background: #ffffff;' }}">
                            <div class="fw-bold small mb-1 text-uppercase text-secondary" style="font-size:0.78rem;">{{ $mName }} {{ $currentYear }}</div>
                            @if($monthData)
                                @if($monthData['status'] === 'paid')
                                    <span class="badge bg-success w-100 py-1 mb-1">PAGADO</span>
                                    <div class="fw-bold text-success small">{{ $money($monthData['amount']) }}</div>
                                @elseif($monthData['is_overdue'])
                                    <span class="badge bg-danger w-100 py-1 mb-1">DEUDA</span>
                                    <div class="fw-bold text-danger small">{{ $money($monthData['amount']) }}</div>
                                @elseif(in_array($monthData['status'], ['pending', 'partial']))
                                    <span class="badge bg-warning text-dark w-100 py-1 mb-1">PENDIENTE</span>
                                    <div class="fw-bold text-warning small">{{ $money($monthData['amount']) }}</div>
                                @else
                                    <span class="badge bg-secondary w-100 py-1 mb-1">{{ strtoupper($monthData['status']) }}</span>
                                    <div class="small text-muted">{{ $money($monthData['amount']) }}</div>
                                @endif
                            @else
                                @if($mNum < $mCurrentNum)
                                    <span class="badge bg-light text-muted border w-100 py-1 mb-1">SIN EMISIÓN</span>
                                    <div class="small text-muted">-</div>
                                @elseif($mNum == $mCurrentNum)
                                    <span class="badge bg-info text-white w-100 py-1 mb-1">MES ACTUAL</span>
                                    <div class="small text-muted">-</div>
                                @else
                                    <span class="badge bg-light text-muted w-100 py-1 mb-1">FUTURO</span>
                                    <div class="small text-muted">-</div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FILA PRINCIPAL DE COBRANZA: ULTIMOS PAGOS Y FACTURAS PENDIENTES (PRIMER GOLPE DE VISTA) -->
<div class="row mb-4">
    <div class="col-lg-7 mb-4 mb-lg-0">
        <div class="card customer-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-money-bill-wave me-2 text-success"></i>Últimos pagos realizados</span>
                <a href="{{ route('billing.payments.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-primary">Ver todos los pagos</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Fecha</th><th>Mes pagado</th><th>Método</th><th>Estado</th><th class="text-end">Monto</th><th></th></tr></thead>
                        <tbody>
                            @forelse($recentPayments as $payment)
                                <tr>
                                    <td>{{ $payment->payment_date ? $payment->payment_date->format('d/m/Y') : '-' }}</td>
                                    <td><strong>{{ $cleanPeriod($payment->invoice?->billing_period) ?: 'No disponible' }}</strong></td>
                                    <td>{{ $payment->payment_method_name }}</td>
                                    <td><span class="badge bg-{{ $payment->status === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($payment->status) }}</span></td>
                                    <td class="text-end fw-bold text-success">{{ $money($payment->amount) }}</td>
                                    <td><a href="{{ route('billing.payments.show', $payment->id) }}" class="action-btn" title="Ver detalle"><i class="fas fa-eye"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No hay pagos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($paymentMethodsSummary->count())
                    <div class="mt-3 pt-2 border-top">
                        <h6 class="fw-bold small text-muted text-uppercase mb-2">Métodos de pago usados</h6>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($paymentMethodsSummary as $method)
                                <div class="bg-light rounded px-3 py-2 border small">
                                    <span class="text-muted me-1">{{ $method['label'] }} ({{ $method['count'] }}):</span>
                                    <strong class="text-dark">{{ $money($method['amount']) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card customer-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-exclamation-circle me-2 text-danger"></i>Facturas pendientes por cobrar</span>
                <span class="badge bg-danger">{{ $pendingInvoices->count() }}</span>
            </div>
            <div class="card-body">
                @forelse($pendingInvoices->take(6) as $invoice)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                        <div>
                            <a class="fw-bold" href="{{ route('billing.invoices.show', $invoice->id) }}">{{ $invoice->invoice_number ?: 'Factura #' . $invoice->id }}</a>
                            @if($invoice->billing_period)
                                <span class="badge bg-light text-dark ms-1">{{ $cleanPeriod($invoice->billing_period) }}</span>
                            @endif
                            <div class="small text-muted mt-1">
                                <i class="far fa-clock me-1"></i>Vence: {{ $invoice->due_date ? $invoice->due_date->format('d/m/Y') : '-' }}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="h5 fw-bold text-danger mb-1">{{ $money($invoice->pending_amount) }}</div>
                            <span class="badge bg-{{ $statusColors[$invoice->status] ?? 'secondary' }}">{{ $statusLabels[$invoice->status] ?? ucfirst($invoice->status) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-check-circle text-success fa-2x mb-2 d-block"></i>
                        Sin facturas pendientes de cobro.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<!-- INFORMACIÓN DEL CLIENTE, SERVICIOS Y RESUMEN DE COBRANZA (SUBIDOS PARA MEJOR VISIBILIDAD) -->
<div class="row mb-4">
    <div class="col-lg-4 mb-4 mb-lg-0">
        <div class="card customer-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user me-2 text-primary"></i>Información del cliente</span>
                @can('edit-customers')
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#quickEditCustomerModal">
                        <i class="fas fa-pencil-alt me-1"></i> Editar
                    </button>
                @endcan
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span>Email</span>
                    @if($customer->email)
                        <strong>{{ $customer->email }}</strong>
                    @else
                        @can('edit-customers')
                            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm py-1 px-2 fw-bold" data-bs-toggle="modal" data-bs-target="#quickEditCustomerModal" data-focus="quick_email">
                                <i class="fas fa-plus-circle me-1"></i> + Registrar Email
                            </button>
                        @else
                            <span class="badge-missing"><i class="fas fa-exclamation-circle me-1"></i> No registrado</span>
                        @endcan
                    @endif
                </div>
                <div class="info-row">
                    <span>Teléfono</span>
                    @if($customer->phone)
                        <strong>{{ $customer->phone }}</strong>
                    @else
                        @can('edit-customers')
                            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm py-1 px-2 fw-bold" data-bs-toggle="modal" data-bs-target="#quickEditCustomerModal" data-focus="quick_phone">
                                <i class="fas fa-plus-circle me-1"></i> + Registrar Teléfono
                            </button>
                        @else
                            <span class="badge-missing"><i class="fas fa-exclamation-circle me-1"></i> No registrado</span>
                        @endcan
                    @endif
                </div>
                <div class="info-row">
                    <span>Documento</span>
                    @if($customer->identity_document)
                        <strong>{{ $customer->identity_document }}</strong>
                    @else
                        @can('edit-customers')
                            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm py-1 px-2 fw-bold" data-bs-toggle="modal" data-bs-target="#quickEditCustomerModal" data-focus="quick_identity_document">
                                <i class="fas fa-plus-circle me-1"></i> + Registrar Documento
                            </button>
                        @else
                            <span class="badge-missing"><i class="fas fa-exclamation-circle me-1"></i> No registrado</span>
                        @endcan
                    @endif
                </div>
                <div class="info-row"><span>Puntaje crediticio</span><strong>{{ $customer->credit_score ?: 'No evaluado' }}</strong></div>
                <div class="info-row"><span>Registro</span><strong>{{ $customer->registration_date ? $customer->registration_date->format('d/m/Y') : 'No registrado' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4 mb-lg-0">
        <div class="card customer-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-wifi me-2 text-success"></i>Servicios del cliente</span>
                @can('create-contracts')
                    <a href="{{ route('contract.contracts.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-plus"></i> Nuevo
                    </a>
                @endcan
            </div>
            <div class="card-body">
                @forelse($contracts as $contract)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <a class="fw-bold" href="{{ route('contract.contracts.show', $contract->id) }}">
                                    Contrato #{{ $contract->id }}
                                </a>
                                <div class="fw-bold mt-1">{{ $contract->plan?->name ?: 'Sin plan' }}</div>
                            </div>
                            <span class="badge bg-{{ $statusColors[$contract->status] ?? 'secondary' }}">
                                {{ $statusLabels[$contract->status] ?? ucfirst(str_replace('_', ' ', $contract->status)) }}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small">
                            <span class="text-muted">Mensualidad</span>
                            <strong>{{ $money($contract->final_price) }}</strong>
                        </div>
                        @if($contract->assigned_ip || $contract->vlan)
                            <div class="small text-muted mt-1">
                                IP: {{ $contract->assigned_ip ?: 'Sin IP' }} · VLAN: {{ $contract->vlan ?: 'Sin VLAN' }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Este cliente todavía no tiene contrato registrado.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card customer-card h-100">
            <div class="card-header"><i class="fas fa-receipt me-2 text-warning"></i>Resumen de cobranza</div>
            <div class="card-body">
                <div class="info-row"><span>Último pago</span><strong>{{ $billingSummary['last_payment'] ? $money($billingSummary['last_payment']->amount) : 'Sin pagos' }}</strong></div>
                <div class="info-row"><span>Fecha último pago</span><strong>{{ $billingSummary['last_payment']?->payment_date ? $billingSummary['last_payment']->payment_date->format('d/m/Y') : 'No registrado' }}</strong></div>
                <div class="info-row"><span>Próximo vencimiento</span><strong>
                    @if($billingSummary['next_due_invoice']?->due_date)
                        @if($billingSummary['next_due_invoice']->due_date->isPast())
                            <span class="text-danger">{{ $billingSummary['next_due_invoice']->due_date->format('d/m/Y') }} (Vencido)</span>
                        @else
                            {{ $billingSummary['next_due_invoice']->due_date->format('d/m/Y') }}
                        @endif
                        @if($billingSummary['next_due_invoice']->billing_period)
                            <br><span class="text-muted small fw-normal">Período: {{ $cleanPeriod($billingSummary['next_due_invoice']->billing_period) }}</span>
                        @endif
                    @else
                        Sin deuda
                    @endif
                </strong></div>
                <div class="info-row"><span>Deuda vencida</span><strong class="text-danger">{{ $money($billingSummary['overdue_total']) }}</strong></div>
                <div class="info-row"><span>Saldo a favor total</span><strong class="text-success">{{ $money($billingSummary['credit_balance_total'] ?? 0) }}</strong></div>
                <div class="info-row"><span>Saldo contrato actual</span><strong class="text-success">{{ $money($billingSummary['credit_balance_active_contract'] ?? 0) }}</strong></div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @if($billingSummary['next_due_invoice'])
                        <a href="{{ route('billing.invoices.show', $billingSummary['next_due_invoice']->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-eye"></i> Ver factura pendiente</a>
                    @endif
                    @if((Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['payments.create', 'payments.manage'])) && \Illuminate\Support\Facades\Route::has('billing.customers.add-credit'))
                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addCreditModal">
                            <i class="fas fa-plus-circle"></i> Abonar saldo
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CARDS DE MÉTRICAS (BAJADAS DEBAJO DE INFORMACIÓN/SERVICIOS/RESUMEN) -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="metric-card"><div class="metric-icon mb-3"><i class="fas fa-file-contract"></i></div><div class="metric-value">{{ $billingSummary['active_contract_count'] }}</div><div class="metric-label">{{ $billingSummary['active_contract_count'] === 1 ? 'Contrato activo' : 'Contratos activos' }}</div><small class="text-muted">{{ $billingSummary['registered_contract_count'] }} {{ $billingSummary['registered_contract_count'] === 1 ? 'contrato registrado' : 'contratos registrados' }} · {{ $billingSummary['historical_contract_count'] }} {{ $billingSummary['historical_contract_count'] === 1 ? 'histórico/cancelado' : 'históricos/cancelados' }}</small></div></div>
    <div class="col-md-3"><div class="metric-card"><div class="metric-icon mb-3"><i class="fas fa-file-invoice-dollar"></i></div><div class="metric-value">{{ $billingSummary['active_invoice_count'] }}</div><div class="metric-label">Facturas activas</div><small class="text-muted">{{ $billingSummary['invoice_count'] }} registradas · {{ $billingSummary['void_invoice_count'] }} anuladas</small></div></div>
    <div class="col-md-3"><div class="metric-card"><div class="metric-icon mb-3"><i class="fas fa-clock"></i></div><div class="metric-value text-warning">{{ $billingSummary['pending_count'] }}</div><div class="metric-label">Facturas pendientes</div></div></div>
    <div class="col-md-3"><div class="metric-card"><div class="metric-icon mb-3"><i class="fas fa-triangle-exclamation"></i></div><div class="metric-value text-danger">{{ $billingSummary['overdue_count'] }}</div><div class="metric-label">Facturas vencidas</div></div></div>
</div>





<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card customer-card">
            <div class="card-header"><i class="fas fa-network-wired me-2 text-primary"></i>Asignación de red</div>
            <div class="card-body">
                @forelse($routerAssignments as $assignment)
                    <div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between"><strong>{{ $assignment->router_name }}{{ $assignment->contract_id ? ' · Contrato #'.$assignment->contract_id : '' }}</strong><span class="badge bg-{{ $assignment->connection_status === 'active' ? 'success' : 'secondary' }}">{{ $assignment->connection_status ?: 'Sin estado' }}</span></div>
                        <div class="row mt-2 small text-muted">
                            <div class="col-6">Router IP: <strong>{{ $assignment->router_ip ?: '-' }}</strong></div>
                            <div class="col-6">Cliente IP: <strong>{{ $assignment->assigned_ip ?: '-' }}</strong></div>
                            <div class="col-6">Puerto: <strong>{{ $assignment->port ?: '-' }}</strong></div>
                            <div class="col-6">VLAN: <strong>{{ $assignment->vlan ?: '-' }}</strong></div>
                            <div class="col-12">PPPoE: <strong>{{ $assignment->pppoe_username ?: '-' }}</strong></div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Sin router asignado.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card customer-card">
            <div class="card-header"><i class="fas fa-stream me-2 text-info"></i>Línea de tiempo</div>
            <div class="card-body">
                @forelse($timeline as $item)
                    <div class="timeline-item">
                        <div class="timeline-icon bg-{{ $item['color'] }}"><i class="fas {{ $item['icon'] }}"></i></div>
                        <div class="fw-bold">{{ $item['title'] }}</div>
                        <div class="small text-muted">{{ $timelineDate($item['date']) }}</div>
                        @if(!empty($item['paid_period']))<div class="small text-muted">Mes pagado: {{ $cleanPeriod($item['paid_period']) }}</div>@endif
                        <div>{{ $item['description'] }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">Sin eventos recientes.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card customer-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-map-marker-alt me-2 text-success"></i>Direcciones</span>
                @can('edit-customers')<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal"><i class="fas fa-plus"></i> Nueva Dirección</button>@endcan
            </div>
            <div class="card-body">
                @forelse($customer->addresses as $address)
                    <div class="border rounded p-3 mb-3">
                        <div class="mb-2">
                            @if($address->is_primary)<span class="badge bg-warning">Dirección primaria</span>@endif
                            <span class="badge bg-light text-dark">{{ ucfirst($address->address_type) }}</span>
                        </div>
                        @if($address->location_name)
                            <div class="fw-bold">{{ $address->location_name }}</div>
                        @endif
                        <div class="fw-bold">{{ trim($address->street . ' ' . $address->number) }}</div>
                        <div class="text-muted">{{ $address->city }}, {{ $address->state }} {{ $address->postal_code }}</div>
                        @if($address->reference)<div class="small text-muted"><i class="fas fa-map-signs"></i> Ref. {{ $address->reference }}</div>@endif
                        @if($address->coordinates)<div class="small text-muted"><i class="fas fa-map-pin"></i> {{ $address->coordinates }}</div>@endif
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No hay direcciones registradas.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card customer-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-comments me-2 text-primary"></i>Interacciones</span>
                @can('edit-customers')<a href="{{ route('customer.interactions.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Nueva</a>@endcan
            </div>
            <div class="card-body">
                @forelse($customer->interactions->sortByDesc('date')->take(5) as $interaction)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div><strong>{{ $interaction->interaction_type ?: 'Interacción' }}</strong><div class="small text-muted">{{ $interaction->channel ?: 'Sin canal' }}</div></div>
                        <div class="text-end small text-muted">{{ $interaction->date ? $interaction->date->format('d/m/Y H:i') : '-' }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No hay interacciones registradas.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card customer-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-phone-alt me-2 text-danger"></i>Contactos de emergencia</span>
                @can('edit-customers')<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEmergencyContactModal"><i class="fas fa-plus"></i> Nuevo</button>@endcan
            </div>
            <div class="card-body">
                @forelse($customer->emergencyContacts as $contact)
                    <div class="border rounded p-3 mb-3"><strong>{{ $contact->name }}</strong><div>{{ $contact->relationship }} · {{ $contact->phone }}</div><div class="text-muted">{{ $contact->email }}</div></div>
                @empty
                    <div class="text-center text-muted py-4">No hay contactos registrados.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card customer-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-file-alt me-2 text-secondary"></i>Documentos</span>
                @can('edit-customers')<a href="{{ route('customer.documents.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Nuevo</a>@endcan
            </div>
            <div class="card-body">
                @forelse($customer->documents as $document)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div><strong>{{ $document->name }}</strong><div class="small text-muted">{{ $document->documentType?->name ?: 'Sin tipo' }}</div></div>
                        <div><span class="badge bg-{{ $document->status === 'verified' ? 'success' : ($document->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($document->status) }}</span> <a href="{{ route('customer.documents.show', $document->id) }}" class="action-btn"><i class="fas fa-eye"></i></a></div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">No hay documentos registrados.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@can('edit-customers')
<div class="modal fade" id="addAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Añadir nueva dirección</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form action="{{ route('customer.addresses.store') }}" method="POST">@csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Nombre del local *</label><input type="text" class="form-control" name="location_name" placeholder="Ej: Casa principal, Tienda mercado central, Local 2" required><small class="form-text text-muted">Ej: Casa principal, Tienda mercado central, Local 2</small></div>
                    <div class="col-md-6"><label class="form-label">Calle/Avenida *</label><input type="text" class="form-control" name="street" required></div>
                    <div class="col-md-2"><label class="form-label">Número</label><input type="text" class="form-control" name="number"></div>
                    <div class="col-md-2"><label class="form-label">Piso</label><input type="text" class="form-control" name="floor"></div>
                    <div class="col-md-2"><label class="form-label">Apartamento</label><input type="text" class="form-control" name="apartment"></div>
                    <div class="col-md-4"><label class="form-label">Ciudad *</label><input type="text" class="form-control" name="city" required></div>
                    <div class="col-md-4"><label class="form-label">Estado/Provincia *</label><input type="text" class="form-control" name="state" required></div>
                    <div class="col-md-4"><label class="form-label">Código postal</label><input type="text" class="form-control" name="postal_code"></div>
                    <div class="col-md-6"><label class="form-label">Referencia *</label><input type="text" class="form-control" name="reference" placeholder="Ej: Frente al colegio, al costado de la posta" required><small class="form-text text-muted">Ej: Frente al colegio, al costado de la posta</small></div>
                    <div class="col-md-6"><label class="form-label">Coordenadas GPS</label><input type="text" class="form-control" name="coordinates"></div>
                    <div class="col-md-6"><label class="form-label">Tipo *</label><select name="address_type" class="form-select" required><option value="main">Principal</option><option value="billing">Facturación</option><option value="installation">Instalación</option></select></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_primary" value="1" id="is_primary"><label class="form-check-label" for="is_primary">Establecer como dirección primaria</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar dirección</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="addEmergencyContactModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Añadir contacto de emergencia</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form action="{{ route('customer.emergencyContacts.store') }}" method="POST">@csrf
            <input type="hidden" name="customer_id" value="{{ $customer->id }}">
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Nombre *</label><input type="text" class="form-control" name="name" required></div>
                <div class="mb-3"><label class="form-label">Relación *</label><input type="text" class="form-control" name="relationship" required></div>
                <div class="mb-3"><label class="form-label">Teléfono *</label><input type="text" class="form-control" name="phone" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar contacto</button></div>
        </form>
    </div></div>
</div>
@endcan

@can('edit-customers')
<div class="modal fade" id="quickEditCustomerModal" tabindex="-1" aria-labelledby="quickEditCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('customer.customers.update', $customer->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="first_name" value="{{ $customer->first_name }}">
                <input type="hidden" name="last_name" value="{{ $customer->last_name }}">
                <input type="hidden" name="customer_type" value="{{ $customer->customer_type ?: 'individual' }}">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="quickEditCustomerModalLabel"><i class="fas fa-user-edit me-2"></i>Registrar / Actualizar Datos del Cliente</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Ingresa o corrige los datos del cliente de forma rápida durante la atención telefónica.</p>

                    <div class="mb-3">
                        <label for="quick_identity_document" class="form-label fw-bold">Documento (DNI / RUC)</label>
                        <input type="text" class="form-control" id="quick_identity_document" name="identity_document" value="{{ $customer->identity_document }}" placeholder="Ej: 00000000 o 20601234567">
                    </div>

                    <div class="mb-3">
                        <label for="quick_phone" class="form-label fw-bold">Teléfono de Contacto</label>
                        <input type="text" class="form-control" id="quick_phone" name="phone" value="{{ $customer->phone }}" placeholder="Ej: 000000000">
                    </div>

                    <div class="mb-3">
                        <label for="quick_email" class="form-label fw-bold">Correo Electrónico</label>
                        <input type="email" class="form-control" id="quick_email" name="email" value="{{ $customer->email }}" placeholder="Ej: usuario5@example.test">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@if((Auth::user()->hasRole('admin') || Auth::user()->hasAnyPermission(['payments.create', 'payments.manage'])) && \Illuminate\Support\Facades\Route::has('billing.customers.add-credit'))
<div class="modal fade" id="addCreditModal" tabindex="-1" aria-labelledby="addCreditModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('billing.customers.add-credit', $customer->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addCreditModalLabel"><i class="fas fa-plus-circle text-success me-2"></i>Abonar Saldo a Favor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">El saldo a favor abonado podrá ser utilizado para pagar facturas automáticas o pendientes de este cliente.</p>
                    
                    <div class="mb-3">
                        <label for="action" class="form-label fw-bold">Tipo de Operación</label>
                        <select class="form-select" id="action" name="action" required>
                            <option value="add" selected>Cargar abono (Aumentar saldo a favor)</option>
                            <option value="consume">Retirar / Ajustar (Disminuir saldo a favor)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="amount" class="form-label fw-bold">Monto a Abonar (S/)</label>
                        <div class="input-group">
                            <span class="input-group-text">S/</span>
                            <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="contract_id" class="form-label fw-bold">Asociar a Contrato (Opcional)</label>
                        <select class="form-select" id="contract_id" name="contract_id">
                            <option value="">Todos / Ninguno (Saldo general del cliente)</option>
                            @foreach($contracts as $c)
                                <option value="{{ $c->id }}">
                                    Contrato #{{ $c->id }} - {{ $c->plan?->name ?: 'Sin Plan' }} (S/ {{ number_format($c->price ?? 0, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-bold">Descripción / Nota</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Ej. Abono por pago en exceso, saldo anterior..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>Registrar Abono</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<script>
    function confirmCustomerDelete(form) {
        const customerName = form.dataset.customerName || 'este cliente';
        const contractCount = Number(form.dataset.contractCount || 0);
        if (contractCount === 0) return confirm('¿Está seguro de eliminar a ' + customerName + '? Esta acción no se puede deshacer.');
        if (!confirm(customerName + ' tiene ' + contractCount + ' contrato(s). Se eliminará también su información relacionada. ¿Desea continuar?')) return false;
        if (!confirm('Última confirmación: esta acción no se puede deshacer. ¿Confirmar eliminación completa?')) return false;
        form.querySelector('[name="delete_deletable_contracts"]').value = '1';
        form.querySelector('[name="force_delete_related_history"]').value = '1';
        return true;
    }
</script>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const addCreditModal = document.getElementById('addCreditModal');
    if (addCreditModal) {
        document.body.appendChild(addCreditModal);

        const actionSelect = addCreditModal.querySelector('#action');
        const amountLabel = addCreditModal.querySelector('label[for="amount"]');
        const submitBtn = addCreditModal.querySelector('button[type="submit"]');
        const modalTitle = addCreditModal.querySelector('#addCreditModalLabel');

        if (actionSelect) {
            actionSelect.addEventListener('change', function() {
                if (this.value === 'consume') {
                    amountLabel.textContent = 'Monto a Retirar / Ajustar (S/)';
                    submitBtn.className = 'btn btn-danger';
                    submitBtn.innerHTML = '<i class="fas fa-minus-circle me-1"></i>Registrar Ajuste';
                    modalTitle.innerHTML = '<i class="fas fa-minus-circle text-danger me-2"></i>Retirar/Ajustar Saldo';
                } else {
                    amountLabel.textContent = 'Monto a Abonar (S/)';
                    submitBtn.className = 'btn btn-success';
                    submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Registrar Abono';
                    modalTitle.innerHTML = '<i class="fas fa-plus-circle text-success me-2"></i>Abonar Saldo a Favor';
                }
            });
        }
    }

    const quickEditModal = document.getElementById('quickEditCustomerModal');
    if (quickEditModal) {
        document.body.appendChild(quickEditModal);
        quickEditModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (button && button.dataset.focus) {
                const targetInput = quickEditModal.querySelector('#' + button.dataset.focus);
                if (targetInput) {
                    setTimeout(() => targetInput.focus(), 350);
                }
            }
        });
    }
});
</script>
@endpush
