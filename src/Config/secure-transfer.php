<?php

declare(strict_types=1);

return [
    // ------------------------------------------------------------
    // Authentication
    // ------------------------------------------------------------
    'auth' => [
        'strategy' => env('SECURE_TRANSFER_AUTH', 'bearer'),
        'bearer' => [
            'token' => env('SECURE_TRANSFER_TOKEN', ''),
            'header' => 'Authorization',
        ],
        'hmac' => [
            'secret' => env('SECURE_TRANSFER_HMAC_SECRET', ''),
            'algo' => 'sha256',
            'timestamp_tolerance_seconds' => 30,
        ],
        'jwt' => [
            'secret' => env('SECURE_TRANSFER_JWT_SECRET', ''),
            'algorithm' => 'HS256',
            'ttl_seconds' => 3600,
            'issuer' => env('SECURE_TRANSFER_JWT_ISSUER', 'file-transfer-client'),
        ],
    ],

    // ------------------------------------------------------------
    // Storage
    // ------------------------------------------------------------
    'storage' => [
        'base_path' => env('SECURE_TRANSFER_STORAGE_PATH', storage_path('secure-transfers')),
        'max_file_size_bytes' => env('SECURE_TRANSFER_MAX_SIZE', 104857600),
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/zip',
        ],
        'filename_strategy' => 'uuid',
    ],

    // ------------------------------------------------------------
    // Security
    // ------------------------------------------------------------
    'security' => [
        'replay_protection' => [
            'enabled' => true,
            'nonce_ttl_seconds' => 60,
        ],
        'rate_limit' => [
            'enabled' => true,
            'max_requests_per_minute' => 60,
        ],
        'signed_urls' => [
            'ttl_seconds' => 60,
            'allowed_ips' => true,
        ],
    ],

    // ------------------------------------------------------------
    // Logging
    // ------------------------------------------------------------
    'logging' => [
        'enabled' => true,
        'channel' => env('SECURE_TRANSFER_LOG_CHANNEL', 'default'),
        'log_ip' => true,
        'log_file_hash' => true,
    ],
];
