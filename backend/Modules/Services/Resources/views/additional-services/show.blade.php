@extends('services::layouts.master')

@section('title', 'Detalles del Servicio Adicional')
@section('page-title', 'Detalles del Servicio Adicional: ' . $additionalService->name)

@section('actions')
    <div class="btn-group" role="group">
        <a href="{{ route('services.additional-services.index', ['service_id' => $additionalService->service_id]) }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Volver a servicios adicionales
        </a>

        @if(auth()->user()->hasPermission('edit', 'additional-services'))
            <a href="{{ route('services.additional-services.edit', $additionalService->id) }}" class="btn btn-sm btn-primary">
                <i class="fas fa-edit mr-1"></i> Editar
            </a>
        @endif
    </div>
@endsection

@section('module-content')
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow mb-4">
                <div class="service-detail-header">
                    <h5 class="font-weight-bold mb-0">
                        <i class="fas fa-plus-circle mr-2"></i> {{ $additionalService->name }}
                        @if($additionalService->configurable)
                            <span class="badge badge-info float-end">Configurable</span>
                        @endif
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="font-weight-bold">Servicio Principal:</h6>
                        <p>
                            <a href="{{ route('services.services.show', $additionalService->service_id) }}">
                                {{ $service->name }}
                            </a>
                            ({{ $service->service_type_label }} - {{ $service->technology_label }})
                        </p>
                    </div>

                    <div class="mb-4">
                        <h6 class="font-weight-bold">Precio:</h6>
                        <p class="h5">S/ {{ number_format($additionalService->price, 2) }}</p>
                    </div>

                    <div class="mb-4">
                        <h6 class="font-weight-bold">Descripción:</h6>
                        @if($additionalService->description)
                            <p>{{ $additionalService->description }}</p>
                        @else
                            <p class="text-muted"><em>Sin descripción</em></p>
                        @endif
                    </div>

                    @if($additionalService->configurable && !empty($additionalService->configuration_options))
                        <div class="mb-4">
                            <h6 class="font-weight-bold">Opciones de configuración:</h6>
                            <ul class="list-group">
                                @foreach($additionalService->configuration_options as $option)
                                    <li class="list-group-item">{{ $option }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row mb-0">
                        <div class="col-md-6">
                            <p class="mb-1 text-sm text-muted">Creado:</p>
                            <p class="mb-0">{{ $additionalService->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1 text-sm text-muted">Actualizado:</p>
                            <p class="mb-0">{{ $additionalService->updated_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-end">
                    @if(auth()->user()->hasPermission('delete', 'additional-services'))
                            <form action="{{ route('services.additional-services.destroy', $additionalService->id) }}" method="POST" class="ml-2">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('¿Está seguro de eliminar este servicio adicional? Esta acción no se puede deshacer.')">
                                    <i class="fas fa-trash mr-1"></i> Eliminar
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

