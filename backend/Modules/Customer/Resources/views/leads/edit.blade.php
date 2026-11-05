@extends('core::layouts.master')

@section('title', 'Editar Lead')
@section('page-title', 'Editar Lead')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="fas fa-edit"></i> Editar Lead: {{ $lead->name }}
    </div>
    <div class="card-body">
        <form action="{{ route('customer.leads.update', $lead->id) }}" method="POST">
            @method('PUT')
            @include('customer::leads._form')
            <div class="d-flex justify-content-end mt-4">
                <a href="{{ route('customer.leads.show', $lead->id) }}" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Actualizar Lead</button>
            </div>
        </form>
    </div>
</div>
@endsection
