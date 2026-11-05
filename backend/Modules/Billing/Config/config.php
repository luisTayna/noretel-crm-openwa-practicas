<?php

return [
    'name' => 'Billing',
    'invoice' => [
        'auto_generation' => true,
        'prefix' => 'INV-',
        'digits' => 6,
        'due_days' => 15,
        'monthly_generation_day' => env('BILLING_MONTHLY_INVOICE_GENERATION_DAY', 15),
        'fixed_due_day' => env('BILLING_MONTHLY_DUE_DAY', 30),
        'grace_period_days' => env('BILLING_PAYMENT_GRACE_PERIOD_DAYS', 5),
        'payment_reminder_days' => [3, 7, 1],
        'late_fee_percentage' => 5
    ],
    'payment' => [
        'methods' => [
            'cash' => 'Efectivo',
            'yape' => 'Yape',
            'customer_credit' => 'Saldo a Favor',
            'saldo_a_favor' => 'Saldo a Favor',
            'credit_card' => 'Tarjeta de Crédito',
            'debit_card' => 'Tarjeta de Débito',
            'bank_transfer' => 'Transferencia Bancaria',
            'check' => 'Cheque',
            'online_payment' => 'Pago en Línea',
            'direct_debit' => 'Débito Automático'
        ]
    ],
    'yape' => [
        'bot_enabled' => env('YAPE_BOT_ENABLED', true),
        'monitor_token' => env('YAPE_MONITOR_TOKEN'),
        'receiver_name' => env('YAPE_RECEIVER_NAME', 'NORETEL'),
        'receiver_phone' => env('YAPE_RECEIVER_PHONE'),
        'time_tolerance_minutes' => env('YAPE_TIME_TOLERANCE_MINUTES', 10),
        'timezone_offset_minutes' => env('YAPE_TIMEZONE_OFFSET_MINUTES', 300),
        'confidence_threshold' => env('YAPE_CONFIDENCE_THRESHOLD', 0.88),
        'intent_expiration_minutes' => env('YAPE_INTENT_EXPIRATION_MINUTES', 1440),
        'storage_disk' => env('YAPE_STORAGE_DISK', 'local'),
        'client_evidence_dir' => env('YAPE_CLIENT_EVIDENCE_DIR', 'yape/client-evidences'),
        'receiver_event_dir' => env('YAPE_RECEIVER_EVENT_DIR', 'yape/receiver-events'),
        'payment_receipt_dir' => env('YAPE_PAYMENT_RECEIPT_DIR', 'yape/payment-receipts'),
        'whatsapp_inbox_path' => env('YAPE_WHATSAPP_INBOX_PATH'),
        'ocr_mode' => env('YAPE_OCR_MODE', 'local'),
        'ocr_remote_url' => env('YAPE_OCR_REMOTE_URL'),
        'ocr_remote_secret' => env('YAPE_OCR_REMOTE_SECRET'),
        'ocr_remote_timeout' => env('YAPE_OCR_REMOTE_TIMEOUT', 8),
        'openwa' => [
            'api_url' => env('YAPE_OPENWA_API_URL', 'http://127.0.0.1:2785/api'),
            'api_key' => env('YAPE_OPENWA_API_KEY'),
            'session' => env('YAPE_OPENWA_SESSION', 'noretel-yape'),
            'default_country_code' => env('YAPE_OPENWA_DEFAULT_COUNTRY_CODE', '51'),
            'webhook_secret' => env('YAPE_OPENWA_WEBHOOK_SECRET'),
            'http_timeout' => env('YAPE_OPENWA_HTTP_TIMEOUT', 20),
            'ocr_enabled' => env('YAPE_OPENWA_OCR_ENABLED', true),
            'python_path' => env('YAPE_OPENWA_PYTHON_PATH', 'python'),
            'ocr_parser_path' => env('YAPE_OPENWA_OCR_PARSER_PATH') ?: base_path('tools/yape_monitor/parse_image.py'),
            'ocr_timeout' => env('YAPE_OPENWA_OCR_TIMEOUT', 30),
            'send_receipt_on_validation' => env('YAPE_OPENWA_SEND_RECEIPT_ON_VALIDATION', true),
        ],
        'adb_path' => env('YAPE_ADB_PATH', 'adb'),
        'device_serial' => env('YAPE_DEVICE_SERIAL'),
    ],
    'credit_note' => [
        'prefix' => 'CN-',
        'digits' => 6
    ],
    'payment_reminders' => [
        'enabled' => env('BILLING_PAYMENT_REMINDER_ENABLED', env('BILLING_OPENWA_REMINDERS_ENABLED', true)),
        'due_day' => env('BILLING_PAYMENT_DUE_DAY', env('BILLING_MONTHLY_DUE_DAY', 30)),
        'use_month_end_fallback' => env('BILLING_PAYMENT_DUE_USE_MONTH_END_FALLBACK', true),
        'distribution_days_before' => env('BILLING_PAYMENT_REMINDER_DISTRIBUTION_DAYS_BEFORE', 6),
        'auto_distribute' => env('BILLING_PAYMENT_REMINDER_AUTO_DISTRIBUTE', true),
        'daily_limit' => env('BILLING_PAYMENT_REMINDER_DAILY_LIMIT', 150),
        'batch_size' => env('BILLING_PAYMENT_REMINDER_BATCH_SIZE', env('BILLING_OPENWA_REMINDER_SEND_LIMIT', 1)),
        'send_limit' => env('BILLING_PAYMENT_REMINDER_BATCH_SIZE', env('BILLING_OPENWA_REMINDER_SEND_LIMIT', 1)),
        'delay_min_seconds' => env('BILLING_PAYMENT_REMINDER_DELAY_MIN_SECONDS', env('BILLING_OPENWA_REMINDER_SEND_DELAY_SECONDS', 12)),
        'delay_max_seconds' => env('BILLING_PAYMENT_REMINDER_DELAY_MAX_SECONDS', 25),
        'send_interval_min_seconds' => env('BILLING_REMINDER_SEND_INTERVAL_MIN_SECONDS', env('BILLING_PAYMENT_REMINDER_SEND_INTERVAL_MIN_SECONDS', 75)),
        'send_interval_max_seconds' => env('BILLING_REMINDER_SEND_INTERVAL_MAX_SECONDS', env('BILLING_PAYMENT_REMINDER_SEND_INTERVAL_MAX_SECONDS', 210)),
        'next_allowed_send_cache_key' => env('BILLING_REMINDER_NEXT_ALLOWED_SEND_CACHE_KEY', 'billing_payment_reminders_next_allowed_send_at'),
        'allowed_start' => env('BILLING_PAYMENT_REMINDER_ALLOWED_START', '09:00'),
        'allowed_end' => env('BILLING_PAYMENT_REMINDER_ALLOWED_END', '18:00'),
        'max_attempts' => env('BILLING_PAYMENT_REMINDER_MAX_ATTEMPTS', env('BILLING_OPENWA_REMINDER_MAX_ATTEMPTS', 3)),
    ],
    'overdue_reminders' => [
        'enabled' => env('BILLING_OVERDUE_REMINDER_ENABLED', true),
        'days' => env('BILLING_OVERDUE_REMINDER_DAYS', '1,3,5'),
        'daily_limit' => env('BILLING_OVERDUE_REMINDER_DAILY_LIMIT', env('BILLING_PAYMENT_REMINDER_DAILY_LIMIT', 150)),
        'max_attempts' => env('BILLING_OVERDUE_REMINDER_MAX_ATTEMPTS', env('BILLING_PAYMENT_REMINDER_MAX_ATTEMPTS', 3)),
    ],
    'tax' => [
        'default_rate' => 18 // Porcentaje de impuesto (ajustar según normativa local)
    ]
];
