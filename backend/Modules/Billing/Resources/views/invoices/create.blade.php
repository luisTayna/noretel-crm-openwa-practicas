@extends('core::layouts.master')

@section('title', 'Crear Recibo')
@section('page-title', 'Crear Recibo')

@section('content')
<form action="{{ route('billing.invoices.store') }}" method="POST" id="invoice-form">
    @csrf

    <div class="row">
        <div class="col-md-8">
            <!-- Detalles de Recibo -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-file-invoice text-primary me-2"></i> Detalles de Recibo
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="contract_id" class="form-label">Contrato <span class="text-danger">*</span></label>
                            <select class="form-select" id="contract_id" name="contract_id" required>
                                <option value="">Buscar contrato por nombre o número...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="invoice_number" class="form-label">Número de Recibo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="invoice_number" name="invoice_number" value="{{ $invoiceNumber }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="issue_date" class="form-label">Fecha de Emisión <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="issue_date" name="issue_date" value="{{ $issueDate }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Fecha de Vencimiento <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="due_date" name="due_date" value="{{ $dueDate }}" required>
                        </div>
                        <div class="col-md-6">
                            <label for="billing_period" class="form-label">Período del recibo</label>
                            <input type="text" class="form-control" id="billing_period" name="billing_period" placeholder="Ej: Mayo 2023">
                        </div>
                        <div class="col-md-6">
                            <label for="generation_type" class="form-label">Tipo de Generación</label>
                            <select class="form-select" id="generation_type" name="generation_type">
                                <option value="manual">Manual</option>
                                <option value="automatic">Automática</option>
                                <option value="recurring">Recurrente</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información de Cliente -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-user text-primary me-2"></i> Información del recibo
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="billing_name" class="form-label">Nombre/Razón Social</label>
                            <input type="text" class="form-control" id="billing_name" name="billing_name">
                        </div>
                        <div class="col-md-6">
                            <label for="billing_document" class="form-label">Documento</label>
                            <input type="text" class="form-control" id="billing_document" name="billing_document">
                        </div>
                        <div class="col-md-6">
                            <label for="billing_address" class="form-label">Dirección</label>
                            <input type="text" class="form-control" id="billing_address" name="billing_address">
                        </div>
                        <div class="col-md-6">
                            <label for="billing_email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="billing_email" name="billing_email">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items de Recibo -->
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list text-primary me-2"></i> Items
                        </h5>
                        <button type="button" class="btn btn-sm btn-primary" id="add-item-btn">
                            <i class="fas fa-plus me-1"></i> Agregar Item
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 60%;">Descripción</th>
                                    <th style="width: 15%;">Cantidad</th>
                                    <th style="width: 15%;">Precio Unitario</th>
                                    <th style="width: 10%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Aquí se agregarán los items dinámicamente -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2" class="text-end">Total (IGV Incluido):</th>
                                    <th class="text-end" id="grand-total">0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <!-- Mensaje de error para items -->
                    <div id="items-error" class="text-danger d-none mt-2">
                        Debe agregar al menos un item a la recibo.
                    </div>
                </div>
            </div>

            <!-- Notas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-sticky-note text-primary me-2"></i> Notas Adicionales
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Acciones -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-save text-primary me-2"></i> Acciones
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Guardar Recibo
                        </button>
                        <a href="{{ route('billing.invoices.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>

            <!-- Vista Previa -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-eye text-primary me-2"></i> Vista Previa
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <h4 id="preview-invoice-number">{{ $invoiceNumber }}</h4>
                        <div id="preview-status" class="badge bg-secondary">Borrador</div>
                    </div>
                    <div class="row">
                        <div class="col-6 text-muted">Cliente:</div>
                        <div class="col-6 text-end" id="preview-customer">-</div>

                        <div class="col-6 text-muted">Fecha:</div>
                        <div class="col-6 text-end" id="preview-date">{{ $issueDate }}</div>

                        <div class="col-6 text-muted">Vencimiento:</div>
                        <div class="col-6 text-end" id="preview-due-date">{{ $dueDate }}</div>

                        <div class="col-6 text-muted">Total:</div>
                        <div class="col-6 text-end" id="preview-total">0.00</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Template para items de recibo -->
<template id="item-template">
    <tr class="item-row">
        <td>
            <input type="text" class="form-control item-description" name="items[{index}][description]" required>
            <input type="hidden" name="items[{index}][item_type]" value="manual">
            <input type="hidden" name="items[{index}][service_id]" value="">
        </td>
        <td>
            <input type="number" class="form-control item-quantity" name="items[{index}][quantity]" value="1" min="1" step="1" required>
        </td>
        <td>
            <input type="number" class="form-control item-price" name="items[{index}][unit_price]" value="0.00" min="0" step="0.01" required>
            <input type="hidden" class="item-tax-rate" name="items[{index}][tax_rate]" value="0">
            <input type="hidden" class="item-tax-amount" name="items[{index}][tax_amount]" value="0.00">
            <input type="hidden" class="item-amount" name="items[{index}][amount]" value="0.00">
            <input type="hidden" class="item-discount" name="items[{index}][discount]" value="0.00">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger remove-item-btn">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

@endsection

@push('styles')
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endpush

@push('scripts')
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Variables
    let itemCount = 0;
    const itemTemplate = document.getElementById('item-template').innerHTML;

    // Inicializar Select2 con AJAX
    $('#contract_id').select2({
        theme: 'bootstrap-5',
        placeholder: 'Buscar contrato por nombre, DNI o número...',
        allowClear: true,
        ajax: {
            url: '{{ route("contract.api.contracts.search") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // término de búsqueda
                };
            },
            processResults: function (data) {
                return {
                    results: data.results
                };
            },
            cache: true
        }
    });

    // Actualizar información de cliente al seleccionar contrato
    $('#contract_id').on('select2:select', function (e) {
        const data = e.params.data;
        if (data && data.customer) {
            const customerData = data.customer;
            
            // Determinar el nombre (soporte para razón social vs individual)
            const fullName = customerData.customer_type === 'business' 
                ? (customerData.first_name || customerData.last_name || '') 
                : (customerData.first_name + ' ' + customerData.last_name);
                
            document.getElementById('billing_name').value = fullName;
            document.getElementById('billing_document').value = customerData.identity_document || '';
            document.getElementById('billing_email').value = customerData.email || '';

            // Buscar la dirección principal
            let address = '';
            if (customerData.addresses && customerData.addresses.length > 0) {
                // Tratar de encontrar la principal o de cobranza
                const primary = customerData.addresses.find(a => a.is_primary == 1 || a.address_type === 'billing');
                const addr = primary || customerData.addresses[0];
                address = `${addr.street} ${addr.number || ''}, ${addr.city}`;
            }
            document.getElementById('billing_address').value = address;

            // Actualizar vista previa
            document.getElementById('preview-customer').textContent = fullName;
            
            // Auto-poblar items de recibo con el plan y servicios del contrato
            const tbody = document.querySelector('#items-table tbody');
            tbody.innerHTML = ''; // Limpiar items actuales
            itemCount = 0; // Reiniciar contador
            
            if (data.plan) {
                addPredefinedItem(`Plan ${data.plan.name}`, 1, data.plan.price, 'plan', data.plan.id);
            }
            
            if (data.contracted_services && data.contracted_services.length > 0) {
                data.contracted_services.forEach(service => {
                    addPredefinedItem(service.name, 1, service.price, 'additional_service', service.id);
                });
            }
            
            if (itemCount === 0) {
                // Si por alguna razón no tiene plan ni servicios, agregar uno vacío
                addItem();
            }
        }
    });

    $('#contract_id').on('select2:unselect', function (e) {
        // Limpiar campos
        document.getElementById('billing_name').value = '';
        document.getElementById('billing_document').value = '';
        document.getElementById('billing_address').value = '';
        document.getElementById('billing_email').value = '';

        // Actualizar vista previa
        document.getElementById('preview-customer').textContent = '-';
        
        // Limpiar items
        const tbody = document.querySelector('#items-table tbody');
        tbody.innerHTML = '';
        itemCount = 0;
        addItem();
    });

    // Agregar item
    document.getElementById('add-item-btn').addEventListener('click', function() {
        addItem();
    });

    // Eliminar item
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item-btn') || e.target.closest('.remove-item-btn')) {
            const row = e.target.closest('.item-row');
            row.remove();
            updateTotals();

            // Mostrar mensaje si no hay items
            toggleErrorMessage();
        }
    });

    // Actualizar cálculos cuando cambian los inputs
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-quantity') ||
            e.target.classList.contains('item-price') ||
            e.target.classList.contains('item-tax-rate')) {

            const row = e.target.closest('.item-row');
            updateRowCalculations(row);
            updateTotals();
        }
    });

    // Actualizar fechas en vista previa
    document.getElementById('issue_date').addEventListener('change', function() {
        document.getElementById('preview-date').textContent = this.value;
    });

    document.getElementById('due_date').addEventListener('change', function() {
        document.getElementById('preview-due-date').textContent = this.value;
    });

    // Actualizar número de recibo en vista previa
    document.getElementById('invoice_number').addEventListener('input', function() {
        document.getElementById('preview-invoice-number').textContent = this.value;
    });

    // Validar formulario antes de enviar
    document.getElementById('invoice-form').addEventListener('submit', function(e) {
        const itemRows = document.querySelectorAll('.item-row');

        if (itemRows.length === 0) {
            e.preventDefault();
            document.getElementById('items-error').classList.remove('d-none');
            return false;
        }

        return true;
    });

    // Funciones
    function addItem() {
        const tbody = document.querySelector('#items-table tbody');
        let newRow = itemTemplate.replace(/{index}/g, itemCount);

        tbody.insertAdjacentHTML('beforeend', newRow);

        // Inicializar cálculos
        const row = tbody.lastElementChild;
        updateRowCalculations(row);

        // Incrementar contador
        itemCount++;

        // Ocultar mensaje de error
        document.getElementById('items-error').classList.add('d-none');

        // Actualizar totales
        updateTotals();
    }

    function addPredefinedItem(description, quantity, price, type, serviceId) {
        const tbody = document.querySelector('#items-table tbody');
        let newRow = itemTemplate.replace(/{index}/g, itemCount);
        
        tbody.insertAdjacentHTML('beforeend', newRow);
        
        const row = tbody.lastElementChild;
        
        // Asignar valores
        row.querySelector('.item-description').value = description || '';
        row.querySelector('.item-quantity').value = quantity || 1;
        row.querySelector('.item-price').value = price || '0.00';
        row.querySelector('input[name="items[' + itemCount + '][item_type]"]').value = type || 'manual';
        row.querySelector('input[name="items[' + itemCount + '][service_id]"]').value = serviceId || '';
        
        // Bloquear edición de descripción y precio si es de plan/servicio (opcional, pero buena UX)
        if (type !== 'manual') {
            row.querySelector('.item-description').readOnly = true;
            row.querySelector('.item-price').readOnly = true;
        }

        // Inicializar cálculos
        updateRowCalculations(row);

        // Incrementar contador
        itemCount++;

        // Ocultar mensaje de error
        document.getElementById('items-error').classList.add('d-none');

        // Actualizar totales
        updateTotals();
    }

    function updateRowCalculations(row) {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const unitPrice = parseFloat(row.querySelector('.item-price').value) || 0;
        const discount = parseFloat(row.querySelector('.item-discount').value) || 0;

        // Calcular subtotal (quantity * unitPrice - discount)
        const subtotal = (quantity * unitPrice) - discount;

        // Calcular total (IGV ya incluido)
        const total = subtotal;

        // Actualizar campos
        if (row.querySelector('.item-tax-amount')) {
            row.querySelector('.item-tax-amount').value = "0.00";
        }
        row.querySelector('.item-amount').value = total.toFixed(2);
    }

    function updateTotals() {
        let grandTotal = 0;

        // Sumar todos los items
        document.querySelectorAll('.item-row').forEach(row => {
            const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const unitPrice = parseFloat(row.querySelector('.item-price').value) || 0;
            const discount = parseFloat(row.querySelector('.item-discount').value) || 0;

            const subtotal = (quantity * unitPrice) - discount;
            grandTotal += parseFloat(row.querySelector('.item-amount').value) || 0;
        });

        // Actualizar totales en la tabla
        document.getElementById('grand-total').textContent = grandTotal.toFixed(2);

        // Actualizar vista previa
        document.getElementById('preview-total').textContent = grandTotal.toFixed(2);
    }

    function toggleErrorMessage() {
        const itemRows = document.querySelectorAll('.item-row');
        if (itemRows.length === 0) {
            document.getElementById('items-error').classList.remove('d-none');
        } else {
            document.getElementById('items-error').classList.add('d-none');
        }
    }

    // Agregar un item por defecto
    addItem();
});
</script>
@endpush
