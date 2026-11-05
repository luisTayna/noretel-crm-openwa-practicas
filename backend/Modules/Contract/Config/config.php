<?php

return [
    'name' => 'Contract',
    
    /*
    |--------------------------------------------------------------------------
    | Contract Statuses
    |--------------------------------------------------------------------------
    |
    | Define the available statuses for contracts
    |
    */
    'contract_statuses' => [
        'pending_installation' => 'Pendiente de instalación',
        'active' => 'Activo',
        'expired' => 'Vencido',
        'renewed' => 'Renovado',
        'cancelled' => 'Cancelado',
        'suspended' => 'Suspendido',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Installation Statuses
    |--------------------------------------------------------------------------
    |
    | Define the available statuses for installations
    |
    */
    'installation_statuses' => [
        'scheduled' => 'Programada',
        'in_progress' => 'En progreso',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
        'postponed' => 'Pospuesta',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Route Statuses
    |--------------------------------------------------------------------------
    |
    | Define the available statuses for installation routes
    |
    */
    'route_statuses' => [
        'scheduled' => 'Programada',
        'in_progress' => 'En progreso',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | SLA Service Levels
    |--------------------------------------------------------------------------
    |
    | Define the available service levels for SLAs
    |
    */
    'sla_service_levels' => [
        'premium' => 'Premium',
        'standard' => 'Estándar',
        'basic' => 'Básico',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Installation Photo Types
    |--------------------------------------------------------------------------
    |
    | Define the required photo types for installations
    |
    */
    'installation_photo_types' => [
        'exterior' => 'Exterior del domicilio',
        'interior' => 'Interior del domicilio',
        'equipment' => 'Equipos instalados',
        'signal' => 'Medición de señal',
        'customer' => 'Cliente con la instalación',
        'other' => 'Otros',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Default SLA Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for service level agreements
    |
    */
    'default_sla' => [
        'response_time' => [
            'premium' => 1, // horas
            'standard' => 4, // horas
            'basic' => 24, // horas
        ],
        'resolution_time' => [
            'premium' => 4, // horas
            'standard' => 24, // horas
            'basic' => 48, // horas
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Settings for contract notifications
    |
    */
    'notifications' => [
        'expiration_reminder_days' => [30, 15, 7, 3, 1], // Días antes de vencimiento para enviar recordatorios
        'include_customer_email' => true, // Enviar notificaciones por correo al cliente
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Installation Settings
    |--------------------------------------------------------------------------
    |
    | Settings for installation process
    |
    */
    'installation' => [
        'max_installations_per_day' => 8, // Máximo de instalaciones por día por técnico
        'default_installation_duration' => 120, // Duración por defecto en minutos
        'require_customer_signature' => false, // Requerir firma del cliente para completar
        'require_photos' => false, // Requerir fotos para completar
    ],
];
