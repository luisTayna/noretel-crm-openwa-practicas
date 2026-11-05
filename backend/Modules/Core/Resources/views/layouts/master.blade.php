<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - NorETEL CRM</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- Estilos personalizados -->
    <link href="{{ asset('modules/core/css/style.css') }}" rel="stylesheet">
    <!-- Estilos del módulo Customer cuando sea necesario -->
    @if(request()->segment(1) == 'customer')
        <link href="{{ asset('modules/customer/css/style.css') }}" rel="stylesheet">
    @endif

    <style>
        :root {
            /* Palette Noretel Next-Gen (Referencia Imagen 2) */
            --primary-color: #00cc66;
            /* Verde Teal vibrante */
            --primary-hover: #00b359;
            --secondary-color: #0099ff;
            /* Azul brillante */
            --accent-color: #8b5cf6;
            /* Morado */
            --dark-bg: #0f172a;

            /* Sidebar Gradient: Azul profundo a Teal esmeralda */
            --sidebar-bg: linear-gradient(180deg, #0b3b60 0%, #0099ff 50%, #00cc66 100%);
            --sidebar-text: #ffffff;

            --text-color: #1e293b;
            --text-muted: #64748b;
            --border-color: rgba(255, 255, 255, 0.4);

            /* Mesh Gradient Background */
            --glass-bg: rgba(255, 255, 255, 0.6);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0fdfa;
            /* Base muy clara menta */
            background-image:
                radial-gradient(at 0% 0%, rgba(224, 242, 254, 0.8) 0px, transparent 50%),
                /* Celeste top-left */
                radial-gradient(at 100% 0%, rgba(243, 232, 255, 0.8) 0px, transparent 50%),
                /* Morado suave top-right */
                radial-gradient(at 100% 100%, rgba(209, 250, 229, 0.8) 0px, transparent 50%),
                /* Menta bottom-right */
                radial-gradient(at 0% 100%, rgba(224, 242, 254, 0.8) 0px, transparent 50%);
            /* Celeste bottom-left */
            background-attachment: fixed;
            color: var(--text-color);
            min-height: 100vh;
        }

        /* Wrapper for floating layout (Solo el hijo directo del body) */
        body>.d-flex {
            padding: 1rem;
            gap: 1.5rem;
            min-height: 100vh;
            box-sizing: border-box;
        }

        /* Sidebar Next-Gen */
        #sidebar {
            background: rgba(255, 255, 255, 0.85);
            /* Fondo blanco */
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
            /* Sombra gris suave en lugar de azul */
            z-index: 100;
            transition: all 0.3s ease;
            width: 260px;
            min-width: 260px;
            height: calc(100vh - 2rem);
            position: sticky;
            top: 1rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        #sidebar::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        #sidebar .sidebar-brand {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        #sidebar .sidebar-brand-text {
            color: var(--text-color);
            /* Texto oscuro */
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
        }

        #sidebar .logo-initial {
            background: var(--primary-color);
            /* Fondo de logo verde o azul */
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            margin-right: 12px;
            box-shadow: 0 4px 10px rgba(0, 204, 102, 0.3);
        }

        #sidebar .nav-item {
            margin: 4px 16px;
        }

        #sidebar .nav-link {
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            /* Texto de enlaces en gris */
            border-radius: 12px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
            font-size: 0.95rem;
        }

        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            color: var(--primary-color);
            background-color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            transform: translateY(-1px);
        }

        #sidebar .nav-link.active i,
        #sidebar .nav-link:hover i {
            color: var(--primary-color);
        }

        #sidebar .sidebar-heading {
            color: var(--text-muted);
            font-size: 0.7rem;
            padding: 1rem 1.5rem 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.7;
        }

        #sidebar .sidebar-divider {
            border-top: 1px solid var(--border-color);
            margin: 1rem 1.5rem;
        }

        #sidebar .nav-item .nav-link i {
            width: 1.5rem;
            margin-right: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.2s;
        }

        #sidebar .sidebar-footer {
            padding: 1rem;
            font-size: 0.8rem;
            text-align: center;
            color: var(--text-muted);
            margin-top: auto;
        }

        /* Module groups */
        .sidebar-module-group {
            margin-bottom: 0.5rem;
        }

        .sidebar-module-title {
            display: flex;
            align-items: center;
            color: var(--text-muted);
            font-size: 0.7rem;
            margin: 1rem 1.5rem 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .sidebar-module-title i {
            margin-right: 0.5rem;
            font-size: 0.8rem;
        }

        /* Main content area */
        .content-wrapper {
            flex: 1;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* Navbar styles (Transparent) */
        .topbar {
            height: 70px;
            background: transparent !important;
            z-index: 99;
            border-bottom: none;
            margin-bottom: 1rem;
        }

        .topbar .navbar-nav .nav-item {
            position: relative;
            margin: 0 5px;
        }

        .topbar .navbar-nav .nav-item:not(.user-nav-item) .nav-link {
            color: var(--text-muted);
            padding: 0.5rem;
            border-radius: 50%;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        }

        .topbar .navbar-nav .nav-item:not(.user-nav-item) .nav-link:hover {
            color: var(--secondary-color);
            transform: translateY(-2px);
            background: white;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
        }
        
        .topbar .navbar-nav .user-nav-item .nav-link {
            padding: 0;
            display: flex;
            align-items: center;
        }

        .topbar .dropdown-menu {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: none;
            border-radius: 16px;
            overflow: hidden;
            background: white;
            padding: 0;
        }

        .topbar-divider {
            display: none;
        }

        /* Page Title */
        h1.h4 {
            font-weight: 700;
            color: var(--dark-bg);
            font-size: 1.6rem;
            letter-spacing: -0.5px;
        }

        /* Base Card styles - Glassmorphism Soft */
        .card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
            border-radius: 20px;
            margin-bottom: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        /* Glassmorphism Card Utility */
        .glass-card {
            background: rgba(255, 255, 255, 0.85) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: 1px solid rgba(255, 255, 255, 0.8) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03) !important;
            border-radius: 20px !important;
            transition: transform 0.2s, box-shadow 0.2s !important;
        }
        .glass-card:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 32px rgba(112, 144, 176, 0.12) !important;
        }

        /* Glassmorphic Table */
        .table-glass {
            margin-bottom: 0;
            color: var(--text-color);
        }
        .table-glass th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 2px solid rgba(0, 0, 0, 0.03);
            padding: 1.1rem 1rem;
            background: transparent;
        }
        .table-glass td {
            vertical-align: middle;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            padding: 1.1rem 1rem;
            background: transparent;
            color: var(--text-color);
        }
        .table-glass tbody tr:hover td {
            background-color: rgba(255, 255, 255, 0.4) !important;
        }

        /* Glassmorphic Forms */
        .form-control-glass,
        .form-select-glass {
            background-color: rgba(255, 255, 255, 0.6) !important;
            backdrop-filter: blur(5px) !important;
            -webkit-backdrop-filter: blur(5px) !important;
            border: 1px solid rgba(255, 255, 255, 0.8) !important;
            color: var(--text-color) !important;
            border-radius: 12px !important;
            padding: 0.6rem 1rem !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.01) !important;
            transition: all 0.2s ease !important;
        }
        .form-control-glass:focus,
        .form-select-glass:focus {
            background-color: white !important;
            border-color: var(--secondary-color) !important;
            box-shadow: 0 0 0 0.25rem rgba(0, 153, 255, 0.15) !important;
        }

        /* Glass Buttons and Badges */
        .btn-glass-action {
            background-color: rgba(255, 255, 255, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.8) !important;
            color: var(--text-muted) !important;
            border-radius: 12px !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
        }
        .btn-glass-action:hover {
            background-color: white !important;
            color: var(--text-color) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03) !important;
        }

        .badge-pill {
            border-radius: 9999px !important;
            padding: 0.35em 0.85em !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
        }

        .badge-plan {
            background-color: rgba(0, 153, 255, 0.1) !important;
            color: var(--secondary-color) !important;
            border: 1px solid rgba(0, 153, 255, 0.2) !important;
        }

        .badge-status-paid {
            background-color: rgba(16, 185, 129, 0.1) !important;
            color: #10b981 !important;
            border: 1px solid rgba(16, 185, 129, 0.2) !important;
        }

        .badge-status-pending {
            background-color: rgba(245, 158, 11, 0.1) !important;
            color: #d97706 !important;
            border: 1px solid rgba(245, 158, 11, 0.2) !important;
        }

        .badge-status-overdue {
            background-color: rgba(239, 68, 68, 0.1) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.2) !important;
        }



        /* Estilos KPI Next-Gen Reutilizables */
        .metric-card {
            border-radius: 20px !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.03) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease !important;
            position: relative;
            overflow: hidden;
        }
        .metric-card:hover {
            transform: translateY(-5px) !important;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06) !important;
        }
        .icon-box {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important; color: white !important; }
        .bg-gradient-primary { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important; color: white !important; }
        .bg-gradient-danger  { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%) !important; color: white !important; }
        .bg-gradient-purple  { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%) !important; color: white !important; }
        .bg-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important; color: white !important; }
        .bg-gradient-info    { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%) !important; color: white !important; }

        /* Variaciones Pastel para Tarjetas KPI */
        .card-mrr {
            background: linear-gradient(135deg, rgba(236, 253, 245, 0.9) 0%, rgba(209, 250, 229, 0.9) 100%) !important;
            border: 1px solid rgba(52, 211, 153, 0.3) !important;
        }
        .card-receivables {
            background: linear-gradient(135deg, rgba(239, 246, 255, 0.9) 0%, rgba(219, 234, 254, 0.9) 100%) !important;
            border: 1px solid rgba(96, 165, 250, 0.3) !important;
        }
        .card-morosidad {
            background: linear-gradient(135deg, rgba(254, 242, 242, 0.9) 0%, rgba(254, 226, 226, 0.9) 100%) !important;
            border: 1px solid rgba(248, 113, 113, 0.3) !important;
        }
        .card-customers {
            background: linear-gradient(135deg, rgba(250, 245, 255, 0.9) 0%, rgba(243, 232, 255, 0.9) 100%) !important;
            border: 1px solid rgba(192, 132, 252, 0.3) !important;
        }
        .card-warning-light {
            background: linear-gradient(135deg, rgba(255, 251, 235, 0.9) 0%, rgba(254, 243, 199, 0.9) 100%) !important;
            border: 1px solid rgba(245, 158, 11, 0.3) !important;
        }
        .card-info-light {
            background: linear-gradient(135deg, rgba(240, 249, 255, 0.9) 0%, rgba(224, 242, 254, 0.9) 100%) !important;
            border: 1px solid rgba(14, 165, 233, 0.3) !important;
        }

        .metric-title {
            color: #475569 !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
        }
        .metric-value {
            color: #0f172a !important;
            font-size: 1.8rem !important;
            letter-spacing: -0.5px !important;
        }
        .small-trend {
            font-size: 0.8rem !important;
            font-weight: 600 !important;
            margin-top: 0.5rem !important;
            display: flex;
            align-items: center;
        }

        .card-header {
            background: transparent !important;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(112, 144, 176, 0.12);
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            padding: 1.2rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--text-color);
        }

        .card-header i {
            margin-right: 0.5rem;
            color: var(--primary-color);
            background: var(--primary-soft);
            padding: 8px;
            border-radius: 8px;
        }

        /* Action buttons */
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: white;
            color: var(--text-muted);
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        }

        .action-btn:hover {
            background-color: var(--primary-soft);
            color: var(--primary-color);
            border-color: var(--primary-soft);
        }

        .action-btn.primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 153, 255, 0.3);
        }

        .action-btn.primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .action-btn-group {
            display: flex;
            gap: 0.5rem;
        }

        /* Notifications */
        .notification-counter {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            text-align: center;
            line-height: 18px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.4);
        }

        .notification-item {
            display: flex;
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            transition: background-color 0.2s;
        }

        .notification-item:hover {
            background-color: var(--light-color);
        }

        .notification-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .notification-icon.primary {
            background-color: var(--primary-soft);
            color: var(--primary-color);
        }

        .notification-content {
            flex: 1;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        /* Topbar specific dropdown changes */
        .topbar .dropdown-toggle::after {
            display: none !important;
        }

        /* User dropdown as a premium glass pill */
        .user-dropdown {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 6px 16px 6px 6px;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.5) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            transition: all 0.2s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        }

        .user-dropdown:hover {
            background: white !important;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
        }

        .user-dropdown .img-profile {
            width: 30px;
            height: 30px;
            object-fit: cover;
            border-radius: 50%;
            border: 1.5px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 2px 6px rgba(0, 153, 255, 0.15);
        }

        .user-dropdown .user-name {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0 8px 0 10px;
            color: var(--text-color);
        }


        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        /* Responsive */
        @media (max-width: 767.98px) {
            #sidebar {
                margin-left: -260px;
                position: fixed;
                height: 100%;
            }

            #sidebar.active {
                margin-left: 0;
            }

            .content-wrapper {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>

    @stack('styles')
</head>

<body>
    <div class="d-flex">
        @auth
            <!-- Sidebar izquierdo cuando está autenticado -->
            <nav id="sidebar">
                <div class="sidebar-brand">
                    <div class="d-flex align-items-center">
                        <div class="logo-initial">N</div>
                        <div class="sidebar-brand-text">NorETEL CRM</div>
                    </div>
                    <button type="button" id="sidebarCollapseBtn" class="btn btn-sm btn-dark d-md-none">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                @if(auth()->user()->hasRole('admin'))
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('core.dashboard') ? 'active' : '' }}"
                                href="{{ route('core.dashboard') }}">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                    </ul>
                    <div class="sidebar-divider"></div>
                @endif

                <!-- Módulo Operaciones -->
                @if(auth()->user()->hasRole('admin') || auth()->user()->canViewModule('operations'))
                    <div class="sidebar-module-group">
                        <div class="sidebar-module-title">
                            <i class="fas fa-route"></i> Operaciones
                        </div>
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('operations.*') ? 'active' : '' }}"
                                    href="{{ route('operations.onboarding') }}">
                                    <i class="fas fa-stream"></i> Órdenes de instalación
                                </a>
                            </li>
                        </ul>
                    </div>
                @endif

                <div class="sidebar-divider"></div>

                <!-- Módulo Clientes -->
                <div class="sidebar-module-group">
                    <div class="sidebar-module-title">
                        <i class="fas fa-users"></i> Clientes
                    </div>
                    <ul class="nav flex-column">
                        @if(auth()->user()->canViewModule('customers'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}"
                                    href="{{ route('customer.dashboard') }}">
                                    <i class="fas fa-chart-line"></i> Dashboard Clientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('customer.bi-classification') ? 'active' : '' }}"
                                    href="{{ route('customer.bi-classification') }}">
                                    <i class="fas fa-brain text-warning"></i> Inteligencia BI & Clasificación
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('customer.customers.*') ? 'active' : '' }}"
                                    href="{{ route('customer.customers.index') }}">
                                    <i class="fas fa-user-friends"></i> Gestión de Clientes
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('customer.documents.*') ? 'active' : '' }}"
                                    href="{{ route('customer.documents.index') }}">
                                    <i class="fas fa-file-alt"></i> Documentos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('customer.interactions.*') ? 'active' : '' }}"
                                    href="{{ route('customer.interactions.index') }}">
                                    <i class="fas fa-comments"></i> Interacciones
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('customer.leads.*') ? 'active' : '' }}"
                                    href="{{ route('customer.leads.index') }}">
                                    <i class="fas fa-user-tag"></i> Leads
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>
                <!-- Módulo Sistema -->
                <div class="sidebar-module-group">
                    <div class="sidebar-module-title">
                        <i class="fas fa-cogs"></i> Sistema
                    </div>
                    <ul class="nav flex-column">
                        @if(auth()->user()->canViewModule('users'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('core.users.*') ? 'active' : '' }}"
                                    href="{{ route('core.users.index') }}">
                                    <i class="fas fa-users"></i> Usuarios
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasRole('admin') || auth()->user()->canViewModule('roles'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('core.roles.*') ? 'active' : '' }}"
                                   href="{{ route('core.roles.index') }}">
                                   <i class="fas fa-user-shield"></i> Roles y Permisos
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasRole('admin') || auth()->user()->canViewModule('configuration'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('core.config.*') ? 'active' : '' }}"
                                   href="{{ route('core.config.index') }}">
                                   <i class="fas fa-cog"></i> Configuraciones
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasRole('admin') || auth()->user()->canViewModule('security'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('core.security.*') ? 'active' : '' }}"
                                   href="{{ route('core.security.index') }}">
                                   <i class="fas fa-shield-alt"></i> Seguridad
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->hasRole('admin') || auth()->user()->canViewModule('audit'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('core.audit.*') ? 'active' : '' }}"
                                   href="{{ route('core.audit.index') }}">
                                   <i class="fas fa-clipboard-list"></i> Auditoría
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

                <!-- Módulo Servicios -->
                <div class="sidebar-module-group">
                    <div class="sidebar-module-title">
                        <i class="fas fa-plug"></i> Servicios
                    </div>
                    <ul class="nav flex-column">
                        @if(auth()->user()->canViewModule('services'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('services.services.*') ? 'active' : '' }}"
                                    href="{{ route('services.services.index') }}">
                                    <i class="fas fa-cogs"></i> Gestión de Servicios
                                </a>
                            </li>
                        @endif
                        @if (auth()->user()->canViewModule('plans'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('services.plans.*') ? 'active' : '' }}"
                                    href="{{ route('services.plans.index') }}">
                                    <i class="fas fa-network-wired"></i> Planes
                                </a>
                            </li>
                        @endif
                        @if (auth()->user()->canViewModule('promotions'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('services.promotions.*') ? 'active' : '' }}"
                                    href="{{ route('services.promotions.index') }}">
                                    <i class="fas fa-tags"></i> Promociones
                                </a>
                            </li>
                        @endif
                        @if (auth()->user()->canViewModule('additional-services'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('services.additional-services.*') ? 'active' : '' }}"
                                    href="{{ route('services.additional-services.index') }}">
                                    <i class="fas fa-plus-circle"></i> Servicios Adicionales
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

                <!-- Módulo Contratos -->
                <div class="sidebar-module-group">
                    <div class="sidebar-module-title">
                        <i class="fas fa-file-signature"></i> Contratos
                    </div>
                    <ul class="nav flex-column">
                        @if(auth()->user()->canViewModule('contracts'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('contract.dashboard') ? 'active' : '' }}"
                                    href="{{ route('contract.dashboard') }}">
                                    <i class="fas fa-chart-line"></i> Dashboard Contratos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('contract.contracts.*') ? 'active' : '' }}"
                                    href="{{ route('contract.contracts.index') }}">
                                    <i class="fas fa-file-contract"></i> Gestión de Contratos
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('installations'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('contract.installations.*') ? 'active' : '' }}"
                                    href="{{ route('contract.installations.index') }}">
                                    <i class="fas fa-tools"></i> Instalaciones
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('routes'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('contract.routes.*') ? 'active' : '' }}"
                                    href="{{ route('contract.routes.index') }}">
                                    <i class="fas fa-map-marked-alt"></i> Rutas
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('slas'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('contract.slas.*') ? 'active' : '' }}"
                                    href="{{ route('contract.slas.index') }}">
                                    <i class="fas fa-handshake"></i> SLAs
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

                <!-- Módulo Infraestructura de Red -->
                <div class="sidebar-module-group">
                    <div class="sidebar-module-title">
                        <i class="fas fa-server"></i> Infraestructura
                    </div>
                    <ul class="nav flex-column">
                        @if(auth()->user()->canViewModule('network'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('network.dashboard') ? 'active' : '' }}"
                                    href="{{ route('network.dashboard') }}">
                                    <i class="fas fa-chart-network"></i> Dashboard Red
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('routers'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('network.routers.*') ? 'active' : '' }}"
                                    href="{{ route('network.routers.index') }}">
                                    <i class="fas fa-router"></i> Routers
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('nodes'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('network.nodes.*') ? 'active' : '' }}"
                                    href="{{ route('network.nodes.index') }}">
                                    <i class="fas fa-network-wired"></i> Nodos
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('network_automation'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('network.automation.*') ? 'active' : '' }}"
                                    href="{{ route('network.automation.index') }}">
                                    <i class="fas fa-magic"></i> Automatizaciones
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('service_requests'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('network.service-requests.*') ? 'active' : '' }}"
                                    href="{{ route('network.service-requests.index') }}">
                                    <i class="fas fa-ticket-alt"></i> Solicitudes de Servicio
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

                <!-- Módulo Facturación y Cobranza -->
                <div class="sidebar-module-group">
                    <div class="sidebar-module-title">
                        <i class="fas fa-file-invoice-dollar"></i> Facturación
                    </div>
                    <ul class="nav flex-column">
                        @if(auth()->user()->canViewModule('invoices') || auth()->user()->canViewModule('payments'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.dashboard') ? 'active' : '' }}"
                                    href="{{ route('billing.dashboard') }}">
                                    <i class="fas fa-chart-line"></i> Dashboard Facturación
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('invoices'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.invoices.*') && !request()->routeIs('billing.collections') ? 'active' : '' }}"
                                    href="{{ route('billing.invoices.index') }}">
                                    <i class="fas fa-file-invoice"></i> Facturas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.collections') ? 'active' : '' }}"
                                    href="{{ route('billing.collections') }}">
                                    <i class="fas fa-users text-warning"></i> Cobranzas (WhatsApp)
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.notebook') ? 'active' : '' }}"
                                    href="{{ route('billing.notebook') }}">
                                    <i class="fas fa-book text-info"></i> Cuaderno de Pagos
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('payments'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.payments.*') ? 'active' : '' }}"
                                    href="{{ route('billing.payments.index') }}">
                                    <i class="fas fa-money-bill-wave"></i> Pagos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.yape.review-*') ? 'active' : '' }}"
                                    href="{{ route('billing.yape.review-queue') }}">
                                    <i class="fas fa-user-clock"></i> Pagos Yape por revisar
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.yape.*') && !request()->routeIs('billing.yape.review-*') && !request()->routeIs('billing.yape.automation-report') ? 'active' : '' }}"
                                    href="{{ route('billing.yape.index') }}">
                                    <i class="fas fa-mobile-alt"></i> Historial Yape
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.yape.automation-report') ? 'active' : '' }}"
                                    href="{{ route('billing.yape.automation-report') }}">
                                    <i class="fas fa-chart-line"></i> Efectividad Yape
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('credit_notes'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.credit-notes.*') ? 'active' : '' }}"
                                    href="{{ route('billing.credit-notes.index') }}">
                                    <i class="fas fa-receipt"></i> Notas de Crédito
                                </a>
                            </li>
                        @endif

                        @if(auth()->user()->canViewModule('financial_reports'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('billing.reports') ? 'active' : '' }}"
                                    href="{{ route('billing.reports') }}">
                                    <i class="fas fa-chart-bar"></i> Reportes Financieros
                                </a>
                            </li>
                        @endif
                    </ul>
                </div>

                <!-- Módulo Workflow -->
                <div class="sidebar-module-group">
                    <div class="sidebar-module-title">
                        <i class="fas fa-project-diagram"></i> Procesos
                    </div>
                    <ul class="nav flex-column">
                        @if(auth()->user()->canViewModule('workflows'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('core.workflows.*') ? 'active' : '' }}"
                                    href="{{ route('core.workflows.index') }}">
                                    <i class="fas fa-sitemap"></i> Workflows
                                </a>
                            </li>
                        @endif

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('core.notifications.*') ? 'active' : '' }}"
                                href="{{ route('core.notifications.index') }}">
                                <i class="fas fa-bell"></i> Notificaciones
                                @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                                    <span class="notification-counter">{{ $unreadNotificationsCount }}</span>
                                @endif
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-divider"></div>

                <div class="sidebar-footer">
                    <p>© {{ date('Y') }} NorETEL CRM v1.0</p>
                </div>
            </nav>
        @endauth

        <!-- Contenido principal -->
        <div class="content-wrapper">
            @auth
                <!-- Navbar superior -->
                <nav class="navbar navbar-expand topbar mb-4 static-top">
                    <div class="container-fluid px-4">
                        <button type="button" id="sidebarCollapse" class="action-btn d-md-none">
                            <i class="fas fa-bars"></i>
                        </button>

                        <div class="d-flex align-items-center">
                            <h1 class="h4 mb-0 text-gray-800">@yield('page-title', 'Dashboard')</h1>
                        </div>

                        <ul class="navbar-nav ms-auto">
                            <!-- Dropdown de notificaciones -->
                            <li class="nav-item dropdown no-arrow mx-1">
                                <a class="nav-link dropdown-toggle position-relative" href="#" id="alertsDropdown"
                                    role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-bell fa-fw"></i>
                                    @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                                        <span class="notification-counter">{{ $unreadNotificationsCount }}</span>
                                    @endif
                                </a>
                                <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="alertsDropdown"
                                    style="min-width: 320px;">
                                    <div class="dropdown-header bg-primary text-white py-2 px-3">
                                        <i class="fas fa-bell me-2"></i> Centro de Notificaciones
                                    </div>

                                    @if(isset($recentNotifications) && count($recentNotifications) > 0)
                                        @foreach($recentNotifications as $notification)
                                            <a class="notification-item" href="{{ route('core.notifications.index') }}">
                                                <div class="notification-icon primary">
                                                    <i class="fas fa-file-alt"></i>
                                                </div>
                                                <div class="notification-content">
                                                    <div class="notification-time">
                                                        {{ $notification->created_at->format('d/m/Y H:i') }}</div>
                                                    <div
                                                        class="{{ !isset($notification->metadata['read']) || !$notification->metadata['read'] ? 'fw-bold' : '' }}">
                                                        {{ Str::limit($notification->action_detail, 50) }}
                                                    </div>
                                                </div>
                                            </a>
                                        @endforeach
                                    @else
                                        <div class="notification-item justify-content-center">
                                            No hay notificaciones
                                        </div>
                                    @endif

                                    <div class="dropdown-footer bg-light text-center py-2">
                                        <a href="{{ route('core.notifications.index') }}" class="text-decoration-none">Ver
                                            todas las notificaciones</a>
                                    </div>
                                </div>
                            </li>

                            <div class="topbar-divider d-none d-sm-block"></div>

                            <!-- Dropdown de usuario -->
                            <li class="nav-item dropdown no-arrow me-2 pe-2 user-nav-item">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-dropdown">
                                        <img class="img-profile rounded-circle"
                                            src="https://ui-avatars.com/api/?name={{ auth()->user()->username }}&background=0099ff&color=fff">
                                        <span class="user-name d-none d-lg-block">{{ ucfirst(auth()->user()->username) }}</span>
                                        <i class="fas fa-chevron-down text-muted" style="font-size: 0.75rem;"></i>
                                    </div>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in"
                                    aria-labelledby="userDropdown">
                                    <div class="dropdown-header py-2">
                                        <small class="text-muted">Cuenta de usuario</small>
                                    </div>
                                    <a class="dropdown-item" href="{{ route('core.change-password') }}">
                                        <i class="fas fa-key fa-sm fa-fw me-2 text-gray-400"></i>
                                        Cambiar contraseña
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <form action="{{ route('core.auth.logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                                            Cerrar sesión
                                        </button>
                                    </form>
                                </div>
                            </li>
                        </ul>
                    </div>
                </nav>
            @endauth

            <!-- Contenedor principal -->
            <div class="container-fluid px-4">
                <!-- Mensajes de alerta -->
                <div class="alerts-container mb-4">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if(session('info'))
                        <div class="alert alert-info alert-dismissible fade show">
                            <i class="fas fa-info-circle"></i> {{ session('info') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> Hay errores en el formulario:
                            <ul class="mb-0 mt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                </div>

                <!-- Contenido de la página -->
                <div class="content-container mb-4">
                    @yield('content')
                </div>

                <!-- Footer -->
                <footer class="py-3 mt-auto">
                    <div class="container">
                        <div class="text-center text-muted">
                            <span>© {{ date('Y') }} NorETEL - Todos los derechos reservados</span>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </div>

    <!-- Scripts JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>

    <!-- Script para Chart.js - necesario para dashboard de clientes -->
    @if(request()->segment(1) == 'users' && request()->segment(2) == '')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endif

    <script>
        // Toggle sidebar on mobile
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarCollapse = document.getElementById('sidebarCollapse');
            const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
            const sidebar = document.getElementById('sidebar');

            if (sidebarCollapse) {
                sidebarCollapse.addEventListener('click', function () {
                    if (sidebar) {
                        sidebar.classList.toggle('active');
                    }
                });
            }

            if (sidebarCollapseBtn) {
                sidebarCollapseBtn.addEventListener('click', function () {
                    if (sidebar) {
                        sidebar.classList.toggle('active');
                    }
                });
            }

            // Cerrar alertas automáticamente después de 5 segundos
            const alerts = document.querySelectorAll('.alert:not(.alert-danger)');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const closeButton = alert.querySelector('.btn-close');
                    if (closeButton) {
                        closeButton.click();
                    }
                }, 5000);
            });
        });

        // Componente para agrupar botones de acción (para usar en tablas, formularios, etc.)
        class ActionButtons {
            constructor(container, buttons) {
                this.container = container;
                this.buttons = buttons;
                this.render();
            }

            render() {
                const buttonGroup = document.createElement('div');
                buttonGroup.className = 'action-btn-group';

                this.buttons.forEach(button => {
                    const btn = document.createElement('button');
                    btn.type = button.type || 'button';
                    btn.className = `action-btn ${button.class || ''}`;
                    btn.title = button.title || '';

                    if (button.id) btn.id = button.id;
                    if (button.onclick) btn.onclick = button.onclick;

                    const icon = document.createElement('i');
                    icon.className = button.icon;
                    btn.appendChild(icon);

                    buttonGroup.appendChild(btn);
                });

                this.container.appendChild(buttonGroup);
                return buttonGroup;
            }
        }

        // Helper para crear botones de acción más fácilmente
        function createActionButtons(containerId, buttons) {
            const container = document.getElementById(containerId);
            if (container) {
                return new ActionButtons(container, buttons);
            }
            return null;
        }
    </script>

    @stack('scripts')
</body>

</html>
