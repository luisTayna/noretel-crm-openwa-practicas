<?php

return [
    'name' => 'Core',
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Here you can configure authentication related settings for the Core module
    |
    */
    'auth' => [
        // Tiempo de expiración del token de acceso en minutos
        'token_expiration' => env('AUTH_TOKEN_EXPIRATION', 60),
        
        // Habilitar autenticación de dos factores
        'enable_2fa' => env('AUTH_ENABLE_2FA', false),
        
        // Número máximo de intentos fallidos de inicio de sesión
        'max_login_attempts' => env('AUTH_MAX_LOGIN_ATTEMPTS', 5),
        
        // Tiempo de bloqueo después de superar intentos fallidos (minutos)
        'lockout_time' => env('AUTH_LOCKOUT_TIME', 15),
        
        // Tiempo de expiración del token de restablecimiento de contraseña (minutos)
        'password_reset_expiration' => env('AUTH_PASSWORD_RESET_EXPIRATION', 60),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | User Settings
    |--------------------------------------------------------------------------
    |
    | Configure user related settings
    |
    */
    'users' => [
        // Roles predefinidos del sistema
        'default_roles' => [
            'admin' => 'Administrador',
            'sales' => 'Ventas',
            'technician' => 'Técnico',
        ],
        
        // Rol por defecto para nuevos usuarios
        'default_user_role' => 'sales',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure notification related settings
    |
    */
    'notifications' => [
        // Canales habilitados para envío de notificaciones
        'channels' => [
            'email' => true,
            'sms' => false,
            'system' => true,
        ],
        
        // Configuración de correo electrónico
        'email' => [
            'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'from_name' => env('MAIL_FROM_NAME', 'ISP-CRM'),
        ],
        
        // Procesamiento automático de notificaciones pendientes
        'auto_process' => true,
        
        // Intervalo de procesamiento (minutos)
        'process_interval' => 5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Workflow Settings
    |--------------------------------------------------------------------------
    |
    | Configure workflow related settings
    |
    */
    'workflows' => [
        // Tipos de workflow soportados
        'types' => [
            'ticket' => 'Tickets de Soporte',
            'installation' => 'Instalaciones',
            'contract' => 'Contratos',
            'service_request' => 'Solicitudes de Servicio',
        ],
        
        // Validar transiciones de workflow
        'validate_transitions' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security related settings
    |
    */
    'security' => [
        // Política de contraseñas predeterminada
        'password_policy' => [
            'min_length' => 8,
            'require_numbers' => true,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_special' => true,
        ],
        
        // Tiempo de expiración de la sesión (minutos)
        'session_lifetime' => env('SESSION_LIFETIME', 120),
        
        // Forzar HTTPS
        'force_https' => env('FORCE_HTTPS', false),
        
        // Rate limiting para APIs y endpoints sensibles
        'api_rate_limit' => 60, // por minuto
        'login_rate_limit' => 5, // por minuto
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Audit Settings
    |--------------------------------------------------------------------------
    |
    | Configure audit related settings
    |
    */
    'audit' => [
        // Habilitar auditoría
        'enabled' => true,
        
        // Eventos a auditar
        'events' => [
            'login' => true,
            'logout' => true,
            'create' => true,
            'update' => true,
            'delete' => true,
        ],
        
        // Período de retención de registros (días)
        'retention_period' => 90,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Configuration Settings
    |--------------------------------------------------------------------------
    |
    | Configure system configuration settings
    |
    */
    'configuration' => [
        // Habilitar caché de configuraciones
        'enable_cache' => true,
        
        // Tiempo de caché (minutos)
        'cache_time' => 60,
    ],
];
