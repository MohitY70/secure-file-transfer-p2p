<?php

return [
    /*
     * Authentication strategy: 'bearer', 'hmac', or 'jwt'
     */
    'auth' => [
        'strategy' => env('SECURE_TRANSFER_AUTH', 'bearer'),
        'token' => env('SECURE_TRANSFER_TOKEN', ''),
        'hmac_secret' => env('SECURE_TRANSFER_HMAC_SECRET', ''),
        'jwt_secret' => env('SECURE_TRANSFER_JWT_SECRET', ''),
    ],

    /*
     * File storage settings
     */
    'storage' => [
        'path' => env('SECURE_TRANSFER_STORAGE_PATH', storage_path('app/secure-transfers')),
        'max_size' => env('SECURE_TRANSFER_MAX_SIZE', 104857600), // 100MB
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain',
            'text/csv',
            'application/json',
            'application/zip',
            'application/x-7z-compressed',
            'application/gzip',
        ],
    ],

    /*
     * Security settings
     */
    'security' => [
        'replay_protection_ttl' => env('SECURE_TRANSFER_REPLAY_TTL', 60), // seconds
        'rate_limit' => [
            'requests' => env('SECURE_TRANSFER_RATE_LIMIT_REQUESTS', 60),
            'window' => env('SECURE_TRANSFER_RATE_LIMIT_WINDOW', 60), // seconds
        ],
        'signed_url_ttl' => env('SECURE_TRANSFER_SIGNED_URL_TTL', 3600), // 1 hour
        'signing_secret' => env('SECURE_TRANSFER_SIGNING_SECRET', 'default-signing-secret'),
    ],

    /*
     * Logging settings
     */
    'logging' => [
        'channel' => env('SECURE_TRANSFER_LOG_CHANNEL', 'single'),
    ],

    /*
     * API prefix
     */
    'api_prefix' => env('SECURE_TRANSFER_API_PREFIX', 'secure-transfer'),
];
