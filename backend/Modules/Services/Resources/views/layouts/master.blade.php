@extends('core::layouts.master')

@section('title', isset($title) ? $title . ' - Servicios' : 'Módulo de Servicios')

@section('content-header')
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">@yield('page-title', 'Módulo de Servicios')</h1>
        <div>
            @yield('actions')
        </div>
    </div>
@endsection

@push('styles')
    <!-- Estilos específicos del módulo de servicios -->
    <style>
        /* Estilos para tarjetas de servicio */
        .service-card {
            transition: all 0.3s ease;
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .service-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            padding: 0.75rem 1.25rem;
        }

        .service-icon {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            background-color: var(--primary-color);
            color: white;
            border-radius: 5px;
            margin-right: 10px;
        }

        .badge-active {
            background-color: var(--success-color);
            color: white;
        }

        .badge-inactive {
            background-color: var(--danger-color);
            color: white;
        }

        /* Estilos para listado de planes */
        .plan-item {
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .plan-item:hover {
            transform: translateX(5px);
        }

        .plan-item.inactive {
            border-left-color: var(--danger-color);
            opacity: 0.7;
        }

        /* Estilos para promociones */
        .promotion-badge {
            position: absolute;
            top: -10px;
            right: 10px;
            z-index: 10;
        }

        .promotion-label {
            background: var(--accent-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        /* Detalles del servicio */
        .service-detail-card {
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .service-detail-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
        }

        .service-detail-body {
            padding: 1.5rem;
        }

        .feature-list {
            list-style: none;
            padding-left: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li i {
            color: var(--success-color);
            margin-right: 0.5rem;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid">
        @hasSection('actions')
            <div class="d-flex justify-content-end align-items-center mb-4">
                @yield('actions')
            </div>
        @endif

        @yield('module-content')
    </div>
@endsection
