@extends('core::layouts.master')

@section('title', 'Detalle de Lead')
@section('page-title', 'Detalle de Lead')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i class="fas fa-user-tag"></i> Lead: {{ $lead->name }}</div>
        <div>
            @can('edit-leads')
                <a href="{{ route('customer.leads.edit', $lead->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit me-1"></i> Editar</a>
            @endcan
            <a href="{{ route('customer.leads.index') }}" class="btn btn-secondary btn-sm">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4"><strong>Nombre:</strong><br>{{ $lead->name }}</div>
            <div class="col-md-4"><strong>Contacto:</strong><br>{{ $lead->contact ?: '-' }}</div>
            <div class="col-md-4"><strong>Teléfono:</strong><br>{{ $lead->phone ?: '-' }}</div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4"><strong>Correo:</strong><br>{{ $lead->email ?: '-' }}</div>
            <div class="col-md-4"><strong>Fuente:</strong><br>{{ $lead->source ?: '-' }}</div>
            <div class="col-md-4"><strong>Interés:</strong><br>{{ $lead->interest ?: '-' }}</div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4"><strong>Estado:</strong><br><span class="badge bg-secondary">{{ $lead->status }}</span></div>
            <div class="col-md-4"><strong>Fecha de captura:</strong><br>{{ $lead->capture_date ? $lead->capture_date->format('d/m/Y H:i') : '-' }}</div>
            <div class="col-md-4"><strong>Último contacto:</strong><br>{{ $lead->last_contacted_at ? $lead->last_contacted_at->format('d/m/Y H:i') : '-' }}</div>
        </div>
        <div class="row mb-3">
            <div class="col-md-4"><strong>Valor potencial:</strong><br>{{ $lead->potential_value ? '$'.number_format($lead->potential_value, 2) : '-' }}</div>
        </div>
        <div class="row mb-3">
            <div class="col-12"><strong>Mensaje:</strong><br>{{ $lead->message ?: '-' }}</div>
        </div>

        @if($lead->customers->count())
            <hr>
            <h5>Clientes vinculados</h5>
            <ul>
                @foreach($lead->customers as $customer)
                    <li><a href="{{ route('customer.customers.show', $customer->id) }}">{{ $customer->full_name ?? ($customer->first_name . ' ' . $customer->last_name) }}</a></li>
                @endforeach
            </ul>
        @endif

        @can('convert-leads')
            @if($lead->status !== 'converted')
                <hr>
                <a href="{{ route('customer.leads.convert-form', $lead->id) }}" class="btn btn-success"><i class="fas fa-user-plus me-1"></i> Convertir a Cliente</a>
            @endif
        @endcan
    </div>
</div>
@endsection
