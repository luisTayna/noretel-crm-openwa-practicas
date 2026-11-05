@extends('core::layouts.master')

@section('title', 'Registrar Cliente Histórico')
@section('page-title', 'Registrar Cliente Histórico')

@push('styles')
<style>
    .historical-shell {
        padding: 0.5rem;
    }

    .historical-hero {
        border-radius: 28px;
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(0, 153, 255, 0.12), rgba(16, 185, 129, 0.10));
        border: 1px solid rgba(255, 255, 255, 0.85);
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
    }

    .historical-card {
        border: 0;
        border-radius: 24px;
        background: rgba(255, 255, 255, 0.86);
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }

    .historical-card-header {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 1.1rem 1.25rem;
        border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        background: rgba(255, 255, 255, 0.56);
    }

    .historical-step {
        width: 2.35rem;
        height: 2.35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        color: #fff;
        font-weight: 800;
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        box-shadow: 0 10px 18px rgba(0, 153, 255, 0.22);
    }

    .historical-card-body {
        padding: 1.25rem;
    }

    .mode-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        padding: 1rem;
        background: rgba(248, 250, 252, 0.85);
        cursor: pointer;
        transition: all 0.18s ease;
        height: 100%;
    }

    .mode-card:hover,
    .mode-card.active {
        border-color: rgba(0, 153, 255, 0.42);
        background: rgba(239, 246, 255, 0.92);
        box-shadow: 0 14px 24px rgba(0, 153, 255, 0.10);
        transform: translateY(-1px);
    }

    .mode-card .mode-icon {
        width: 2.55rem;
        height: 2.55rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        background: #fff;
        color: var(--secondary-color);
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
    }

    .customer-search-box {
        position: relative;
    }

    .customer-search-box .search-icon {
        position: absolute;
        top: 50%;
        left: 1rem;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
    }

    .customer-result-list {
        display: grid;
        gap: 0.65rem;
        max-height: 360px;
        overflow-y: auto;
        padding-right: 0.25rem;
    }

    .customer-result-card {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.07);
        border-radius: 18px;
        padding: 0.85rem;
        background: rgba(255, 255, 255, 0.88);
        transition: all 0.18s ease;
    }

    .customer-result-card:hover,
    .customer-result-card.selected {
        border-color: rgba(16, 185, 129, 0.45);
        background: rgba(236, 253, 245, 0.88);
    }

    .customer-avatar {
        width: 2.65rem;
        height: 2.65rem;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        flex: 0 0 auto;
    }

    .selected-customer-card {
        display: none;
        border: 1px solid rgba(16, 185, 129, 0.28);
        border-radius: 20px;
        padding: 1rem;
        background: rgba(236, 253, 245, 0.88);
    }

    .selected-customer-card.active {
        display: flex;
    }

    .summary-card {
        position: sticky;
        top: 1rem;
        border: 0;
        border-radius: 24px;
        background: linear-gradient(180deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.95));
        color: #fff;
        box-shadow: 0 24px 50px rgba(15, 23, 42, 0.22);
        overflow: hidden;
    }

    .summary-card .summary-row {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .summary-card .summary-label {
        color: rgba(255, 255, 255, 0.62);
        font-size: 0.82rem;
    }

    .summary-card .summary-value {
        text-align: right;
        font-weight: 700;
    }

    .debt-preview-box {
        border-radius: 18px;
        padding: 1rem;
        min-height: 74px;
        background: rgba(248, 250, 252, 0.92);
        border: 1px solid rgba(15, 23, 42, 0.08);
        color: var(--text-color);
    }

    .form-control,
    .form-select {
        border-radius: 14px;
        padding: 0.68rem 0.9rem;
    }
</style>
@endpush

@section('content')
@php
    $selectedCustomerId = old('customer_id');
@endphp

<div class="historical-shell">
    <div class="historical-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white text-primary fw-semibold small mb-2">
                    <i class="fas fa-history"></i>
                    Cliente histórico
                </div>
                <h2 class="h3 fw-bold mb-1" style="color: var(--dark-bg);">Registrar servicio ya instalado</h2>
                <p class="text-muted mb-0">
                    Usa este flujo para clientes reales que ya tenían servicio activo antes de usar el CRM.
                </p>
            </div>
            <a href="{{ route('customer.customers.index') }}" class="btn btn-light rounded-pill px-4 py-2 fw-semibold shadow-sm">
                <i class="fas fa-arrow-left me-2 text-primary"></i> Volver a clientes
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger rounded-4 shadow-sm">
            <i class="fas fa-exclamation-circle me-2"></i> {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('customer.customers.historical.store') }}" method="POST" id="historicalCustomerForm">
        @csrf
        <input type="hidden" name="customer_id" id="customer_id" value="{{ $selectedCustomerId }}">

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="historical-card mb-4">
                    <div class="historical-card-header">
                        <span class="historical-step">1</span>
                        <div>
                            <h5 class="mb-0 fw-bold">Cliente</h5>
                            <small class="text-muted">Crea uno nuevo o selecciona uno ya importado.</small>
                        </div>
                    </div>
                    <div class="historical-card-body">
                        @error('customer_id')
                            <div class="alert alert-danger rounded-4">{{ $message }}</div>
                        @enderror

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="mode-card" data-customer-mode="new">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="mode-icon"><i class="fas fa-user-plus"></i></span>
                                        <div>
                                            <div class="fw-bold">Crear cliente nuevo</div>
                                            <small class="text-muted">Registra nombres, celular y dirección.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mode-card" data-customer-mode="existing">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="mode-icon"><i class="fas fa-search"></i></span>
                                        <div>
                                            <div class="fw-bold">Usar cliente existente</div>
                                            <small class="text-muted">Busca por nombre, DNI o celular.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="existingCustomerPicker" class="mb-4">
                            <label class="form-label fw-semibold">Buscar cliente existente</label>
                            <div class="customer-search-box mb-3">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="customerSearch" class="form-control ps-5" placeholder="Ej. Nombre, 00000000 o 000000000">
                            </div>

                            <div id="selectedCustomerCard" class="selected-customer-card align-items-center justify-content-between gap-3 mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="customer-avatar" id="selectedCustomerInitials">CL</span>
                                    <div>
                                        <div class="fw-bold" id="selectedCustomerName">Cliente seleccionado</div>
                                        <div class="small text-muted" id="selectedCustomerMeta">DNI: - · Cel: -</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" id="clearCustomerSelection">
                                    <i class="fas fa-times me-1"></i> Quitar
                                </button>
                            </div>

                            <div class="customer-result-list" id="customerResults"></div>
                            <div class="form-text mt-2">
                                Si no lo encuentras, usa “Crear cliente nuevo”. Se muestran los primeros resultados coincidentes.
                            </div>
                        </div>

                        <div id="newCustomerFields">
                            <div class="alert alert-info rounded-4">
                                <i class="fas fa-info-circle me-2"></i>
                                Completa estos datos solo si el cliente todavía no existe en el CRM.
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="customer_type" class="form-label">Tipo</label>
                                    <select name="customer_type" id="customer_type" class="form-select new-customer-control @error('customer_type') is-invalid @enderror">
                                        <option value="individual" {{ old('customer_type', 'individual') === 'individual' ? 'selected' : '' }}>Individual</option>
                                        <option value="business" {{ old('customer_type') === 'business' ? 'selected' : '' }}>Empresa</option>
                                    </select>
                                    @error('customer_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="first_name" class="form-label">Nombres</label>
                                    <input type="text" name="first_name" id="first_name" class="form-control new-customer-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}">
                                    @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="last_name" class="form-label">Apellidos</label>
                                    <input type="text" name="last_name" id="last_name" class="form-control new-customer-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}">
                                    @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="identity_document" class="form-label">DNI/RUC</label>
                                    <input type="text" name="identity_document" id="identity_document" class="form-control new-customer-control @error('identity_document') is-invalid @enderror" value="{{ old('identity_document') }}">
                                    @error('identity_document') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="phone" class="form-label">Celular</label>
                                    <input type="text" name="phone" id="phone" class="form-control new-customer-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control new-customer-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="segment" class="form-label">Segmento</label>
                                    <select name="segment" id="segment" class="form-select new-customer-control @error('segment') is-invalid @enderror">
                                        <option value="residential" {{ old('segment', 'residential') === 'residential' ? 'selected' : '' }}>Residencial</option>
                                        <option value="business" {{ old('segment') === 'business' ? 'selected' : '' }}>Empresarial</option>
                                        <option value="corporate" {{ old('segment') === 'corporate' ? 'selected' : '' }}>Corporativo</option>
                                    </select>
                                    @error('segment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="street" class="form-label">Dirección de instalación</label>
                                    <input type="text" name="street" id="street" class="form-control new-customer-control @error('street') is-invalid @enderror" value="{{ old('street') }}" placeholder="Ej. Av. principal, referencia del domicilio">
                                    @error('street') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="city" class="form-label">Zona/Ciudad</label>
                                    <input type="text" name="city" id="city" class="form-control new-customer-control @error('city') is-invalid @enderror" value="{{ old('city') }}" placeholder="Ej. Nombre, San Sebastián">
                                    @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <input type="hidden" name="state" value="{{ old('state', 'Cusco') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="historical-card mb-4">
                    <div class="historical-card-header">
                        <span class="historical-step">2</span>
                        <div>
                            <h5 class="mb-0 fw-bold">Servicio instalado</h5>
                            <small class="text-muted">Define el plan, precio y fecha real de instalación.</small>
                        </div>
                    </div>
                    <div class="historical-card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="plan_id" class="form-label">Plan <span class="text-danger">*</span></label>
                                <select name="plan_id" id="plan_id" class="form-select @error('plan_id') is-invalid @enderror" required>
                                    <option value="">Seleccionar plan</option>
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" data-price="{{ $plan->price }}" data-name="{{ $plan->name }} · {{ $plan->download_speed }} Mbps" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                            {{ $plan->name }} · {{ $plan->download_speed }} Mbps · S/ {{ number_format((float) $plan->price, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('plan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="monthly_price" class="form-label">Precio mensual <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text rounded-start-4">S/</span>
                                    <input type="number" step="0.01" min="0" name="monthly_price" id="monthly_price" class="form-control @error('monthly_price') is-invalid @enderror" value="{{ old('monthly_price') }}" required>
                                    @error('monthly_price') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="installed_at" class="form-label">Fecha real de instalación <span class="text-danger">*</span></label>
                                <input type="date" name="installed_at" id="installed_at" class="form-control @error('installed_at') is-invalid @enderror" value="{{ old('installed_at') }}" max="{{ now()->format('Y-m-d') }}" required>
                                @error('installed_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="historical-card mb-4">
                    <div class="historical-card-header">
                        <span class="historical-step">3</span>
                        <div>
                            <h5 class="mb-0 fw-bold">Deuda anterior automática</h5>
                            <small class="text-muted">Genera recibos vencidos desde la fecha histórica que corresponda.</small>
                        </div>
                    </div>
                    <div class="historical-card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="debt_start_date" class="form-label">Cobrar deuda desde</label>
                                <input type="date" name="debt_start_date" id="debt_start_date" class="form-control @error('debt_start_date') is-invalid @enderror" value="{{ old('debt_start_date') }}">
                                <div class="form-text">Si empieza a mitad de mes, el primer recibo se prorratea hasta el día 30.</div>
                                @error('debt_start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="debt_end_month" class="form-label">Cobrar hasta el mes</label>
                                <input type="month" name="debt_end_month" id="debt_end_month" class="form-control @error('debt_end_month') is-invalid @enderror" value="{{ old('debt_end_month', now()->format('Y-m')) }}">
                                <div class="form-text">Déjalo en el mes actual para calcular hasta ahora.</div>
                                @error('debt_end_month') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Resumen de deuda</label>
                                <div class="debt-preview-box" id="debt_preview">
                                    Sin deuda histórica si dejas vacía la fecha inicial.
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas internas</label>
                            <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" placeholder="Ej. Cliente migrado desde padrón anterior, instalación verificada, observaciones de deuda.">{{ old('notes') }}</textarea>
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="summary-card">
                    <div class="p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="historical-step"><i class="fas fa-clipboard-check"></i></span>
                            <div>
                                <h5 class="mb-0 fw-bold text-white">Resumen</h5>
                                <small style="color: rgba(255,255,255,0.64);">Revisa antes de registrar.</small>
                            </div>
                        </div>

                        <div class="summary-row">
                            <span class="summary-label">Modo</span>
                            <span class="summary-value" id="summary_mode">Cliente nuevo</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Cliente</span>
                            <span class="summary-value" id="summary_customer">Por completar</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Plan</span>
                            <span class="summary-value" id="summary_plan">-</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Precio</span>
                            <span class="summary-value" id="summary_price">S/ 0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Instalación</span>
                            <span class="summary-value" id="summary_installation">-</span>
                        </div>
                        <div class="summary-row border-bottom-0">
                            <span class="summary-label">Deuda estimada</span>
                            <span class="summary-value" id="summary_debt">Sin deuda</span>
                        </div>

                        <div class="alert mt-4 mb-4 rounded-4" style="background: rgba(255,255,255,0.10); color: rgba(255,255,255,0.82); border: 1px solid rgba(255,255,255,0.12);">
                            <i class="fas fa-triangle-exclamation me-2"></i>
                            Este flujo no agenda visita técnica. Crea contrato activo, instalación completada y recibos históricos si corresponde.
                        </div>

                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-3 fw-bold" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                            <i class="fas fa-save me-2"></i> Registrar cliente histórico
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const customers = @json($customerOptions);
    const selectedCustomerIdFromOld = '{{ $selectedCustomerId }}';

    const customerIdInput = document.getElementById('customer_id');
    const customerSearch = document.getElementById('customerSearch');
    const customerResults = document.getElementById('customerResults');
    const selectedCustomerCard = document.getElementById('selectedCustomerCard');
    const selectedCustomerInitials = document.getElementById('selectedCustomerInitials');
    const selectedCustomerName = document.getElementById('selectedCustomerName');
    const selectedCustomerMeta = document.getElementById('selectedCustomerMeta');
    const clearCustomerSelection = document.getElementById('clearCustomerSelection');
    const existingCustomerPicker = document.getElementById('existingCustomerPicker');
    const newCustomerFields = document.getElementById('newCustomerFields');
    const newCustomerControls = document.querySelectorAll('.new-customer-control');
    const modeCards = document.querySelectorAll('[data-customer-mode]');

    const planSelect = document.getElementById('plan_id');
    const monthlyPrice = document.getElementById('monthly_price');
    const installedAt = document.getElementById('installed_at');
    const debtStartDate = document.getElementById('debt_start_date');
    const debtEndMonth = document.getElementById('debt_end_month');
    const debtPreview = document.getElementById('debt_preview');

    const summaryMode = document.getElementById('summary_mode');
    const summaryCustomer = document.getElementById('summary_customer');
    const summaryPlan = document.getElementById('summary_plan');
    const summaryPrice = document.getElementById('summary_price');
    const summaryInstallation = document.getElementById('summary_installation');
    const summaryDebt = document.getElementById('summary_debt');

    let selectedCustomer = customers.find((customer) => String(customer.id) === String(selectedCustomerIdFromOld)) || null;
    let mode = selectedCustomer ? 'existing' : 'new';

    function setMode(nextMode) {
        mode = nextMode;
        const useExisting = mode === 'existing';

        existingCustomerPicker.classList.toggle('d-none', !useExisting);
        newCustomerFields.classList.toggle('d-none', useExisting);
        newCustomerControls.forEach((control) => control.disabled = useExisting);
        modeCards.forEach((card) => card.classList.toggle('active', card.dataset.customerMode === mode));

        if (!useExisting) {
            selectedCustomer = null;
            customerIdInput.value = '';
            customerSearch.value = '';
        }

        renderSelectedCustomer();
        renderCustomerResults();
        updateSummary();
    }

    function selectCustomer(customer) {
        selectedCustomer = customer;
        customerIdInput.value = customer.id;
        customerSearch.value = '';
        renderSelectedCustomer();
        renderCustomerResults();
        updateSummary();
    }

    function renderSelectedCustomer() {
        if (!selectedCustomer) {
            selectedCustomerCard.classList.remove('active');
            return;
        }

        selectedCustomerInitials.textContent = selectedCustomer.initials || 'CL';
        selectedCustomerName.textContent = selectedCustomer.name || 'Cliente seleccionado';
        selectedCustomerMeta.textContent = `DNI: ${selectedCustomer.document || '-'} · Cel: ${selectedCustomer.phone || '-'}`;
        selectedCustomerCard.classList.add('active');
    }

    function renderCustomerResults() {
        if (mode !== 'existing') {
            customerResults.innerHTML = '';
            return;
        }

        const query = normalize(customerSearch.value);
        const matches = customers
            .filter((customer) => query === '' || normalize(customer.search).includes(query))
            .slice(0, 8);

        if (matches.length === 0) {
            customerResults.innerHTML = '<div class="text-muted text-center py-3">No se encontraron clientes con esa búsqueda.</div>';
            return;
        }

        customerResults.innerHTML = matches.map((customer) => {
            const selected = selectedCustomer && Number(selectedCustomer.id) === Number(customer.id);

            return `
                <button type="button" class="customer-result-card text-start ${selected ? 'selected' : ''}" data-customer-id="${customer.id}">
                    <span class="d-flex align-items-center gap-3">
                        <span class="customer-avatar">${escapeHtml(customer.initials || 'CL')}</span>
                        <span>
                            <span class="d-block fw-bold">${escapeHtml(customer.name)}</span>
                            <span class="d-block small text-muted">DNI: ${escapeHtml(customer.document || '-')} · Cel: ${escapeHtml(customer.phone || '-')}</span>
                        </span>
                    </span>
                    <span class="btn btn-sm ${selected ? 'btn-success' : 'btn-outline-primary'} rounded-pill">
                        ${selected ? 'Seleccionado' : 'Seleccionar'}
                    </span>
                </button>
            `;
        }).join('');
    }

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[char];
        });
    }

    function fillPlanPrice() {
        const option = planSelect.options[planSelect.selectedIndex];
        if (option && option.dataset.price && monthlyPrice.value === '') {
            monthlyPrice.value = option.dataset.price;
        }
        updateSummary();
    }

    function fixedDueDate(year, monthIndex) {
        const lastDay = new Date(year, monthIndex + 1, 0).getDate();
        return new Date(year, monthIndex, Math.min(30, lastDay));
    }

    function firstDueOnOrAfter(date) {
        let due = fixedDueDate(date.getFullYear(), date.getMonth());

        if (due < date) {
            due = fixedDueDate(date.getFullYear(), date.getMonth() + 1);
        }

        return due;
    }

    function formatDate(date) {
        return date.toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function previewDebt() {
        if (!debtStartDate.value) {
            debtPreview.textContent = 'Sin deuda histórica si dejas vacía la fecha inicial.';
            summaryDebt.textContent = 'Sin deuda';
            return;
        }

        const price = Number(monthlyPrice.value || 0);
        const start = new Date(debtStartDate.value + 'T00:00:00');
        const endParts = (debtEndMonth.value || new Date().toISOString().slice(0, 7)).split('-');
        const finalDue = fixedDueDate(Number(endParts[0]), Number(endParts[1]) - 1);

        if (finalDue < start || price <= 0) {
            debtPreview.textContent = 'Revisa precio y rango de deuda.';
            summaryDebt.textContent = 'Revisar';
            return;
        }

        let due = firstDueOnOrAfter(start);
        let count = 0;
        let total = 0;
        let firstAmount = 0;
        let first = true;

        while (due <= finalDue && count < 120) {
            let amount = price;

            if (first && start > new Date(due.getFullYear(), due.getMonth(), 1)) {
                const monthStart = new Date(due.getFullYear(), due.getMonth(), 1);
                const billingDays = Math.max(1, Math.round((due - monthStart) / 86400000) + 1);
                const chargedDays = Math.max(1, Math.round((due - start) / 86400000) + 1);
                amount = Math.round((price / billingDays) * chargedDays * 100) / 100;
                firstAmount = amount;
            }

            total += amount;
            count++;
            first = false;
            due = fixedDueDate(due.getFullYear(), due.getMonth() + 1);
        }

        const text = `${count} recibo(s) · aprox. S/ ${total.toFixed(2)}${firstAmount ? ` · primer prorrateo S/ ${firstAmount.toFixed(2)}` : ''} · primer vencimiento ${formatDate(firstDueOnOrAfter(start))}`;
        debtPreview.textContent = text;
        summaryDebt.textContent = `S/ ${total.toFixed(2)}`;
    }

    function updateSummary() {
        const selectedPlan = planSelect.options[planSelect.selectedIndex];
        const planName = selectedPlan && selectedPlan.value ? (selectedPlan.dataset.name || selectedPlan.textContent.trim()) : '-';
        const price = Number(monthlyPrice.value || 0);

        summaryMode.textContent = mode === 'existing' ? 'Cliente existente' : 'Cliente nuevo';
        summaryCustomer.textContent = mode === 'existing'
            ? (selectedCustomer ? selectedCustomer.name : 'Selecciona un cliente')
            : customerNameFromFields();
        summaryPlan.textContent = planName;
        summaryPrice.textContent = `S/ ${price.toFixed(2)}`;
        summaryInstallation.textContent = installedAt.value ? formatDate(new Date(installedAt.value + 'T00:00:00')) : '-';
    }

    function customerNameFromFields() {
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const fullName = `${firstName} ${lastName}`.trim();

        return fullName || 'Por completar';
    }

    modeCards.forEach((card) => {
        card.addEventListener('click', () => setMode(card.dataset.customerMode));
    });

    customerSearch.addEventListener('input', renderCustomerResults);
    customerResults.addEventListener('click', function (event) {
        const card = event.target.closest('[data-customer-id]');
        if (!card) {
            return;
        }

        const customer = customers.find((item) => Number(item.id) === Number(card.dataset.customerId));
        if (customer) {
            selectCustomer(customer);
        }
    });

    clearCustomerSelection.addEventListener('click', function () {
        selectedCustomer = null;
        customerIdInput.value = '';
        renderSelectedCustomer();
        renderCustomerResults();
        updateSummary();
    });

    planSelect.addEventListener('change', fillPlanPrice);
    planSelect.addEventListener('change', previewDebt);
    monthlyPrice.addEventListener('input', previewDebt);
    monthlyPrice.addEventListener('input', updateSummary);
    installedAt.addEventListener('change', function () {
        if (!debtStartDate.value) {
            debtStartDate.value = installedAt.value;
        }
        previewDebt();
        updateSummary();
    });
    debtStartDate.addEventListener('change', previewDebt);
    debtEndMonth.addEventListener('change', previewDebt);
    ['first_name', 'last_name'].forEach((id) => {
        document.getElementById(id).addEventListener('input', updateSummary);
    });

    setMode(mode);
    fillPlanPrice();
    previewDebt();
    updateSummary();
});
</script>
@endpush
