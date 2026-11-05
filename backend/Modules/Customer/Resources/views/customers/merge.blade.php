@extends('core::layouts.master')

@section('title', 'Consolidar clientes duplicados')
@section('page-title', 'Consolidar clientes duplicados')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">Consolidar servicios en un solo cliente</h1>
        <p class="text-muted mb-0">Primero revise la vista previa. Ningún cambio se realiza hasta escribir CONSOLIDAR.</p>
    </div>
    <a href="{{ route('customer.customers.show', $target->id) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Volver
    </a>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-bold">Cliente principal que se conservará</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4"><span class="text-muted">Cliente</span><div class="fw-bold">#{{ $target->id }} · {{ $target->full_name }}</div></div>
            <div class="col-md-3"><span class="text-muted">DNI/RUC</span><div class="fw-bold">{{ $target->identity_document ?: 'Sin documento' }}</div></div>
            <div class="col-md-3"><span class="text-muted">Celular</span><div class="fw-bold">{{ $target->phone ?: 'Sin celular' }}</div></div>
            <div class="col-md-2"><span class="text-muted">Contratos</span><div class="fw-bold">{{ $target->contracts_count }}</div></div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-bold">Posibles registros duplicados</div>
    <div class="card-body">
        <form method="POST" action="{{ route('customer.customers.merge.preview', $target->id) }}">
            @csrf
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th></th><th>Cliente</th><th>DNI/RUC</th><th>Celular</th><th>Contratos</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($candidates as $candidate)
                            <tr>
                                <td><input class="form-check-input" type="checkbox" name="source_ids[]" value="{{ $candidate->id }}" @checked(in_array($candidate->id, old('source_ids', isset($preview) ? $preview['sourceIds']->all() : [])))></td>
                                <td><a href="{{ route('customer.customers.show', $candidate->id) }}">#{{ $candidate->id }} · {{ $candidate->full_name }}</a></td>
                                <td>{{ $candidate->identity_document ?: 'Sin documento' }}</td>
                                <td>{{ $candidate->phone ?: 'Sin celular' }}</td>
                                <td>{{ $candidate->contracts_count }}</td>
                                <td><span class="badge bg-{{ $candidate->active ? 'success' : 'secondary' }}">{{ $candidate->active ? 'Activo' : 'Inactivo' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron candidatos por DNI/RUC, celular o nombre exacto.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @error('source_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <button class="btn btn-primary" type="submit" @disabled($candidates->isEmpty())>
                <i class="fas fa-search"></i> Generar vista previa segura
            </button>
        </form>
    </div>
</div>

@isset($preview)
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-bold">Vista previa de la consolidación</div>
    <div class="card-body">
        @foreach($preview['blockers'] as $blocker)
            <div class="alert alert-danger mb-2"><i class="fas fa-ban me-1"></i>{{ $blocker }}</div>
        @endforeach
        @foreach($preview['warnings'] as $warning)
            <div class="alert alert-warning mb-2"><i class="fas fa-triangle-exclamation me-1"></i>{{ $warning }}</div>
        @endforeach

        <h6 class="fw-bold mt-4">Registros que pasarán al cliente #{{ $target->id }}</h6>
        <div class="row g-3 mb-4">
            @foreach($preview['counts'] as $table => $count)
                @if($count > 0)
                    <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">{{ str_replace('_', ' ', $table) }}</div><div class="h4 mb-0">{{ $count }}</div></div></div>
                @endif
            @endforeach
        </div>

        @if($preview['can_merge'])
            <form method="POST" action="{{ route('customer.customers.merge.store', $target->id) }}" onsubmit="return confirm('¿Confirma la consolidación? Esta operación reasignará todo el historial y eliminará únicamente las fichas duplicadas.');">
                @csrf
                @foreach($preview['sourceIds'] as $sourceId)
                    <input type="hidden" name="source_ids[]" value="{{ $sourceId }}">
                @endforeach
                <label for="confirmation" class="form-label fw-bold">Escriba CONSOLIDAR para confirmar</label>
                <input id="confirmation" name="confirmation" class="form-control mb-3" autocomplete="off" required>
                @error('confirmation')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <button class="btn btn-danger" type="submit"><i class="fas fa-object-group"></i> Consolidar clientes</button>
            </form>
        @else
            <p class="text-danger fw-bold mb-0">La operación está bloqueada hasta resolver los problemas indicados.</p>
        @endif
    </div>
</div>
@endisset
@endsection
