@extends('core::layouts.master')

@section('title', 'Reparación de pagos adelantados')
@section('page-title', 'Reparación de pagos adelantados')

@section('content')
    @include('billing::period-repair.content')
@endsection
