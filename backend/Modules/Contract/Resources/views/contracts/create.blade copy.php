@extends('core::layouts.master')

@section('title', 'Crear Contrato')
@section('page-title', 'Crear Nuevo Contrato')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
    }
    
    .form-section-title {
        margin-bottom: 1.5rem;
        color: var(--primary-color);
        font-weight: 600;
    }
    
    .nav-tabs .nav-link.active {
        border-color: var(--primary-color);
        color: var(--primary-color);
        font-weight: 500;
    }
    
    .additional-service-card {
        border-radius: 0.5rem;
        border: 1px solid #ddd;
        margin-bottom: 1rem;
        transition: all 0.3s ease;
    }
    
    .additional-service-card.selected {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 1px var(--primary-color);
    }
    
    .additional-service-card .card-header {
        cursor: pointer;
        padding: 0.75rem;
    }
    
    .price-calculation-box {
        background-color: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-top: 1rem;
    }
    
    .price-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }
    
    .price-row.total {
        font-weight: bold;
        border-top: 1px solid #ddd;
        padding-top: 0.5rem;
        margin-top: 0.5rem;
    }
</style>
@endpush

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-file-contract"></i> Formulario de Contrato</h5>
        <a href="{{ route('contract.contracts.index') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
    
    <div class="card-body">
        <form action="{{ route('contract.contracts.store') }}" method="POST" id="contractForm">
            @csrf
            
            <ul class="nav nav-tabs mb-4" id="contractTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info-tab-pane" type="button" role="tab" aria-controls="info-tab-pane" aria-selected="true">
                        <i class="fas fa-info-circle"></i> Información General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#services-tab-pane" type="button" role="tab" aria-controls="services-tab-pane" aria-selected="false">
                        <i class="fas fa-plus-circle"></i> Servicios Adicionales
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="technical-tab" data-bs-toggle="tab" data-bs-target="#technical-tab-pane" type="button" role="tab" aria-controls="technical-tab-pane" aria-selected="false">
                        <i class="fas fa-network-wired"></i> Información Técnica
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="contractTabContent">
                <!-- Pestaña de Información General -->
                <div class="tab-pane fade show active" id="info-tab-pane" role="tabpanel" aria-labelledby="info-tab" tabindex="0">
                    <div class="form-section">
                        <h6 class="form-section-title">Información del Cliente</h6>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="customer_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                                    <option value="">Seleccionar Cliente</option>
                                    @if(isset($customer) && $customer)
                                        <option value="{{ $customer->id }}" selected>{{ $customer->first_name }} {{ $customer->last_name }} ({{ $customer->identity_document }})</option>
                                    @endif
                                    
                                    @foreach($customers as $cust)
                                        @if(!isset($customer) || $customer->id != $cust->id)
                                            <option value="{{ $cust->id }}" {{ old('customer_id') == $cust->id ? 'selected' : '' }}>
                                                {{ $cust->first_name }} {{ $cust->last_name }} ({{ $cust->identity_document }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('customer_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Busca y selecciona el cliente para el contrato. Si el cliente no existe, <a href="{{ route('customer.customers.create') }}" target="_blank">crea uno nuevo</a>.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h6 class="form-section-title">Información del Plan</h6>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="plan_id" class="form-label">Plan de Servicio <span class="text-danger">*</span></label>
                                <select name="plan_id" id="plan_id" class="form-select @error('plan_id') is-invalid @enderror" required>
                                    <option value="">Seleccionar Plan</option>
                                    @foreach($plans as $plan)
                                        <option value="{{ $plan->id }}" data-price="{{ $plan->price }}" {{ old('plan_id') == $plan->id ? 'selected' : '' }}>
                                            {{ $plan->name }} - {{ $plan->service->name }} ({{ $plan->download_speed }}/{{ $plan->upload_speed }} Mbps)
                                        </option>
                                    @endforeach
                                </select>
                                @error('plan_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Fecha de Inicio <span class="text-danger">*</span></label>
                                <input type="text" name="start_date" id="start_date" class="form-control datepicker @error('start_date') is-invalid @enderror" value="{{ old('start_date', date('Y-m-d')) }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Fecha de Fin</label>
                                <input type="text" name="end_date" id="end_date" class="form-control datepicker @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Deja en blanco para contratos de duración indefinida.</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña de Servicios Adicionales -->
                <div class="tab-pane fade" id="services-tab-pane" role="tabpanel" aria-labelledby="services-tab" tabindex="0">
                    <div class="form-section">
                        <h6 class="form-section-title">Servicios Adicionales</h6>
                        <p>Selecciona los servicios adicionales que deseas incluir en el contrato:</p>
                        
                        <div class="row">
                            @foreach($additionalServices as $service)
                            <div class="col-md-6 mb-3">
                                <div class="card additional-service-card" id="service-card-{{ $service->id }}">
                                    <div class="card-header d-flex justify-content-between align-items-center" onclick="toggleService({{ $service->id }})">
                                        <h6 class="mb-0">{{ $service->name }}</h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input service-checkbox" type="checkbox" 
                                                   id="service_{{ $service->id }}" 
                                                   name="additional_services[{{ $service->id }}][selected]" 
                                                   value="1"
                                                   data-price="{{ $service->price }}"
                                                   {{ old("additional_services.{$service->id}.selected") ? 'checked' : '' }}
                                                   onchange="toggleService({{ $service->id }})">
                                        </div>
                                    </div>
                                    <div class="card-body" id="service-body-{{ $service->id }}" style="display: none;">
                                        <p>{{ $service->description }}</p>
                                        <div class="form-group">
                                            <label for="service_price_{{ $service->id }}" class="form-label">Precio</label>
                                            <input type="number" step="0.01" min="0" 
                                                   class="form-control service-price" 
                                                   id="service_price_{{ $service->id }}" 
                                                   name="additional_services[{{ $service->id }}][price]"
                                                   value="{{ old("additional_services.{$service->id}.price", $service->price) }}"
                                                   onchange="calculateTotalPrice()">
                                        </div>
                                        
                                        @if($service->configurable)
                                        <div class="form-group mt-3">
                                            <label for="service_config_{{ $service->id }}" class="form-label">Configuración</label>
                                            <textarea class="form-control" 
                                                      id="service_config_{{ $service->id }}" 
                                                      name="additional_services[{{ $service->id }}][configuration]"
                                                      rows="3">{{ old("additional_services.{$service->id}.configuration") }}</textarea>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <!-- Pestaña de Información Técnica -->
                <div class="tab-pane fade" id="technical-tab-pane" role="tabpanel" aria-labelledby="technical-tab" tabindex="0">
                    <div class="form-section">
                        <h6 class="form-section-title">Información de Nodo y Conexión</h6>
                        <div class="row mb-3">
                            <!--
                            <div class="col-md-6">
                                <label for="node_id" class="form-label">Nodo <span class="text-danger">*</span></label>
                                <select name="node_id" id="node_id" class="form-select @error('node_id') is-invalid @enderror" required>
                                    <option value="">Seleccionar Nodo</option>
                                    @foreach($nodes as $node)
                                        <option value="{{ $node->id }}" {{ old('node_id') == $node->id ? 'selected' : '' }}>
                                            {{ $node->name }} ({{ $node->used_capacity }}/{{ $node->total_capacity }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('node_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            -->
                            <div class="col-md-6">
                                <label for="sla_id" class="form-label">Acuerdo de Nivel de Servicio (SLA)</label>
                                <select name="sla_id" id="sla_id" class="form-select @error('sla_id') is-invalid @enderror">
                                    <option value="">Sin SLA</option>
                                    @foreach($slas as $sla)
                                        <option value="{{ $sla->id }}" {{ old('sla_id') == $sla->id ? 'selected' : '' }}>
                                            {{ $sla->name }} ({{ $sla->service_level }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('sla_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="assigned_ip" class="form-label">IP Asignada</label>
                                <input type="text" name="assigned_ip" id="assigned_ip" class="form-control @error('assigned_ip') is-invalid @enderror" value="{{ old('assigned_ip') }}">
                                @error('assigned_ip')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Puedes asignar una IP ahora o después de la instalación.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="vlan" class="form-label">VLAN</label>
                                <input type="text" name="vlan" id="vlan" class="form-control @error('vlan') is-invalid @enderror" value="{{ old('vlan') }}">
                                @error('vlan')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h6 class="form-section-title">Información de Precio</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="final_price" class="form-label">Precio Final <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="final_price" id="final_price" class="form-control @error('final_price') is-invalid @enderror" value="{{ old('final_price') }}" required>
                                @error('final_price')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <div class="price-calculation-box">
                                    <h6>Cálculo de Precio</h6>
                                    <div class="price-row">
                                        <span>Plan Base:</span>
                                        <span id="base-plan-price">$0.00</span>
                                    </div>
                                    <div id="additional-services-prices"></div>
                                    <div class="price-row total">
                                        <span>Total:</span>
                                        <span id="total-price">$0.00</span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="applyCalculatedPrice()">
                                        Aplicar Precio Calculado
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="navigateTab('prev')" disabled>
                    <i class="fas fa-arrow-left"></i> Anterior
                </button>
                <button type="button" class="btn btn-primary" id="nextBtn" onclick="navigateTab('next')">
                    Siguiente <i class="fas fa-arrow-right"></i>
                </button>
                <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                    <i class="fas fa-save"></i> Guardar Contrato
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>
    let currentTab = 0;
    const tabs = ['info-tab', 'services-tab', 'technical-tab'];
    
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar datepickers
        flatpickr(".datepicker", {
            locale: "es",
            dateFormat: "Y-m-d",
            allowInput: true
        });
        
        // Inicializar select2 para búsqueda avanzada
        if (typeof $.fn.select2 !== 'undefined') {
            $('#customer_id').select2({
                placeholder: 'Buscar cliente...',
                allowClear: true
            });
        }
        
        // Calcular precio inicial
        calculateTotalPrice();
        
        // Manejar cambio en el plan seleccionado
        document.getElementById('plan_id').addEventListener('change', calculateTotalPrice);
    });
    
    function navigateTab(direction) {
        if (direction === 'next') {
            if (currentTab < tabs.length - 1) {
                currentTab++;
            }
        } else if (direction === 'prev') {
            if (currentTab > 0) {
                currentTab--;
            }
        }
        
        // Activar la pestaña correspondiente
        document.getElementById(tabs[currentTab]).click();
        
        // Actualizar estado de los botones
        updateButtons();
    }
    
    function updateButtons() {
        document.getElementById('prevBtn').disabled = (currentTab === 0);
        
        if (currentTab === tabs.length - 1) {
            document.getElementById('nextBtn').style.display = 'none';
            document.getElementById('submitBtn').style.display = 'block';
        } else {
            document.getElementById('nextBtn').style.display = 'block';
            document.getElementById('submitBtn').style.display = 'none';
        }
    }
    
    function toggleService(serviceId) {
        const checkbox = document.getElementById(`service_${serviceId}`);
        const card = document.getElementById(`service-card-${serviceId}`);
        const body = document.getElementById(`service-body-${serviceId}`);
        
        if (checkbox.checked) {
            card.classList.add('selected');
            body.style.display = 'block';
        } else {
            card.classList.remove('selected');
            body.style.display = 'none';
        }
        
        calculateTotalPrice();
    }
    
    function calculateTotalPrice() {
        let totalPrice = 0;
        let additionalServicesHtml = '';
        
        // Obtener precio del plan base
        const planSelect = document.getElementById('plan_id');
        const selectedPlan = planSelect.options[planSelect.selectedIndex];
        
        if (selectedPlan && selectedPlan.value) {
            const planPrice = parseFloat(selectedPlan.getAttribute('data-price')) || 0;
            totalPrice += planPrice;
            document.getElementById('base-plan-price').textContent = `$${planPrice.toFixed(2)}`;
        } else {
            document.getElementById('base-plan-price').textContent = '$0.00';
        }
        
        // Calcular precios de servicios adicionales
        const serviceCheckboxes = document.querySelectorAll('.service-checkbox:checked');
        serviceCheckboxes.forEach(checkbox => {
            const serviceId = checkbox.id.replace('service_', '');
            const priceInput = document.getElementById(`service_price_${serviceId}`);
            const price = parseFloat(priceInput.value) || 0;
            
            totalPrice += price;
            
            const serviceName = document.querySelector(`#service-card-${serviceId} .card-header h6`).textContent;
            additionalServicesHtml += `
                <div class="price-row">
                    <span>${serviceName}:</span>
                    <span>$${price.toFixed(2)}</span>
                </div>
            `;
        });
        
        // Actualizar HTML con los servicios adicionales
        document.getElementById('additional-services-prices').innerHTML = additionalServicesHtml;
        
        // Actualizar precio total
        document.getElementById('total-price').textContent = `$${totalPrice.toFixed(2)}`;
    }
    
    function applyCalculatedPrice() {
        const totalPriceText = document.getElementById('total-price').textContent;
        const totalPrice = parseFloat(totalPriceText.replace('$', '')) || 0;
        
        document.getElementById('final_price').value = totalPrice.toFixed(2);
    }
    
    // Event listeners para las pestañas
    tabs.forEach((tabId, index) => {
        document.getElementById(tabId).addEventListener('shown.bs.tab', function (e) {
            currentTab = index;
            updateButtons();
        });
    });
</script>
@endpush