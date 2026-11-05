@extends('core::layouts.master')

@section('title', 'Inteligencia BI & Clasificación')
@section('page-title', 'Inteligencia BI & Clasificación de Clientes')

@push('styles')
<style>
    .bi-hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%); border-radius: 24px; color: #fff; padding: 1.8rem; box-shadow: 0 16px 36px rgba(15, 23, 42, 0.2); }
    .bi-kpi-card { border-radius: 20px; border: 0; box-shadow: 0 8px 24px rgba(0,0,0,0.04); transition: transform 0.2s ease, box-shadow 0.2s ease; overflow: hidden; position: relative; }
    .bi-kpi-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
    .kpi-vip { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid rgba(245, 158, 11, 0.3); }
    .kpi-regular { background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid rgba(59, 130, 246, 0.3); }
    .kpi-churn { background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border: 1px solid rgba(239, 68, 68, 0.3); }
    .kpi-new { background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid rgba(34, 197, 94, 0.3); }
    .kpi-icon-box { width: 48px; height: 48px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
    .health-bar-container { background: #e2e8f0; border-radius: 10px; height: 8px; width: 100%; overflow: hidden; }
    .health-bar-fill { height: 100%; border-radius: 10px; transition: width 0.4s ease; }
    .alert-card-vip { background: #fff5f5; border-left: 5px solid #ef4444; border-radius: 14px; }
    .alert-card-milestone { background: #fffbeb; border-left: 5px solid #f59e0b; border-radius: 14px; }
    .btn-whatsapp { background-color: #25d366; color: white; border-radius: 50px; font-weight: 600; padding: 5px 12px; border: none; }
    .btn-whatsapp:hover { background-color: #128c7e; color: white; }
</style>
@endpush

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="bi-hero mb-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
        <h2 class="h3 mb-1 text-white fw-bold"><i class="fas fa-brain text-warning me-2"></i> Inteligencia de Negocios & Segmentación BI</h2>
        <p class="text-white-50 mb-0">Algoritmo de clasificación de clientes basado en modelo RFM (Recencia, Frecuencia de Pago y ARPU)</p>
    </div>
    <div>
        <form action="{{ route('customer.bi-classification.run') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-warning text-dark fw-bold px-4 py-2 rounded-pill shadow-sm" onclick="return confirm('¿Desea recalcular la clasificación BI para toda la cartera de clientes ahora?');">
                <i class="fas fa-sync-alt me-2"></i> Clasificar Cartera Ahora
            </button>
        </form>
    </div>
</div>

<!-- Widget de Alertas Tempranas (Atención HOY) -->
@if(($criticalVipAlerts && $criticalVipAlerts->count() > 0) || ($newClientMilestones && $newClientMilestones->count() > 0))
<div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #fafafa;">
    <h5 class="fw-bold text-dark mb-3"><i class="fas fa-bell text-danger me-2"></i> Alertas Tempranas (Atención HOY)</h5>
    <div class="row g-3">
        @if($criticalVipAlerts && $criticalVipAlerts->count() > 0)
        <div class="col-12 col-md-6">
            <div class="alert-card-vip p-3 h-100 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-danger text-white text-uppercase"><i class="fas fa-exclamation-triangle me-1"></i> 🔴 Alerta VIP Crítica</span>
                    <span class="fw-bold text-danger">{{ $criticalVipAlerts->count() }} cliente(s)</span>
                </div>
                <p class="small text-dark mb-2">Clientes de alto valor (VIP) que registran su primer atraso de pago. Requieren atención comercial inmediata.</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($criticalVipAlerts->take(3) as $vipClient)
                        @php
                            $phone = preg_replace('/[^0-9]/', '', $vipClient->phone ?: '');
                            if (strlen($phone) === 9) $phone = '51' . $phone;
                            $vipMsg = rawurlencode("Hola {$vipClient->first_name}, le saludamos de NoreTel. Notamos un pendiente en su cuenta y deseamos ayudarle a mantener su servicio VIP activo. ¿Podemos asistirlo?");
                        @endphp
                        <a href="https://wa.me/{{ $phone }}?text={{ $vipMsg }}" target="_blank" class="btn btn-sm btn-whatsapp me-1">
                            <i class="fab fa-whatsapp me-1"></i> {{ $vipClient->first_name }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @if($newClientMilestones && $newClientMilestones->count() > 0)
        <div class="col-12 col-md-6">
            <div class="alert-card-milestone p-3 h-100 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-warning text-dark text-uppercase"><i class="fas fa-birthday-cake me-1"></i> 🟡 Hito 90 Días</span>
                    <span class="fw-bold text-dark">{{ $newClientMilestones->count() }} cliente(s)</span>
                </div>
                <p class="small text-dark mb-2">Clientes nuevos cumpliendo 3 meses de servicio. Oportunidad ideal para medir satisfacción y afianzar permanencia.</p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($newClientMilestones->take(3) as $newClient)
                        @php
                            $phone = preg_replace('/[^0-9]/', '', $newClient->phone ?: '');
                            if (strlen($phone) === 9) $phone = '51' . $phone;
                            $newMsg = rawurlencode("Hola {$newClient->first_name}, ¡gracias por cumplir 3 meses con NoreTel! Queremos saber cómo ha sido tu experiencia con la velocidad del internet.");
                        @endphp
                        <a href="https://wa.me/{{ $phone }}?text={{ $newMsg }}" target="_blank" class="btn btn-sm btn-whatsapp me-1">
                            <i class="fab fa-whatsapp me-1"></i> {{ $newClient->first_name }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endif

<!-- Tarjetas KPI -->
<div class="row g-4 mb-4">
    <!-- VIP -->
    <div class="col-12 col-md-6 col-xl-3">
        <a href="{{ route('customer.bi-classification', ['segment' => 'vip']) }}" class="text-decoration-none">
            <div class="card bi-kpi-card kpi-vip h-100 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-warning fw-bold small text-uppercase"><i class="fas fa-crown me-1"></i> VIP / Excelente</span>
                    <div class="kpi-icon-box bg-warning text-white"><i class="fas fa-crown"></i></div>
                </div>
                <h3 class="fw-bold text-dark mb-1">{{ $segmentCounts['vip'] }} <small class="fs-6 text-muted">clientes</small></h3>
                <div class="small text-secondary fw-semibold">Ingresos: S/ {{ number_format($revenuePerSegment['vip'], 2) }}/mes</div>
            </div>
        </a>
    </div>

    <!-- Regular -->
    <div class="col-12 col-md-6 col-xl-3">
        <a href="{{ route('customer.bi-classification', ['segment' => 'regular']) }}" class="text-decoration-none">
            <div class="card bi-kpi-card kpi-regular h-100 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-primary fw-bold small text-uppercase"><i class="fas fa-user-check me-1"></i> Estable / Regular</span>
                    <div class="kpi-icon-box bg-primary text-white"><i class="fas fa-user-check"></i></div>
                </div>
                <h3 class="fw-bold text-dark mb-1">{{ $segmentCounts['regular'] }} <small class="fs-6 text-muted">clientes</small></h3>
                <div class="small text-secondary fw-semibold">Ingresos: S/ {{ number_format($revenuePerSegment['regular'], 2) }}/mes</div>
            </div>
        </a>
    </div>

    <!-- Churn Risk -->
    <div class="col-12 col-md-6 col-xl-3">
        <a href="{{ route('customer.bi-classification', ['segment' => 'churn_risk']) }}" class="text-decoration-none">
            <div class="card bi-kpi-card kpi-churn h-100 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-danger fw-bold small text-uppercase"><i class="fas fa-exclamation-triangle me-1"></i> Riesgo de Churn</span>
                    <div class="kpi-icon-box bg-danger text-white"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <h3 class="fw-bold text-dark mb-1">{{ $segmentCounts['churn_risk'] }} <small class="fs-6 text-muted">clientes</small></h3>
                <div class="small text-danger fw-semibold">En riesgo: S/ {{ number_format($revenuePerSegment['churn_risk'], 2) }}/mes</div>
            </div>
        </a>
    </div>

    <!-- New -->
    <div class="col-12 col-md-6 col-xl-3">
        <a href="{{ route('customer.bi-classification', ['segment' => 'new']) }}" class="text-decoration-none">
            <div class="card bi-kpi-card kpi-new h-100 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-success fw-bold small text-uppercase"><i class="fas fa-seedling me-1"></i> Nuevo en Observación</span>
                    <div class="kpi-icon-box bg-success text-white"><i class="fas fa-seedling"></i></div>
                </div>
                <h3 class="fw-bold text-dark mb-1">{{ $segmentCounts['new'] }} <small class="fs-6 text-muted">clientes</small></h3>
                <div class="small text-secondary fw-semibold">Ingresos: S/ {{ number_format($revenuePerSegment['new'], 2) }}/mes</div>
            </div>
        </a>
    </div>
</div>

<!-- Gráficos Analíticos -->
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i> Distribución de Cartera por Segmento</h5>
            <div style="height: 250px; position: relative;">
                <canvas id="segmentDonutChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-chart-bar me-2 text-success"></i> Ingresos Mensuales por Segmento (S/)</h5>
            <div style="height: 250px; position: relative;">
                <canvas id="revenueBarChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de Clientes e Indicador de Salud BI -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-users-cog me-2 text-primary"></i> Clientes Clasificados</h5>
        <div class="d-flex gap-2">
            <a href="{{ route('customer.bi-classification') }}" class="btn btn-sm btn-{{ !$segmentFilter ? 'dark' : 'outline-secondary' }}">Todos ({{ $totalCustomers }})</a>
            <a href="{{ route('customer.bi-classification', ['segment' => 'vip']) }}" class="btn btn-sm btn-{{ $segmentFilter === 'vip' ? 'warning' : 'outline-warning' }}">VIP</a>
            <a href="{{ route('customer.bi-classification', ['segment' => 'regular']) }}" class="btn btn-sm btn-{{ $segmentFilter === 'regular' ? 'primary' : 'outline-primary' }}">Estables</a>
            <a href="{{ route('customer.bi-classification', ['segment' => 'churn_risk']) }}" class="btn btn-sm btn-{{ $segmentFilter === 'churn_risk' ? 'danger' : 'outline-danger' }}">Riesgo Churn</a>
            <a href="{{ route('customer.bi-classification', ['segment' => 'new']) }}" class="btn btn-sm btn-{{ $segmentFilter === 'new' ? 'success' : 'outline-success' }}">Nuevos</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Cliente</th>
                    <th>Documento</th>
                    <th>Plan Activo</th>
                    <th>Segmento BI</th>
                    <th style="min-width: 160px;">Puntaje Salud BI</th>
                    <th>Última Actualización</th>
                    <th class="text-end">Acciones Comercial / WhatsApp</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                    @php
                        $score = $customer->bi_score ?? 50.0;
                        $scoreColor = $score >= 80 ? '#10b981' : ($score >= 60 ? '#3b82f6' : ($score >= 40 ? '#f59e0b' : '#ef4444'));
                        $activePlan = $customer->contracts->firstWhere('status', 'active')?->plan;

                        // Mensajes personalizados de WhatsApp por segmento
                        $phone = preg_replace('/[^0-9]/', '', $customer->phone ?: '');
                        if (strlen($phone) === 9) $phone = '51' . $phone;

                        if ($customer->bi_segment === 'vip') {
                            $waText = "Hola {$customer->first_name}, en NoreTel valoramos su preferencia como cliente VIP. Tenemos una propuesta especial de Upgrade de velocidad para su servicio de internet. ¿Le gustaría conocer los detalles?";
                        } elseif ($customer->bi_segment === 'churn_risk') {
                            $waText = "Hola {$customer->first_name}, le saludamos de NoreTel. Le escribimos para recordarle mantener su servicio de internet al día y ofrecerle facilidades de pago para evitar la suspensión de su línea.";
                        } elseif ($customer->bi_segment === 'new') {
                            $waText = "Hola {$customer->first_name}, bienvenido a NoreTel. Queremos saber si su servicio de internet está funcionando con total normalidad. ¡Estamos para atenderle!";
                        } else {
                            $waText = "Hola {$customer->first_name}, le saludamos de NoreTel CRM. ¿En qué podemos ayudarle el día de hoy con su servicio de internet?";
                        }
                        $waUrl = "https://wa.me/{$phone}?text=" . rawurlencode($waText);
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">{{ $customer->full_name }}</div>
                            <small class="text-muted">ID #{{ $customer->id }} · {{ $customer->phone ?: 'Sin teléfono' }}</small>
                        </td>
                        <td>{{ $customer->identity_document ?: '-' }}</td>
                        <td>
                            @if($activePlan)
                                <span class="badge bg-light text-dark border"><i class="fas fa-wifi text-primary me-1"></i> {{ $activePlan->name }} (S/ {{ number_format($activePlan->price, 2) }})</span>
                            @else
                                <span class="text-muted small">Sin plan activo</span>
                            @endif
                        </td>
                        <td>
                            @if($customer->bi_segment === 'vip')
                                <span class="badge bg-warning text-dark"><i class="fas fa-crown me-1"></i> VIP / Excelente</span>
                            @elseif($customer->bi_segment === 'regular')
                                <span class="badge bg-primary text-white"><i class="fas fa-user-check me-1"></i> Estable / Regular</span>
                            @elseif($customer->bi_segment === 'churn_risk')
                                <span class="badge bg-danger text-white"><i class="fas fa-exclamation-triangle me-1"></i> Riesgo de Churn</span>
                            @elseif($customer->bi_segment === 'new')
                                <span class="badge bg-success text-white"><i class="fas fa-seedling me-1"></i> Nuevo en Observación</span>
                            @else
                                <span class="badge bg-secondary text-white">Sin Clasificar</span>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="health-bar-container flex-grow-1">
                                    <div class="health-bar-fill" style="width: {{ min(100, max(5, $score)) }}%; background-color: {{ $scoreColor }};"></div>
                                </div>
                                <span class="fw-bold small" style="color: {{ $scoreColor }}; width: 42px;">{{ number_format($score, 0) }}%</span>
                            </div>
                        </td>
                        <td class="small text-muted">
                            {{ $customer->bi_updated_at ? $customer->bi_updated_at->diffForHumans() : 'Pendiente' }}
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                @if($customer->phone)
                                    <a href="{{ $waUrl }}" target="_blank" class="btn btn-sm btn-whatsapp" title="Enviar WhatsApp Comercial (1 Clic)">
                                        <i class="fab fa-whatsapp me-1"></i> Contactar
                                    </a>
                                @endif
                                <a href="{{ route('customer.customers.show', $customer->id) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No se encontraron clientes para este segmento.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white py-3">
        {{ $customers->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const counts = @json($segmentCounts);
        const revenue = @json($revenuePerSegment);

        const ctxDonut = document.getElementById('segmentDonutChart').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['VIP / Excelente', 'Estable / Regular', 'Riesgo Churn', 'Nuevo'],
                datasets: [{
                    data: [counts.vip, counts.regular, counts.churn_risk, counts.new],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#ef4444', '#10b981'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        const ctxBar = document.getElementById('revenueBarChart').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: ['VIP', 'Estable', 'Riesgo Churn', 'Nuevo'],
                datasets: [{
                    label: 'Ingresos Mensuales (S/)',
                    data: [revenue.vip, revenue.regular, revenue.churn_risk, revenue.new],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#ef4444', '#10b981'],
                    borderRadius: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });
</script>
@endpush
