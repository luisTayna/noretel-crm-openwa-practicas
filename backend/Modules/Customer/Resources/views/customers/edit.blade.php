@extends('core::layouts.master')

@section('title', 'Editar Cliente')
@section('page-title', 'Editar Cliente')

@push('styles')
<style>
    /*
     * La vista de edición usa cards con glassmorphism y stacking contexts propios.
     * En algunos navegadores el backdrop de Bootstrap quedaba por encima del modal,
     * dejando la ventana oscurecida y aparentemente bloqueada.
     * Se limita el ajuste a esta pantalla para no afectar otros módulos.
     */
    #addAddressModal,
    #addEmergencyContactModal {
        background: transparent !important;
        z-index: 3000 !important;
        pointer-events: auto !important;
    }

    #addAddressModal.show,
    #addEmergencyContactModal.show {
        background: transparent !important;
    }

    #addAddressModal .modal-dialog,
    #addEmergencyContactModal .modal-dialog,
    #addAddressModal .modal-content,
    #addEmergencyContactModal .modal-content {
        pointer-events: auto !important;
    }

    body.modal-open .modal-backdrop {
        z-index: 2990 !important;
    }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-12 mb-3">
        <div class="d-sm-flex align-items-center justify-content-between">
            <h1 class="h3 mb-0 text-gray-800">Editar Cliente: {{ $customer->full_name }}</h1>
            <div class="action-btn-group">
                <a href="{{ route('customer.customers.show', $customer->id) }}" class="action-btn" title="Volver al Detalle">
                    <i class="fas fa-eye"></i>
                </a>
                <a href="{{ route('customer.customers.index') }}" class="action-btn" title="Volver al Listado">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<form action="{{ route('customer.customers.update', $customer->id) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="row">
        <!-- Información Personal -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Información Personal
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="customer_type" class="form-label">Tipo de Cliente <span class="text-danger">*</span></label>
                        <select name="customer_type" id="customer_type" class="form-select @error('customer_type') is-invalid @enderror" required>
                            @foreach($customerTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('customer_type', $customer->customer_type) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                        @error('customer_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="first_name" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $customer->first_name) }}" required>
                        @error('first_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="last_name" class="form-label">Apellido <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $customer->last_name) }}" required>
                        @error('last_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="identity_document" class="form-label">Documento de Identidad <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('identity_document') is-invalid @enderror" id="identity_document" name="identity_document" value="{{ old('identity_document', $customer->identity_document) }}" required>
                        <small class="form-text text-muted">Obligatorio. Puede repetirse si la misma persona tiene más de un servicio.</small>
                        @error('identity_document')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $customer->email) }}">
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Teléfono</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $customer->phone) }}">
                        @error('phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="credit_score" class="form-label">Puntaje Crediticio</label>
                        <input type="number" class="form-control @error('credit_score') is-invalid @enderror" id="credit_score" name="credit_score" min="0" max="1000" value="{{ old('credit_score', $customer->credit_score) }}">
                        @error('credit_score')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="segment" class="form-label">Segmento</label>
                        <select name="segment" id="segment" class="form-select @error('segment') is-invalid @enderror">
                            <option value="">Seleccionar segmento</option>
                            @foreach($segments as $value => $label)
                            <option value="{{ $value }}" {{ old('segment', $customer->segment) == $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                        @error('segment')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="contact_preferences" class="form-label">Preferencias de Contacto</label>
                        <select name="contact_preferences" id="contact_preferences" class="form-select @error('contact_preferences') is-invalid @enderror">
                            <option value="email" {{ old('contact_preferences', $customer->contact_preferences) == 'email' ? 'selected' : '' }}>Email</option>
                            <option value="phone" {{ old('contact_preferences', $customer->contact_preferences) == 'phone' ? 'selected' : '' }}>Teléfono</option>
                            <option value="sms" {{ old('contact_preferences', $customer->contact_preferences) == 'sms' ? 'selected' : '' }}>SMS</option>
                            <option value="whatsapp" {{ old('contact_preferences', $customer->contact_preferences) == 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                        </select>
                        @error('contact_preferences')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1" {{ old('active', $customer->active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Cliente Activo</label>
                        @error('active')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Direcciones -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-map-marker-alt"></i> Direcciones
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                        <i class="fas fa-plus"></i> Nueva Dirección
                    </button>
                </div>
                <div class="card-body">
                    <div id="addressesContainer">
                        @foreach($customer->addresses as $index => $address)
                        <div class="address-card mb-4 p-3 border rounded" data-address-index="{{ $index }}">
                            <div class="d-flex justify-content-between">
                                <h6>Dirección #{{ $index + 1 }}</h6>
                                <button type="button" class="btn btn-sm btn-danger remove-address-btn" {{ $address->is_primary ? 'disabled' : '' }}>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <input type="hidden" name="addresses[{{ $index }}][id]" value="{{ $address->id }}">

                            <div class="row mb-3">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Tipo de Dirección <span class="text-danger">*</span></label>
                                    <select name="addresses[{{ $index }}][address_type]" class="form-select" required>
                                        <option value="main" {{ $address->address_type == 'main' ? 'selected' : '' }}>Principal</option>
                                        <option value="billing" {{ $address->address_type == 'billing' ? 'selected' : '' }}>Facturación</option>
                                        <option value="installation" {{ $address->address_type == 'installation' ? 'selected' : '' }}>Instalación</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input is-primary-check" type="checkbox"
                                            name="addresses[{{ $index }}][is_primary]" value="1"
                                            {{ $address->is_primary ? 'checked' : '' }}
                                            {{ $address->is_primary ? 'disabled' : '' }}>
                                        <input type="hidden" name="addresses[{{ $index }}][is_primary]" value="{{ $address->is_primary ? '1' : '0' }}" class="is-primary-hidden">
                                        <label class="form-check-label">
                                            Dirección Primaria
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Nombre del local <span class="text-muted">(opcional)</span></label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][location_name]" value="{{ old('addresses.'.$index.'.location_name', $address->location_name) }}" placeholder="Ej: Casa principal, Tienda mercado central, Local 2">
                                    <small class="form-text text-muted">Ej: Casa principal, Tienda mercado central, Local 2</small>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Calle/Avenida <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][street]" value="{{ $address->street }}" required>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][number]" value="{{ $address->number }}">
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Piso</label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][floor]" value="{{ $address->floor }}">
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Apartamento</label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][apartment]" value="{{ $address->apartment }}">
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][city]" value="{{ $address->city }}" required>
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Estado/Provincia <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][state]" value="{{ $address->state }}" required>
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Código Postal</label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][postal_code]" value="{{ $address->postal_code }}">
                                </div>

                                <!-- <div class="col-md-3 mb-2">
                                        <label class="form-label">País <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="addresses[{{ $index }}][country]" value="{{ $address->country }}" required>
                                    </div> -->
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Referencia <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][reference]" value="{{ old('addresses.'.$index.'.reference', $address->reference) }}" placeholder="Ej: Frente al colegio, al costado de la posta" required>
                                    <small class="form-text text-muted">Ej: Frente al colegio, al costado de la posta</small>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Coordenadas GPS</label>
                                    <input type="text" class="form-control" name="addresses[{{ $index }}][coordinates]" value="{{ $address->coordinates }}" placeholder="Ej: 19.4326,-99.1332">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    @if($customer->addresses->isEmpty())
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> El cliente debe tener al menos una dirección.
                    </div>
                    @endif

                    <template id="addressTemplate">
                        <div class="address-card mb-4 p-3 border rounded" data-address-index="__INDEX__">
                            <div class="d-flex justify-content-between">
                                <h6>Nueva Dirección</h6>
                                <button type="button" class="btn btn-sm btn-danger remove-address-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Tipo de Dirección <span class="text-danger">*</span></label>
                                    <select name="addresses[__INDEX__][address_type]" class="form-select" required>
                                        <option value="main">Principal</option>
                                        <option value="billing">Facturación</option>
                                        <option value="installation">Instalación</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input is-primary-check" type="checkbox" name="addresses[__INDEX__][is_primary]" value="1">
                                        <input type="hidden" name="addresses[__INDEX__][is_primary]" value="0" class="is-primary-hidden">
                                        <label class="form-check-label">
                                            Dirección Primaria
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Nombre del local <span class="text-muted">(opcional)</span></label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][location_name]" placeholder="Ej: Casa principal, Tienda mercado central, Local 2">
                                    <small class="form-text text-muted">Ej: Casa principal, Tienda mercado central, Local 2</small>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Calle/Avenida <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][street]" required>
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Número</label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][number]">
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Piso</label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][floor]">
                                </div>

                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Apartamento</label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][apartment]">
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Ciudad <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][city]" required>
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Estado/Provincia <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][state]" required>
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">Código Postal</label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][postal_code]">
                                </div>

                                <div class="col-md-3 mb-2">
                                    <label class="form-label">País <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][country]" value="México" required>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Referencia <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][reference]" placeholder="Ej: Frente al colegio, al costado de la posta" required>
                                    <small class="form-text text-muted">Ej: Frente al colegio, al costado de la posta</small>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Coordenadas GPS</label>
                                    <input type="text" class="form-control" name="addresses[__INDEX__][coordinates]" placeholder="Ej: 19.4326,-99.1332">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Contactos de Emergencia -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-phone-alt"></i> Contactos de Emergencia
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEmergencyContactModal">
                        <i class="fas fa-plus"></i> Añadir Contacto
                    </button>
                </div>
                <div class="card-body">
                    <div id="emergencyContactsContainer">
                        @foreach($customer->emergencyContacts as $index => $contact)
                        <div class="emergency-contact-card mb-3 p-3 border rounded" data-contact-index="{{ $index }}">
                            <div class="d-flex justify-content-between">
                                <h6>Contacto #{{ $index + 1 }}</h6>
                                <button type="button" class="btn btn-sm btn-danger remove-contact-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <input type="hidden" name="emergency_contacts[{{ $index }}][id]" value="{{ $contact->id }}">

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="emergency_contacts[{{ $index }}][name]" value="{{ $contact->name }}" required>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Relación <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="emergency_contacts[{{ $index }}][relationship]" value="{{ $contact->relationship }}" required>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="emergency_contacts[{{ $index }}][phone]" value="{{ $contact->phone }}" required>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="emergency_contacts[{{ $index }}][email]" value="{{ $contact->email }}">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <template id="emergencyContactTemplate">
                        <div class="emergency-contact-card mb-3 p-3 border rounded" data-contact-index="__INDEX__">
                            <div class="d-flex justify-content-between">
                                <h6>Nuevo Contacto</h6>
                                <button type="button" class="btn btn-sm btn-danger remove-contact-btn">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="emergency_contacts[__INDEX__][name]" required>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Relación <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="emergency_contacts[__INDEX__][relationship]" required>
                                </div>
                            </div>

                            <div class="row mb-2">
                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="emergency_contacts[__INDEX__][phone]" required>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="emergency_contacts[__INDEX__][email]">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between">
                    <a href="{{ route('customer.customers.show', $customer->id) }}" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal para añadir dirección -->
@can('edit-customers')
<div class="modal fade" id="addAddressModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-focus="false">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Añadir Nueva Dirección</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('customer.addresses.store') }}" method="POST">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <div class="modal-body">
                    <!-- Formulario de dirección -->
                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label for="location_name" class="form-label">Nombre del local <span class="text-muted">(opcional)</span></label>
                            <input type="text" class="form-control" id="location_name" name="location_name" placeholder="Ej: Casa principal, Tienda mercado central, Local 2">
                            <small class="form-text text-muted">Ej: Casa principal, Tienda mercado central, Local 2</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="street" class="form-label">Calle/Avenida <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="street" name="street" required>
                        </div>

                        <div class="col-md-2 mb-3">
                            <label for="number" class="form-label">Número</label>
                            <input type="text" class="form-control" id="number" name="number">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label for="floor" class="form-label">Piso</label>
                            <input type="text" class="form-control" id="floor" name="floor">
                        </div>

                        <div class="col-md-2 mb-3">
                            <label for="apartment" class="form-label">Apartamento</label>
                            <input type="text" class="form-control" id="apartment" name="apartment">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3 mb-3">
                            <label for="city" class="form-label">Ciudad <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="city" name="city" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="state" class="form-label">Estado/Provincia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="state" name="state" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="postal_code" class="form-label">Código Postal</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label for="country" class="form-label">País <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="country" name="country" value="México" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6 mb-3">
                            <label for="reference" class="form-label">Referencia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="reference" name="reference" placeholder="Ej: Frente al colegio, al costado de la posta" required>
                            <small class="form-text text-muted">Ej: Frente al colegio, al costado de la posta</small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="coordinates" class="form-label">Coordenadas GPS</label>
                            <input type="text" class="form-control" id="coordinates" name="coordinates" placeholder="Ej: 19.4326,-99.1332">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="address_type" class="form-label">Tipo de Dirección <span class="text-danger">*</span></label>
                            <select name="address_type" id="address_type" class="form-select" required>
                                <option value="main">Principal</option>
                                <option value="billing">Facturación</option>
                                <option value="installation">Instalación</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_primary" name="is_primary" value="1">
                        <label class="form-check-label" for="is_primary">
                            Establecer como dirección primaria
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Dirección</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<!-- Modal para añadir contacto de emergencia -->
@can('edit-customers')
<div class="modal fade" id="addEmergencyContactModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" data-bs-focus="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Añadir Contacto de Emergencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('customer.emergencyContacts.store') }}" method="POST">
                @csrf
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="relationship" class="form-label">Relación <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="relationship" name="relationship" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Teléfono <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Contacto</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('=== DEBUG: Inicialización de la página de edición ===');

        // Variables para controlar los índices de las direcciones y contactos de emergencia
        let addressIndex = {
            {
                $customer - > addresses - > count()
            }
        };
        let contactIndex = {
            {
                $customer - > emergencyContacts - > count()
            }
        };

        // Verificar si Bootstrap está disponible
        if (typeof bootstrap !== 'undefined') {
            console.log('DEBUG: Bootstrap está disponible en la página');
        } else {
            console.error('ERROR: Bootstrap no está disponible. Los modales no funcionarán correctamente.');
        }

        // Revisar modales
        const modals = [
            '#addAddressModal',
            '#addEmergencyContactModal'
        ];

        function forceModalLayer(modalElement) {
            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                backdrop.remove();
            });

            modalElement.style.background = 'transparent';
            modalElement.style.zIndex = '3000';
            modalElement.style.pointerEvents = 'auto';

            const dialog = modalElement.querySelector('.modal-dialog');
            const content = modalElement.querySelector('.modal-content');

            if (dialog) {
                dialog.style.pointerEvents = 'auto';
            }

            if (content) {
                content.style.pointerEvents = 'auto';
            }

            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                backdrop.remove();
            });
        }

        modals.forEach(modalId => {
            const modalElement = document.querySelector(modalId);
            if (modalElement) {
                if (modalElement.parentElement !== document.body) {
                    document.body.appendChild(modalElement);
                }

                console.log(`DEBUG: Modal ${modalId} encontrado en el DOM`);

                // Verificar botones que abren este modal
                const buttons = document.querySelectorAll(`[data-bs-toggle="modal"][data-bs-target="${modalId}"]`);
                console.log(`DEBUG: Se encontraron ${buttons.length} botones para abrir el modal ${modalId}`);

                // Agregar listener de diagnóstico al modal
                modalElement.addEventListener('show.bs.modal', function() {
                    forceModalLayer(modalElement);
                    console.log(`DEBUG: Evento show.bs.modal disparado para ${modalId}`);
                });

                modalElement.addEventListener('shown.bs.modal', function() {
                    forceModalLayer(modalElement);
                    setTimeout(() => forceModalLayer(modalElement), 50);
                    console.log(`DEBUG: El modal ${modalId} se ha mostrado completamente`);
                });

                buttons.forEach((button, index) => {
                    button.addEventListener('click', function(e) {
                        console.log(`DEBUG: Botón #${index} clickeado para abrir ${modalId}`);
                    });
                });
            } else {
                console.error(`ERROR: Modal ${modalId} no encontrado en el DOM`);
            }
        });

        // Función para añadir nueva dirección
        const addAddressBtn = document.getElementById('addAddressBtn');
        if (addAddressBtn) {
            console.log('DEBUG: Botón addAddressBtn encontrado');
            addAddressBtn.addEventListener('click', function() {
                console.log('DEBUG: Botón addAddressBtn clickeado');
                const template = document.getElementById('addressTemplate').innerHTML;
                const newAddress = template.replaceAll('__INDEX__', addressIndex);

                const addressesContainer = document.getElementById('addressesContainer');

                // Crear un div temporal para almacenar el HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = newAddress;

                // Añadir el nuevo elemento al contenedor
                addressesContainer.appendChild(tempDiv.firstElementChild);

                // Incrementar el índice para la próxima dirección
                addressIndex++;

                // Actualizar los event listeners
                setupRemoveButtons();
                setupPrimaryAddressCheckboxes();

                console.log('DEBUG: Nueva dirección añadida, índice actual:', addressIndex);
            });
        } else {
            console.error('ERROR: No se encontró el botón addAddressBtn');
        }

        // Función para configurar los botones de eliminar
        function setupRemoveButtons() {
            console.log('DEBUG: Configurando botones de eliminación');

            // Botones para eliminar direcciones
            document.querySelectorAll('.remove-address-btn').forEach((button, index) => {
                // Eliminar cualquier event listener previo
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                console.log(`DEBUG: Botón de eliminación de dirección #${index} - Disabled:`, newButton.disabled);

                newButton.addEventListener('click', function(e) {
                    console.log(`DEBUG: Botón de eliminar dirección #${index} clickeado`);

                    if (this.disabled) {
                        console.log('DEBUG: Botón deshabilitado, no se puede eliminar dirección primaria');
                        return;
                    }

                    const addressCard = this.closest('.address-card');
                    if (addressCard) {
                        console.log('DEBUG: Eliminando tarjeta de dirección');
                        addressCard.remove();

                        // Verificar si quedan direcciones
                        const remainingAddresses = document.querySelectorAll('.address-card');
                        console.log('DEBUG: Direcciones restantes:', remainingAddresses.length);

                        if (remainingAddresses.length === 0) {
                            console.log('DEBUG: No quedan direcciones, mostrando alerta');
                            const warningAlert = document.createElement('div');
                            warningAlert.className = 'alert alert-warning';
                            warningAlert.innerHTML = '<i class="fas fa-exclamation-triangle"></i> El cliente debe tener al menos una dirección.';
                            document.getElementById('addressesContainer').appendChild(warningAlert);
                        }
                    } else {
                        console.error('ERROR: No se encontró la tarjeta de dirección');
                    }
                });
            });

            // Botones para eliminar contactos de emergencia
            document.querySelectorAll('.remove-contact-btn').forEach((button, index) => {
                // Eliminar cualquier event listener previo
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                console.log(`DEBUG: Botón de eliminación de contacto #${index} configurado`);

                newButton.addEventListener('click', function(e) {
                    console.log(`DEBUG: Botón de eliminar contacto #${index} clickeado`);
                    const contactCard = this.closest('.emergency-contact-card');
                    if (contactCard) {
                        contactCard.remove();
                        console.log('DEBUG: Contacto eliminado');
                    } else {
                        console.error('ERROR: No se encontró la tarjeta de contacto');
                    }
                });
            });
        }

        // Función para manejar los checkboxes de direcciones primarias
        function setupPrimaryAddressCheckboxes() {
            console.log('DEBUG: Configurando checkboxes de direcciones primarias');

            document.querySelectorAll('.is-primary-check').forEach((checkbox, index) => {
                // Eliminar cualquier event listener previo
                const newCheckbox = checkbox.cloneNode(true);
                checkbox.parentNode.replaceChild(newCheckbox, checkbox);

                console.log(`DEBUG: Checkbox de dirección primaria #${index} - Checked:`, newCheckbox.checked);

                newCheckbox.addEventListener('change', function() {
                    console.log(`DEBUG: Checkbox de dirección primaria #${index} cambiado:`, this.checked);

                    if (this.checked) {
                        // Desmarcar todos los demás checkboxes
                        document.querySelectorAll('.is-primary-check').forEach((otherCheck, otherIndex) => {
                            if (otherCheck !== this) {
                                otherCheck.checked = false;
                                console.log(`DEBUG: Desmarcando checkbox #${otherIndex}`);

                                const hiddenInput = otherCheck.closest('.address-card').querySelector('.is-primary-hidden');
                                if (hiddenInput) {
                                    hiddenInput.value = '0';
                                    console.log(`DEBUG: Valor oculto del checkbox #${otherIndex} establecido a 0`);
                                }
                            }
                        });

                        // Marcar este como primario
                        const hiddenInput = this.closest('.address-card').querySelector('.is-primary-hidden');
                        if (hiddenInput) {
                            hiddenInput.value = '1';
                            console.log(`DEBUG: Valor oculto del checkbox #${index} establecido a 1`);
                        }
                    } else {
                        // Si se desmarca, asegurarse de que el valor oculto sea 0
                        const hiddenInput = this.closest('.address-card').querySelector('.is-primary-hidden');
                        if (hiddenInput) {
                            hiddenInput.value = '0';
                            console.log(`DEBUG: Valor oculto del checkbox #${index} establecido a 0`);
                        }
                    }
                });
            });
        }

        // Revisar formularios
        const forms = document.querySelectorAll('form');
        console.log(`DEBUG: Se encontraron ${forms.length} formularios en la página`);

        forms.forEach((form, index) => {
            console.log(`DEBUG: Formulario #${index} - Action: ${form.action}`);

            form.addEventListener('submit', function(e) {
                console.log(`DEBUG: Formulario #${index} enviado a ${this.action}`);
            });
        });

        // Inicializar los event listeners
        setupRemoveButtons();
        setupPrimaryAddressCheckboxes();

        console.log('=== DEBUG: Fin de la inicialización de la página de edición ===');
    });
</script>
@endpush
