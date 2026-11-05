@extends('core::layouts.master')

@section('title', 'Registrar Interacción')
@section('page-title', 'Registrar Nueva Interacción')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="fas fa-comments"></i> Registrar Interacción con Cliente
    </div>
    
    <div class="card-body">
        <form action="{{ route('customer.interactions.store') }}" method="POST">
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
                    <label for="interaction_type" class="form-label">Tipo de Interacción <span class="text-danger">*</span></label>
                    <select name="interaction_type" id="interaction_type" class="form-select @error('interaction_type') is-invalid @enderror" required>
                        <option value="">Seleccione un tipo</option>
                        @foreach($interactionTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('interaction_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('interaction_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6 mb-3">
                    <label for="date" class="form-label">Fecha y Hora <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control @error('date') is-invalid @enderror" id="date" name="date" value="{{ old('date', now()->format('Y-m-d\TH:i')) }}" required>
                    @error('date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="channel" class="form-label">Canal <span class="text-danger">*</span></label>
                    <select name="channel" id="channel" class="form-select @error('channel') is-invalid @enderror" required>
                        <option value="">Seleccione un canal</option>
                        @foreach($channels as $value => $label)
                            <option value="{{ $value }}" {{ old('channel') == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('channel')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-12 mb-3">
                    <label for="description" class="form-label">Descripción <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-12 mb-3">
                    <label for="result" class="form-label">Resultado</label>
                    <textarea class="form-control @error('result') is-invalid @enderror" id="result" name="result" rows="2">{{ old('result') }}</textarea>
                    @error('result')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-12 mb-3">
                    <div class="form-check">
                        <input class="form-check-input @error('follow_up_required') is-invalid @enderror" type="checkbox" id="follow_up_required" name="follow_up_required" value="1" {{ old('follow_up_required') ? 'checked' : '' }}>
                        <label class="form-check-label" for="follow_up_required">
                            Requiere seguimiento posterior
                        </label>
                        @error('follow_up_required')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-end mt-4">
                <a href="{{ route('customer.interactions.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Registrar Interacción</button>
            </div>
        </form>
    </div>
</div>
@endsection