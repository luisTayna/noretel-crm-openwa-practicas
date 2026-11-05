@extends('core::layouts.master')

@section('title', 'Subir Documento')
@section('page-title', 'Subir Nuevo Documento')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="fas fa-file-upload"></i> Formulario de Carga de Documento
    </div>
    
    <div class="card-body">
        <form action="{{ route('customer.documents.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label for="customer_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                    <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required {{ $customer ? 'disabled' : '' }}>
                        <option value="">Seleccione un cliente</option>
                        @foreach($customers as $customerItem)
                            <option value="{{ $customerItem->id }}" {{ (old('customer_id', $customer->id ?? null) == $customerItem->id) ? 'selected' : '' }}>
                                {{ $customerItem->full_name }}
                            </option>
                        @endforeach
                    </select>
                    @if($customer)
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                    @endif
                    @error('customer_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="document_type_id" class="form-label">Tipo de Documento <span class="text-danger">*</span></label>
                    <select name="document_type_id" id="document_type_id" class="form-select @error('document_type_id') is-invalid @enderror" required>
                        <option value="">Seleccione un tipo</option>
                        @foreach($documentTypes as $type)
                            <option value="{{ $type->id }}" {{ old('document_type_id') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('document_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Nombre del Documento <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="classification" class="form-label">Clasificación</label>
                    <input type="text" class="form-control @error('classification') is-invalid @enderror" id="classification" name="classification" value="{{ old('classification') }}">
                    @error('classification')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-12 mb-3">
                    <label for="file" class="form-label">Archivo <span class="text-danger">*</span></label>
                    <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" required>
                    <small class="form-text text-muted">Tamaño máximo: 10MB. Formatos permitidos según el tipo de documento seleccionado.</small>
                    @error('file')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="d-flex justify-content-end mt-4">
                <a href="{{ route('customer.documents.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Subir Documento</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Script para mostrar el formato permitido según el tipo de documento seleccionado
        const documentTypeSelect = document.getElementById('document_type_id');
        const fileInput = document.getElementById('file');
        const fileHelp = fileInput.nextElementSibling;
        
        const documentTypes = {
            @foreach($documentTypes as $type)
                {{ $type->id }}: {
                    name: "{{ $type->name }}",
                    formats: {!! json_encode($type->allowed_format) !!}
                },
            @endforeach
        };
        
        documentTypeSelect.addEventListener('change', function() {
            const typeId = this.value;
            if (typeId && documentTypes[typeId]) {
                const formats = documentTypes[typeId].formats;
                if (formats) {
                    let formatText = '';
                    if (typeof formats === 'string') {
                        try {
                            const parsedFormats = JSON.parse(formats);
                            formatText = Array.isArray(parsedFormats) ? parsedFormats.join(', ') : formats;
                        } catch (e) {
                            formatText = formats;
                        }
                    } else if (Array.isArray(formats)) {
                        formatText = formats.join(', ');
                    }
                    
                    fileHelp.textContent = `Tamaño máximo: 10MB. Formatos permitidos: ${formatText}`;
                } else {
                    fileHelp.textContent = 'Tamaño máximo: 10MB. Formatos permitidos según el tipo de documento seleccionado.';
                }
            } else {
                fileHelp.textContent = 'Tamaño máximo: 10MB. Formatos permitidos según el tipo de documento seleccionado.';
            }
        });
    });
</script>
@endpush