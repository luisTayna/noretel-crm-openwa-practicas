@extends('core::layouts.master')

@section('title', 'Clientes')
@section('page-title', 'Gestión de Clientes')

@push('styles')
<style>
    /* Estilos específicos para el diseño Glassmorphism del listado de clientes */
    .customer-dashboard-glass {
        padding: 0.5rem;
    }

    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        box-shadow: 0 4px 10px rgba(0, 153, 255, 0.2);
    }

    .customer-row-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2rem;
        height: 2rem;
        padding: 0 0.55rem;
        border-radius: 9999px;
        background: rgba(15, 23, 42, 0.04);
        color: var(--text-muted);
        font-size: 0.78rem;
        font-weight: 700;
    }

    .badge-pill {
        border-radius: 9999px;
        padding: 0.35em 0.85em;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-plan {
        background-color: rgba(0, 153, 255, 0.1) !important;
        color: var(--secondary-color) !important;
        border: 1px solid rgba(0, 153, 255, 0.2) !important;
    }

    .badge-status-paid {
        background-color: rgba(16, 185, 129, 0.1) !important;
        color: #10b981 !important;
        border: 1px solid rgba(16, 185, 129, 0.2) !important;
    }

    .badge-status-pending {
        background-color: rgba(245, 158, 11, 0.1) !important;
        color: #d97706 !important;
        border: 1px solid rgba(245, 158, 11, 0.2) !important;
    }

    .badge-status-overdue {
        background-color: rgba(239, 68, 68, 0.1) !important;
        color: #ef4444 !important;
        border: 1px solid rgba(239, 68, 68, 0.2) !important;
    }

    .dot-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }

    .dot-green {
        background-color: #10b981;
    }

    .dot-yellow {
        background-color: #f59e0b;
    }

    .dot-red {
        background-color: #ef4444;
    }

    /* Tabla Glassmorphic limpia */
    .table-glass {
        margin-bottom: 0;
        color: var(--text-color);
    }
    
    .table-glass th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        font-weight: 600;
        border-bottom: 2px solid rgba(0, 0, 0, 0.03);
        padding: 1.1rem 1rem;
        background: transparent;
        white-space: nowrap;
    }
    
    .table-glass td {
        vertical-align: middle;
        border-bottom: 1px solid rgba(0, 0, 0, 0.03);
        padding: 1.1rem 1rem;
        background: transparent;
        color: var(--text-color);
        white-space: nowrap;
    }

    .table-glass tbody tr:hover td {
        background-color: rgba(255, 255, 255, 0.4) !important;
    }

    /* Estilos para buscadores y filtros */
    .form-control-glass,
    .form-select-glass {
        background-color: rgba(255, 255, 255, 0.6) !important;
        backdrop-filter: blur(5px) !important;
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        color: var(--text-color) !important;
        border-radius: 12px !important;
        padding: 0.6rem 1rem !important;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.01) !important;
        transition: all 0.2s ease !important;
    }

    .form-control-glass:focus,
    .form-select-glass:focus {
        background-color: white !important;
        border-color: var(--secondary-color) !important;
        box-shadow: 0 0 0 0.25rem rgba(0, 153, 255, 0.15) !important;
    }

    .btn-glass-action {
        background-color: rgba(255, 255, 255, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.8) !important;
        color: var(--text-muted) !important;
        border-radius: 12px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
    }

    .btn-glass-action:hover {
        background-color: white !important;
        color: var(--text-color) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;
    }

    #customerResultsRegion {
        transition: opacity 0.18s ease;
    }

    #customerResultsRegion.is-loading {
        opacity: 0.45;
        pointer-events: none;
    }

    .customer-row-inactive {
        opacity: 0.65;
        background-color: rgba(241, 245, 249, 0.3) !important;
    }
    .customer-row-inactive td {
        color: var(--text-muted) !important;
    }
    .customer-row-inactive a {
        color: var(--text-muted) !important;
        text-decoration: line-through !important;
    }
    .customer-status-badge {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
</style>
@endpush

@section('content')
<div class="customer-dashboard-glass">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 mb-1 fw-bold" style="color: var(--dark-bg);">Clientes</h2>
            <p class="text-muted mb-0 small">Registro comercial · contratos · historial de pagos</p>
        </div>
        <div class="d-flex gap-2">
            <span class="d-flex align-items-center px-3 py-1 rounded-pill small border" style="background: rgba(255, 255, 255, 0.5); border-color: rgba(255, 255, 255, 0.8); color: var(--text-muted);">
                <i class="fas fa-link me-2"></i> Clientes ≠ Usuarios del sistema
            </span>
            <a href="{{ route('customer.customers.historical.create') }}" class="btn btn-outline-primary px-4 py-2 d-flex align-items-center rounded-pill fw-semibold">
                <i class="fas fa-history me-2"></i> Cliente histórico
            </a>
            <a href="{{ route('customer.customers.create') }}" class="btn btn-primary px-4 py-2 d-flex align-items-center rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, var(--secondary-color), var(--primary-color)); border: none;">
                <i class="fas fa-plus me-2"></i> Nuevo cliente
            </a>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('customer.customers.index') }}" id="filterForm">
        <div class="row g-2 mb-4 align-items-center">
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="position-relative">
                    <i class="fas fa-search position-absolute top-50 translate-middle-y text-muted" style="left: 1rem;"></i>
                    <input
                        type="search"
                        id="customerSearchInput"
                        name="search"
                        class="form-control form-control-glass ps-5"
                        placeholder="Nombre, DNI, celular o correo..."
                        value="{{ request('search') }}"
                        autocomplete="off"
                    >
                </div>
            </div>
            <div class="col-xl col-lg-4 col-md-6">
                <select name="customer_active" class="form-select form-select-glass">
                    <option value="">Estado Cliente (Todos)</option>
                    <option value="1" {{ request('customer_active') === '1' ? 'selected' : '' }}>Activos</option>
                    <option value="0" {{ request('customer_active') === '0' ? 'selected' : '' }}>Inactivos</option>
                </select>
            </div>
            <div class="col-xl col-lg-4 col-md-6">
                <select name="contract_status" class="form-select form-select-glass">
                    <option value="">Contrato (Todos)</option>
                    <option value="active" {{ request('contract_status') === 'active' ? 'selected' : '' }}>Activos</option>
                    <option value="suspended" {{ request('contract_status') === 'suspended' ? 'selected' : '' }}>Suspendidos</option>
                    <option value="expired" {{ request('contract_status') === 'expired' ? 'selected' : '' }}>Finalizados</option>
                    <option value="cancelled" {{ request('contract_status') === 'cancelled' ? 'selected' : '' }}>Cancelados</option>
                </select>
            </div>
            <div class="col-xl col-lg-4 col-md-6">
                <select name="status" class="form-select form-select-glass">
                    <option value="">Estado Pago (Todos)</option>
                    <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Pagado</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pendiente</option>
                    <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>Vencido</option>
                </select>
            </div>
            <div class="col-xl col-lg-4 col-md-6">
                <select name="incomplete" class="form-select form-select-glass">
                    <option value="">Perfil (Todos)</option>
                    <option value="no_plan" {{ request('incomplete') == 'no_plan' ? 'selected' : '' }}>Sin Plan</option>
                    <option value="no_dni" {{ request('incomplete') == 'no_dni' ? 'selected' : '' }}>Sin DNI</option>
                    <option value="no_phone" {{ request('incomplete') == 'no_phone' ? 'selected' : '' }}>Sin Celular</option>
                </select>
            </div>
            <div class="col-xl col-lg-4 col-md-6">
                <select name="zone" class="form-select form-select-glass">
                    <option value="">Zonas (Todas)</option>
                    @foreach($zones as $z)
                    <option value="{{ $z->name }}" {{ request('zone') == $z->name ? 'selected' : '' }}>{{ $z->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl col-lg-4 col-md-6">
                <select name="plan" class="form-select form-select-glass">
                    <option value="">Planes (Todos)</option>
                    @foreach($plans as $p)
                    <option value="{{ $p->id }}" {{ request('plan') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-auto col-lg-4 col-md-6">
                <a href="{{ route('customer.customers.index') }}" class="btn btn-glass-action w-100 py-2" title="Limpiar Filtros">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </div>
    </form>

    <div id="customerResultsRegion">
        <!-- Table -->
        <div class="glass-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-glass">
                <thead>
                    <tr>
                        <th class="text-center">N°</th>
                        <th>Cliente</th>
                        <th>DNI</th>
                        <th>Celular</th>
                        <th>Plan</th>
                        <th>Zona</th>
                        <th>Fecha Pago</th>
                        <th>Estado</th>
                        <th>Cód. Compra</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                    @php
                    $activeContract = $customer->contracts->where('status', 'active')->first();
                    $plan = $activeContract ? $activeContract->plan : null;
                    $zone = $customer->addresses->first() ? $customer->addresses->first()->city : 'N/A';

                    $pendingInvoices = $customer->invoices->whereIn('status', ['pending', 'partial', 'overdue']);
                    if ($pendingInvoices->isNotEmpty()) {
                        $lastInvoice = $pendingInvoices->sortBy('due_date')->first();
                        $hasOverdue = $pendingInvoices->contains('status', 'overdue');
                        $estado = $hasOverdue ? 'Vencido' : 'Pendiente';
                    } else {
                        $lastInvoice = $customer->invoices->where('status', 'paid')->sortByDesc('due_date')->first();
                        $estado = $lastInvoice ? 'Pagado' : 'Pendiente';
                    }

                    $colorClass = $estado == 'Pagado' ? 'paid' : ($estado == 'Vencido' ? 'overdue' : 'pending');
                    $dotClass = $estado == 'Pagado' ? 'dot-green' : ($estado == 'Vencido' ? 'dot-red' : 'dot-yellow');
                    @endphp
                    <tr class="{{ !$customer->active ? 'customer-row-inactive' : '' }}">
                        <td class="text-center">
                            <span class="customer-row-number">
                                {{ ($customers->firstItem() ?? 1) + $loop->index }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle me-3 flex-shrink-0">
                                    {{ mb_substr($customer->first_name, 0, 1) }}{{ mb_substr($customer->last_name, 0, 1) }}
                                </div>
                                <div>
                                    <a href="{{ route('customer.customers.show', $customer->id) }}" class="fw-bold text-decoration-none d-block hover-primary" style="color: var(--text-color);">{{ $customer->first_name }} <br> {{ $customer->last_name }}</a>
                                    <div class="mt-1 d-flex flex-wrap gap-1 align-items-center">
                                        @if($customer->active)
                                            <span class="badge rounded-pill customer-status-badge px-2 py-0.5" style="background: rgba(16, 185, 129, 0.1) !important; color: #10b981 !important; border: 1px solid rgba(16, 185, 129, 0.2) !important;">Activo</span>
                                        @else
                                            <span class="badge rounded-pill customer-status-badge px-2 py-0.5" style="background: rgba(239, 68, 68, 0.1) !important; color: #ef4444 !important; border: 1px solid rgba(239, 68, 68, 0.2) !important;">Inactivo</span>
                                        @endif

                                        @php
                                            $contractStatus = 'none';
                                            if ($customer->contracts->where('status', 'active')->isNotEmpty()) {
                                                $contractStatus = 'active';
                                            } elseif ($customer->contracts->where('status', 'suspended')->isNotEmpty()) {
                                                $contractStatus = 'suspended';
                                            } elseif ($customer->contracts->whereIn('status', ['expired', 'renewed'])->isNotEmpty()) {
                                                $contractStatus = 'expired';
                                            } elseif ($customer->contracts->where('status', 'cancelled')->isNotEmpty()) {
                                                $contractStatus = 'cancelled';
                                            }
                                        @endphp

                                        @if($contractStatus === 'active')
                                            <span class="badge rounded-pill customer-status-badge px-2 py-0.5" style="background: rgba(13, 110, 253, 0.1) !important; color: #0d6efd !important; border: 1px solid rgba(13, 110, 253, 0.2) !important;">Contrato: Activo</span>
                                        @elseif($contractStatus === 'suspended')
                                            <span class="badge rounded-pill customer-status-badge px-2 py-0.5" style="background: rgba(253, 126, 20, 0.1) !important; color: #fd7e14 !important; border: 1px solid rgba(253, 126, 20, 0.2) !important;">Contrato: Suspendido</span>
                                        @elseif($contractStatus === 'expired')
                                            <span class="badge rounded-pill customer-status-badge px-2 py-0.5" style="background: rgba(108, 117, 125, 0.1) !important; color: #6c757d !important; border: 1px solid rgba(108, 117, 125, 0.2) !important;">Contrato: Finalizado</span>
                                        @elseif($contractStatus === 'cancelled')
                                            <span class="badge rounded-pill customer-status-badge px-2 py-0.5" style="background: rgba(220, 53, 69, 0.1) !important; color: #dc3545 !important; border: 1px solid rgba(220, 53, 69, 0.2) !important;">Contrato: Cancelado</span>
                                        @else
                                            <span class="badge rounded-pill customer-status-badge px-2 py-0.5" style="background: rgba(108, 117, 125, 0.05) !important; color: #6b7280 !important; border: 1px solid rgba(108, 117, 125, 0.1) !important;">Sin Contrato</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="text-muted">{{ $customer->identity_document ?: '-' }}</td>
                        <td class="text-muted">{{ $customer->phone ?: '-' }}</td>
                        <td>
                            @if($plan)
                            <span class="badge badge-pill badge-plan d-inline-flex align-items-center">
                                <span class="dot-indicator" style="background-color: var(--secondary-color);"></span> {{ $plan->download_speed }} Mbps · S/{{ $plan->price }}
                            </span>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            <span class="text-success small d-flex align-items-center fw-medium">
                                <span class="dot-indicator dot-green"></span> {{ $zone }}
                            </span>
                        </td>
                        <td class="text-muted">
                            {{ $lastInvoice ? \Carbon\Carbon::parse($lastInvoice->due_date)->format('d/M') : '-' }}
                        </td>
                        <td>
                            <span class="badge badge-pill badge-status-{{ $colorClass }} d-inline-flex align-items-center bg-transparent">
                                <span class="dot-indicator {{ $dotClass }}"></span> {{ $estado }}
                            </span>
                        </td>
                        <td class="text-muted small">
                            {{ $activeContract ? '#ISP-' . \Carbon\Carbon::parse($activeContract->created_at)->format('Y') . '-' . sprintf('%04d', $activeContract->id) : '-' }}
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-glass-action btn-sm rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="border-radius: 12px; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                                    <li><a class="dropdown-item rounded py-2" href="{{ route('customer.customers.show', $customer->id) }}"><i class="fas fa-eye me-2 text-info"></i> Ver Perfil</a></li>
                                    <li><a class="dropdown-item rounded py-2" href="{{ route('customer.customers.edit', $customer->id) }}"><i class="fas fa-edit me-2 text-warning"></i> Editar</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    @if($customer->active)
                                    <li>
                                        <form id="deactivate-customer-{{ $customer->id }}" action="{{ route('customer.customers.deactivate', $customer->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item rounded py-2 text-warning" onclick="return confirm('¿Desea desactivar este cliente y conservar su historial?');">
                                                <i class="fas fa-user-slash me-2"></i> Desactivar
                                            </button>
                                        </form>
                                    </li>
                                    @endif
                                    <li>
                                        <form action="{{ route('customer.customers.destroy', $customer->id) }}"
                                              method="POST"
                                              data-customer-name="{{ $customer->full_name }}"
                                              data-contract-count="{{ $customer->contracts->count() }}"
                                              data-deactivate-form-id="deactivate-customer-{{ $customer->id }}"
                                              onsubmit="return confirmCustomerDelete(this);">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="delete_deletable_contracts" value="0">
                                            <input type="hidden" name="force_delete_related_history" value="0">
                                            <button type="submit" class="dropdown-item rounded py-2 text-danger"><i class="fas fa-trash me-2"></i> Eliminar</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            No se encontraron clientes.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>

        <div class="mt-4 d-flex justify-content-end">
            {{ $customers->appends(request()->except('page'))->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchContainer = document.getElementById('customerSearchContainer');
        const searchInput = document.getElementById('customerSearchInput');
        const searchResults = document.getElementById('customerSearchResults');
        const searchUrl = @json(route('customer.customers.search-suggestions'));
        if (!searchResults) {
            return;
        }
        let debounceTimer = null;
        let requestController = null;
        let activeIndex = -1;

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (character) {
                return ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                })[character];
            });
        }

        function closeResults() {
            searchResults.classList.remove('active');
            searchInput.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        }

        function showState(message) {
            searchResults.innerHTML = `<div class="customer-search-state">${escapeHtml(message)}</div>`;
            searchResults.classList.add('active');
            searchInput.setAttribute('aria-expanded', 'true');
            activeIndex = -1;
        }

        function renderResults(customers) {
            if (!customers.length) {
                showState('No se encontraron clientes.');
                return;
            }

            searchResults.innerHTML = customers.map((customer, index) => `
                <a
                    href="${escapeHtml(customer.url)}"
                    class="customer-search-result"
                    role="option"
                    data-result-index="${index}"
                >
                    <span class="customer-search-avatar">${escapeHtml(customer.initials || 'CL')}</span>
                    <span class="min-w-0">
                        <span class="d-block fw-bold text-truncate">${escapeHtml(customer.name)}</span>
                        <span class="d-block small text-muted text-truncate">
                            DNI/RUC: ${escapeHtml(customer.document || '-')}
                            · Cel: ${escapeHtml(customer.phone || '-')}
                        </span>
                    </span>
                </a>
            `).join('');
            searchResults.classList.add('active');
            searchInput.setAttribute('aria-expanded', 'true');
            activeIndex = -1;
        }

        function moveActiveResult(direction) {
            const results = Array.from(searchResults.querySelectorAll('.customer-search-result'));
            if (!results.length) {
                return;
            }

            activeIndex = (activeIndex + direction + results.length) % results.length;
            results.forEach((result, index) => result.classList.toggle('active', index === activeIndex));
            results[activeIndex].scrollIntoView({ block: 'nearest' });
        }

        async function searchCustomers() {
            const query = searchInput.value.trim();

            if (query.length < 2) {
                closeResults();
                return;
            }

            requestController?.abort();
            requestController = new AbortController();
            showState('Buscando clientes...');

            try {
                const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: requestController.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                renderResults(Array.isArray(payload.results) ? payload.results : []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    showState('No se pudo completar la búsqueda.');
                }
            }
        }

        searchInput.addEventListener('input', function () {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(searchCustomers, 250);
        });

        searchInput.addEventListener('focus', function () {
            if (searchInput.value.trim().length >= 2) {
                searchCustomers();
            }
        });

        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                moveActiveResult(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                moveActiveResult(-1);
            } else if (event.key === 'Escape') {
                closeResults();
            } else if (event.key === 'Enter' && activeIndex >= 0) {
                const activeResult = searchResults.querySelector('.customer-search-result.active');
                if (activeResult) {
                    event.preventDefault();
                    window.location.href = activeResult.href;
                }
            }
        });

        document.addEventListener('click', function (event) {
            if (!searchContainer.contains(event.target)) {
                closeResults();
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        const filterForm = document.getElementById('filterForm');
        const searchInput = document.getElementById('customerSearchInput');
        const filterSelects = filterForm.querySelectorAll('select');
        let debounceTimer = null;
        let requestController = null;

        async function updateCustomerTable(url, updateHistory = true) {
            const currentRegion = document.getElementById('customerResultsRegion');
            currentRegion.classList.add('is-loading');

            requestController?.abort();
            requestController = new AbortController();

            try {
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: requestController.signal,
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const html = await response.text();
                const documentFragment = new DOMParser().parseFromString(html, 'text/html');
                const nextRegion = documentFragment.getElementById('customerResultsRegion');

                if (!nextRegion) {
                    throw new Error('No se encontró el listado actualizado.');
                }

                currentRegion.replaceWith(nextRegion);

                if (updateHistory) {
                    window.history.replaceState({}, '', url);
                }
            } catch (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = url;
                }
            } finally {
                document.getElementById('customerResultsRegion')?.classList.remove('is-loading');
            }
        }

        function filteredUrl() {
            const parameters = new URLSearchParams(new FormData(filterForm));
            parameters.delete('page');
            const query = parameters.toString();

            return `${filterForm.action}${query ? `?${query}` : ''}`;
        }

        function scheduleTableUpdate() {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(() => updateCustomerTable(filteredUrl()), 350);
        }

        searchInput.addEventListener('input', scheduleTableUpdate);
        filterSelects.forEach((select) => select.addEventListener('change', function () {
            window.clearTimeout(debounceTimer);
            updateCustomerTable(filteredUrl());
        }));

        filterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(debounceTimer);
            updateCustomerTable(filteredUrl());
        });

        document.addEventListener('click', function (event) {
            const paginationLink = event.target.closest('#customerResultsRegion .pagination a');

            if (!paginationLink) {
                return;
            }

            event.preventDefault();
            updateCustomerTable(paginationLink.href);
        });
    });

    function confirmCustomerDelete(form) {
        const customerName = form.dataset.customerName || 'este cliente';
        const contractCount = Number(form.dataset.contractCount || 0);

        if (contractCount === 0) {
            return confirm('¿Está seguro de eliminar a ' + customerName + '? Esta acción no se puede deshacer.');
        }

        if (confirm(
            customerName + ' tiene ' + contractCount + ' contrato(s).\n\n' +
            'Se eliminará el cliente junto con sus contratos relacionados.\n\n' +
            'ADVERTENCIA: si esos contratos tienen facturas o instalaciones, también se eliminará ese historial relacionado.\n\n' +
            '¿Desea continuar?'
        )) {
            if (!confirm('Última confirmación: esta acción eliminará información relacionada y no se puede deshacer. ¿Confirmar eliminación completa?')) {
                return false;
            }

            form.querySelector('[name="delete_deletable_contracts"]').value = '1';
            form.querySelector('[name="force_delete_related_history"]').value = '1';
            return true;
        }

        return false;
    }
</script>
@endsection
