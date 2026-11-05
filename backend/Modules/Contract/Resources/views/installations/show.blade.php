@extends('core::layouts.master')

@section('title', 'Instalación')
@section('page-title', 'Detalle de Instalación')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-tools"></i> Instalación #{{ $installation->id }}</h5>
        <div class="d-flex gap-2">
            @php($flowCustomerId = optional($installation->contract)->customer_id ?: optional($installation->onboardingOrder)->customer_id)
            @if($flowCustomerId)
                <a href="{{ route('operations.customers.flow', $flowCustomerId) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-stream"></i> Flujo operativo
                </a>
            @endif
            @if(in_array($installation->status, ['scheduled', 'in_progress']))
                <a href="{{ route('contract.installations.complete-form', $installation->id) }}" class="btn btn-sm btn-success">
                    <i class="fas fa-check"></i> Completar
                </a>
            @endif
        </div>
    </div>

    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tr>
                        <th>Cliente</th>
                        <td>{{ optional(optional($installation->contract)->customer)->full_name ?? optional(optional($installation->onboardingOrder)->customer)->full_name ?? 'No registrado' }}</td>
                    </tr>
                    <tr>
                        <th>Orden / Contrato</th>
                        <td>
                            @if($installation->onboardingOrder)
                                {{ $installation->onboardingOrder->order_number }} · {{ $installation->onboardingOrder->status_label }}
                            @elseif($installation->contract)
                                Contrato #{{ $installation->contract->id }}
                            @else
                                No registrado
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Técnico</th>
                        <td>{{ optional($installation->technician)->full_name ?? 'No asignado' }}</td>
                    </tr>
                    <tr>
                        <th>Ruta</th>
                        <td>{{ $installation->route ? $installation->route->zone . ' · ' . $installation->route->date->format('d/m/Y') : 'Sin ruta' }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-bordered">
                    <tr>
                        <th>Estado</th>
                        <td><span class="badge bg-{{ $installation->status_color }}">{{ ucfirst(str_replace('_', ' ', $installation->status)) }}</span></td>
                    </tr>
                    <tr>
                        <th>Programada</th>
                        <td>{{ optional($installation->scheduled_date)->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Completada</th>
                        <td>{{ $installation->completed_date ? $installation->completed_date->format('d/m/Y H:i') : 'Pendiente' }}</td>
                    </tr>
                    <tr>
                        <th>Notas</th>
                        <td>{{ $installation->notes ?: 'Sin notas' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
