@extends('core::layouts.master')

@section('title', 'Detalle de Auditoría')

@section('page-title', 'Detalle de Registro de Auditoría')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Registro de Auditoría #{{ $log->id }}</h5>
        <div>
            <a href="{{ route('core.audit.index') }}" class="btn btn-secondary">
                <i class="fa fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>ID:</strong> {{ $log->id }}
                </div>
                <div class="mb-3">
                    <strong>Usuario:</strong> {{ $log->user ? $log->user->username : 'N/A' }}
                </div>
                <div class="mb-3">
                    <strong>Fecha:</strong> {{ $log->action_date->format('d/m/Y H:i:s') }}
                </div>
                <div class="mb-3">
                    <strong>Módulo:</strong> {{ ucfirst($log->module) }}
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <strong>Tipo de Acción:</strong> {{ ucfirst(str_replace('_', ' ', $log->action_type)) }}
                </div>
                <div class="mb-3">
                    <strong>Dirección IP:</strong> {{ $log->source_ip }}
                </div>
                <div class="mb-3">
                    <strong>Fecha de Registro:</strong> {{ $log->created_at->format('d/m/Y H:i:s') }}
                </div>
            </div>
        </div>

        <div class="mb-4">
            <h6>Detalle de la Acción</h6>
            <div class="p-3 bg-light rounded">
                {{ $log->action_detail }}
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Datos Anteriores</h6>
                    </div>
                    <div class="card-body">
                        @if(empty($log->previous_data))
                            <div class="alert alert-info mb-0">
                                No hay datos anteriores disponibles.
                            </div>
                        @else
                            <pre class="json-data">{{ json_encode($log->previous_data, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Datos Nuevos</h6>
                    </div>
                    <div class="card-body">
                        @if(empty($log->new_data))
                            <div class="alert alert-info mb-0">
                                No hay datos nuevos disponibles.
                            </div>
                        @else
                            <pre class="json-data">{{ json_encode($log->new_data, JSON_PRETTY_PRINT) }}</pre>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .json-data {
        background-color: #f8f9fa;
        padding: 12px;
        border-radius: 5px;
        max-height: 400px;
        overflow: auto;
    }
</style>
@endpush
