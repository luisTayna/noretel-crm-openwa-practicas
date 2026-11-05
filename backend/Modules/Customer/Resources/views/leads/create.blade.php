@extends('core::layouts.master')

@section('title', 'Nuevo Lead')
@section('page-title', 'Nuevo Lead')

@section('content')
<div class="card">
    <div class="card-header">
        <i class="fas fa-user-plus"></i> Crear Lead
    </div>
    <div class="card-body">
        <form action="{{ route('customer.leads.store') }}" method="POST">
            @include('customer::leads._form')
            <div class="d-flex justify-content-end mt-4">
                <a href="{{ route('customer.leads.index') }}" class="btn btn-secondary me-2">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar Lead</button>
            </div>
        </form>
    </div>
</div>
@endsection
