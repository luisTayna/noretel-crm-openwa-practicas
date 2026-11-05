<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Credenciales por Defecto
    |--------------------------------------------------------------------------
    |
    | Estas credenciales se usan cuando no se especifican en el router
    |
    */
    'default_username' => env('ROUTER_DEFAULT_USERNAME', 'admin'),
    'default_password' => env('ROUTER_DEFAULT_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Configuración de MikroTik
    |--------------------------------------------------------------------------
    */
    'mikrotik' => [
        'rest_port' => env('MIKROTIK_REST_PORT', null), // null = puerto por defecto (80/443)
        'use_https' => env('MIKROTIK_USE_HTTPS', true),
        'timeout' => env('MIKROTIK_TIMEOUT', 30),

        // Perfiles PPPoE por defecto
        'default_profile' => env('MIKROTIK_DEFAULT_PROFILE', 'default'),

        // Pool de IPs para clientes
        'ip_pool' => env('MIKROTIK_IP_POOL', '10.0.0.0/8'),

        // DNS Servers
        'dns_servers' => env('MIKROTIK_DNS_SERVERS', '8.8.8.8,8.8.4.4'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Huawei
    |--------------------------------------------------------------------------
    */
    'huawei' => [
        'api_url' => env('HUAWEI_API_URL', null),
        'api_token' => env('HUAWEI_API_TOKEN', null),
        'timeout' => env('HUAWEI_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    |
    | Configuración para el monitoreo de routers
    |
    */
    'health_check' => [
        'enabled' => env('NETWORK_HEALTH_CHECK_ENABLED', true),
        'interval' => env('NETWORK_HEALTH_CHECK_INTERVAL', 5), // minutos

        // Umbrales de alerta
        'thresholds' => [
            'cpu_warning' => 70,
            'cpu_critical' => 90,
            'memory_warning' => 80,
            'memory_critical' => 95,
            'clients_warning' => 85, // % de capacidad
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Automatización
    |--------------------------------------------------------------------------
    */
    'automation' => [
        'enabled' => env('NETWORK_AUTOMATION_ENABLED', true),

        // Reinicio automático cuando falla
        'auto_retry' => true,
        'max_retries' => 3,
        'retry_delay' => 60, // segundos
    ],

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'retention_days' => 90, // días que se guardan los logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Worker
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('QUEUE_CONNECTION', 'database'),
        'queue_name' => 'network',
        'timeout' => 120, // segundos
        'tries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | OLT y ONT (GPON)
    |--------------------------------------------------------------------------
    |
    | Umbrales de potencia óptica en dBm. Este es el ÚNICO lugar donde se
    | definen: las vistas no deciden colores ni palabras, solo muestran el
    | estado que ya viene calculado en la base de datos.
    |
    | Referencia GPON clase B+: el receptor del ONT trabaja entre -8 dBm
    | (saturación) y -27 dBm (sensibilidad mínima).
    |
    */
    'ont' => [
        'thresholds' => [
            // Por encima de este valor la señal llega demasiado fuerte y también falla.
            'overload_dbm' => env('ONT_OVERLOAD_DBM', -8.0),

            // Igual o mejor que este valor: señal buena.
            'good_min_dbm' => env('ONT_GOOD_MIN_DBM', -25.0),

            // Entre good_min y este valor: señal débil. Por debajo: muy débil.
            'warning_min_dbm' => env('ONT_WARNING_MIN_DBM', -27.0),

            // Caída sostenida respecto de la línea base del propio ONT.
            // Detecta degradación aunque el valor absoluto siga "en rango".
            'degradation_db' => env('ONT_DEGRADATION_DB', 3.0),
        ],

        // Una lectura más vieja que esto se considera sin datos, no buena.
        'reading_max_age_minutes' => env('ONT_READING_MAX_AGE_MINUTES', 60),
    ],
];
