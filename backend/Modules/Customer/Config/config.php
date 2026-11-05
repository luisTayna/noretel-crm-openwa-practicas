<?php

return [
    'name' => 'Customer',

    'commercial_openwa' => [
        'enabled' => env('COMMERCIAL_OPENWA_ENABLED', false),
        'api_url' => env('COMMERCIAL_OPENWA_API_URL'),
        'api_key' => env('COMMERCIAL_OPENWA_API_KEY'),
        'session' => env('COMMERCIAL_OPENWA_SESSION'),
        'webhook_secret' => env('COMMERCIAL_OPENWA_WEBHOOK_SECRET'),
        'http_timeout' => env('COMMERCIAL_OPENWA_HTTP_TIMEOUT', 20),
        'default_country_code' => env('COMMERCIAL_OPENWA_DEFAULT_COUNTRY_CODE', '51'),
    ],
];
