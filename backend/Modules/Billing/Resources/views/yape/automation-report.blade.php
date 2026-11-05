@extends('core::layouts.master')

@section('title', 'Efectividad de la automatización Yape')
@section('page-title', 'Efectividad de la automatización Yape')

@section('content')
@php
    $funnel = $report['funnel'];
    $closure = $report['closure'];
    $monthly = $report['monthly'];
    $speed = $report['speed'];
    $stuck = $report['stuck'];
    $observedReasons = $report['observed_reasons'];

    $lastMonth = !empty($monthly) ? end($monthly) : null;
    $firstMonth = !empty($monthly) ? reset($monthly) : null;

    // The manual share inside Yape payments is the metric that proves the automation
    // is doing the work, because it does not depend on the non-Yape payment volume.
    $manualTrend = null;
    if ($lastMonth && $firstMonth && $lastMonth['month'] !== $firstMonth['month']) {
        $manualTrend = round($lastMonth['manual_pct'] - $firstMonth['manual_pct'], 2);
    }

    $stuckCount = count($stuck);
    $criticalStuck = collect($stuck)->where('age_hours', '>=', 48)->count();
@endphp

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-chart-line me-2"></i> Rendimiento de la automatización
        </h6>
        <a href="{{ route('billing.yape.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-mobile-alt me-1"></i> Ver historial Yape
        </a>
    </div>

    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('billing.yape.automation-report') }}" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" name="from" class="form-control" value="{{ $report['from']->toDateString() }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" name="to" class="form-control" value="{{ $report['to']->toDateString() }}">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-filter me-1"></i> Aplicar
                </button>
                <a href="{{ route('billing.yape.automation-report') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
        <div class="form-text mt-2">
            Por defecto arranca el {{ \Modules\Billing\Services\YapeAutomationReportService::AUTOMATION_GO_LIVE }},
            fecha en que la automatización Yape entró en producción.
        </div>
    </div>
</div>

@if($closure['validated'] === 0)
    <div class="alert alert-secondary">
        <i class="fas fa-info-circle me-2"></i>
        No hay validaciones Yape en el rango seleccionado. Ampliá las fechas para ver resultados.
    </div>
@else
    <div class="alert {{ $closure['automatic_pct'] >= 90 ? 'alert-success' : ($closure['automatic_pct'] >= 70 ? 'alert-warning' : 'alert-danger') }}">
        <div class="fw-bold mb-1">
            <i class="fas fa-gavel me-2"></i>
            La automatización cerró sola el {{ number_format($closure['automatic_pct'], 1) }}% de las validaciones Yape del período.
        </div>
        <div class="small">
            {{ $closure['closed_automatically'] }} de {{ $closure['validated'] }} casos se resolvieron sin que nadie tocara el panel.
            Los otros {{ $closure['closed_by_human'] }} los tuvo que cerrar una persona a mano.
            @if($manualTrend !== null)
                @if($manualTrend < 0)
                    La intervención manual <strong>bajó {{ number_format(abs($manualTrend), 1) }} puntos</strong>
                    entre {{ $firstMonth['label'] }} y {{ $lastMonth['label'] }}: la automatización está mejorando.
                @elseif($manualTrend > 0)
                    La intervención manual <strong>subió {{ number_format($manualTrend, 1) }} puntos</strong>
                    entre {{ $firstMonth['label'] }} y {{ $lastMonth['label'] }}: la automatización está perdiendo terreno.
                @else
                    La intervención manual se mantuvo igual entre {{ $firstMonth['label'] }} y {{ $lastMonth['label'] }}.
                @endif
            @endif
        </div>
    </div>
@endif

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Cerrado sin humanos</div>
                <div class="h3 mb-0 text-success">{{ number_format($closure['automatic_pct'], 1) }}%</div>
                <div class="small text-muted">{{ $closure['closed_automatically'] }} de {{ $closure['validated'] }} validaciones</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Rescatado a mano</div>
                <div class="h3 mb-0 {{ $closure['closed_by_human'] > 0 ? 'text-warning' : 'text-muted' }}">
                    {{ $closure['closed_by_human'] }}
                </div>
                <div class="small text-muted">{{ number_format($closure['manual_pct'], 1) }}% de las validaciones</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Clientes colgados</div>
                <div class="h3 mb-0 {{ $criticalStuck > 0 ? 'text-danger' : 'text-muted' }}">{{ $stuckCount }}</div>
                <div class="small text-muted">
                    @if($criticalStuck > 0)
                        {{ $criticalStuck }} con más de 48 h sin cerrar
                    @else
                        Ninguno crítico
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Tiempo de validación</div>
                <div class="h3 mb-0">
                    {{ $speed['avg_minutes'] !== null ? number_format($speed['avg_minutes'], 0) . ' min' : '—' }}
                </div>
                <div class="small text-muted">{{ $speed['within_5_min'] }} en 5 min o menos</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-calendar-alt me-2"></i> Evolución mes a mes
        </h6>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            La columna que decide es <strong>Automático vs Manual dentro de Yape</strong>: no depende de cuántos pagos
            no-Yape se registraron ese mes, así que mide el trabajo real que hace la automatización.
        </p>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Mes</th>
                        <th class="text-center">Pagos totales</th>
                        <th class="text-center">Pagos vía Yape</th>
                        <th class="text-center">Alcance Yape</th>
                        <th style="min-width: 220px;">Automático vs manual</th>
                        <th class="text-center">% automático</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($monthly as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['label'] }}</td>
                            <td class="text-center">{{ $row['total_payments'] }}</td>
                            <td class="text-center">{{ $row['yape_payments'] }}</td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark">{{ number_format($row['yape_share_pct'], 1) }}%</span>
                            </td>
                            <td>
                                @if($row['yape_payments'] > 0)
                                    <div class="progress" style="height: 18px;">
                                        <div class="progress-bar bg-success" role="progressbar"
                                            style="width: {{ $row['automatic_pct'] }}%"
                                            title="Automático: {{ $row['yape_automatic'] }}">
                                            {{ $row['yape_automatic'] }}
                                        </div>
                                        <div class="progress-bar bg-warning text-dark" role="progressbar"
                                            style="width: {{ $row['manual_pct'] }}%"
                                            title="Manual: {{ $row['yape_manual'] }}">
                                            {{ $row['yape_manual'] }}
                                        </div>
                                    </div>
                                @else
                                    <span class="text-muted small">Sin pagos Yape</span>
                                @endif
                            </td>
                            <td class="text-center fw-bold {{ $row['automatic_pct'] >= 90 ? 'text-success' : ($row['automatic_pct'] >= 70 ? 'text-warning' : 'text-danger') }}">
                                {{ number_format($row['automatic_pct'], 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Sin pagos completados en el rango.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-3 small text-muted">
            <span><span class="badge bg-success">&nbsp;</span> Cerrado por la automatización</span>
            <span><span class="badge bg-warning text-dark">&nbsp;</span> Cerrado a mano desde el panel</span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-danger">
                    <i class="fas fa-user-clock me-2"></i> Clientes colgados ({{ $stuckCount }})
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Intents que nadie cerró ni canceló. El cliente ya envió su captura y sigue esperando.
                    Es el costo invisible de la automatización.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Intent</th>
                                <th>Cliente</th>
                                <th class="text-end">Monto</th>
                                <th>Estado</th>
                                <th class="text-end">Antigüedad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stuck as $intent)
                                <tr>
                                    <td>
                                        <a href="{{ route('billing.yape.show', $intent->id) }}">{{ $intent->intent_number }}</a>
                                    </td>
                                    <td>{{ $intent->customer_name ?: '—' }}</td>
                                    <td class="text-end">S/ {{ number_format((float) $intent->expected_amount, 2) }}</td>
                                    <td><span class="badge bg-info text-dark">{{ $statuses[$intent->status] ?? $intent->status }}</span></td>
                                    <td class="text-end {{ $intent->age_hours >= 48 ? 'text-danger fw-bold' : '' }}">
                                        {{ $intent->age_hours >= 48 ? round($intent->age_hours / 24) . ' días' : $intent->age_hours . ' h' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Ningún cliente quedó colgado en este rango.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-warning">
                    <i class="fas fa-triangle-exclamation me-2"></i> Por qué falló
                </h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Motivos de los intents observados. Si una sola causa concentra los rechazos,
                    ahí está el parámetro que hay que ajustar.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Motivo</th>
                                <th class="text-center">Veces</th>
                                <th>Último</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($observedReasons as $reason)
                                <tr>
                                    <td class="small">{{ $reason->reason }}</td>
                                    <td class="text-center"><span class="badge bg-danger">{{ $reason->occurrences }}</span></td>
                                    <td class="small text-muted">
                                        {{ \Carbon\Carbon::parse($reason->last_seen)->format('d/m/Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Sin rechazos en este rango.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <hr>

                <div class="small">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Intents abiertos</span>
                        <strong>{{ $funnel['intents_created'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Terminaron en pago</span>
                        <strong>{{ $funnel['became_payment'] }} ({{ number_format($funnel['conversion_pct'], 1) }}%)</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Observados</span>
                        <strong>{{ $funnel['observed'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Cancelados</span>
                        <strong>{{ $funnel['cancelled'] }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Validación más lenta</span>
                        <strong>{{ $speed['max_minutes'] }} min</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
