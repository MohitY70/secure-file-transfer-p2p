# Secure File Transfer

A Laravel package enabling secure, authenticated, auditable file transfer between two servers with strong security controls, replay protection, signed URLs, and enterprise-grade logging.

## Features

 **Multiple Authentication Strategies**
- Bearer Token (static secret)
- HMAC (signed requests with timestamp validation)
- JWT (OAuth-style tokens with algorithm verification)

 **Security**
- Replay attack prevention with nonce validation
- Rate limiting per client
- File validation (size, MIME type, filename)
- Magic-byte MIME type checking
- Path traversal prevention
- Timing-safe cryptographic comparisons
- Files stored outside web root

 **File Transfer**
- Secure file uploads with comprehensive validation
- Direct downloads (authenticated)
- Temporary signed URLs for public downloads
- Single-use signed URLs with optional IP binding
- File metadata and hash verification

 **Enterprise Features**
- Structured audit logging
- All events tracked (uploads, downloads, auth failures, rate limits, etc.)
- No raw secrets logged
- Pluggable authentication
- Fully configurable

## Installation

### 1. Install via Composer

```bash
composer require heygeeks/secure-file-transfer
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=secure-transfer
```

This publishes `config/secure-transfer.php` to your application.

### 3. Configure Environment Variables

```env
# Authentication
SECURE_TRANSFER_AUTH=bearer  # or 'hmac' or 'jwt'
SECURE_TRANSFER_TOKEN=your-secret-token  # for bearer
SECURE_TRANSFER_HMAC_SECRET=your-hmac-secret  # for hmac
SECURE_TRANSFER_JWT_SECRET=your-jwt-secret  # for jwt

# Storage
SECURE_TRANSFER_STORAGE_PATH=/secure/path/outside/web/root
SECURE_TRANSFER_MAX_SIZE=104857600  # 100MB in bytes

# Security
SECURE_TRANSFER_REPLAY_TTL=60  # seconds
SECURE_TRANSFER_RATE_LIMIT_REQUESTS=60
SECURE_TRANSFER_RATE_LIMIT_WINDOW=60
SECURE_TRANSFER_SIGNED_URL_TTL=3600  # 1 hour
SECURE_TRANSFER_SIGNING_SECRET=your-signing-secret

# Logging
SECURE_TRANSFER_LOG_CHANNEL=single
```

## Quick Start

### Server B (Upload/Download Endpoint)

The package automatically registers routes for your Laravel application:

- `POST /secure-transfer/upload` - Upload a file
- `GET /secure-transfer/download/{id}` - Download a file (authenticated)
- `POST /secure-transfer/request-url/{id}` - Request a signed URL
- `GET /secure-transfer/status/{id}` - Get file metadata

All authenticated endpoints require the appropriate auth headers based on your configured strategy.

### Server A (File Transfer Client)

```php
use HeyGeeks\SecureFileTransfer\Client\TransferClient;

// Initialize client
$client = new TransferClient(
    baseUrl: 'https://server-b.example.com',
    authType: 'bearer',
    secret: env('SECURE_TRANSFER_TOKEN'),
);

// Upload a file
$response = $client->upload('/path/to/file.pdf');
$fileId = $response['id'];

// Download directly
$client->download($fileId, '/path/to/downloaded/file.pdf');

// Get signed URL and download
$signedUrl = $client->getSignedUrl($fileId, expiresIn: 3600, ipBound: true);
$client->downloadViaSignedUrl($signedUrl, '/path/to/downloaded/file.pdf');

// Get file status
$status = $client->getStatus($fileId);
```

## Authentication Strategies

### Bearer Token

Simplest strategy for static secrets.

```env
SECURE_TRANSFER_AUTH=bearer
SECURE_TRANSFER_TOKEN=your-static-token
```

Request header:
```
Authorization: Bearer your-static-token
```

### HMAC

Request signing with timestamp validation.

```env
SECURE_TRANSFER_AUTH=hmac
SECURE_TRANSFER_HMAC_SECRET=your-secret
```

Request headers:
```
X-Signature: <sha256-hmac-signature>
X-Timestamp: <unix-timestamp>
X-Client-ID: <client-identifier>
X-Nonce: <optional-replay-protection-nonce>
```

### JWT

OAuth-style tokens with algorithm verification.

```env
SECURE_TRANSFER_AUTH=jwt
SECURE_TRANSFER_JWT_SECRET=your-secret
```

Request header:
```
Authorization: Bearer <jwt-token>
```

## Security Features

### Replay Attack Prevention

Every request can include an `X-Nonce` header. If provided, the nonce is validated to ensure it hasn't been used before.

```php
// In your request (e.g., with TransferClient)
'X-Nonce' => Str::random(32),
```

The nonce is cached with a configurable TTL (default 60 seconds).

### Rate Limiting

Requests are rate-limited per client:

```env
SECURE_TRANSFER_RATE_LIMIT_REQUESTS=60    # requests per window
SECURE_TRANSFER_RATE_LIMIT_WINDOW=60      # seconds
```

When exceeded, the API returns HTTP 429.

### File Validation

- **Size**: Checked against configured maximum (default 100MB)
- **MIME Type**: Validated using magic bytes (not just extension)
- **Filename**: Checked for path traversal, null bytes, and shell metacharacters

Whitelist of allowed MIME types is configurable in `config/secure-transfer.php`.

### Signed URLs

Temporary download links with optional features:

```php
// Request a signed URL
$signedUrl = $client->getSignedUrl(
    fileId: $fileId,
    expiresIn: 3600,        // 1 hour
    ipBound: true,          // Lock to client IP
);
```

Features:
- Short TTL (configurable)
- Optional IP binding
- Single-use only (deleted after download)
- HMAC-protected

## Audit Logging

All events are logged with structured context:

```
UPLOAD_SUCCESS       - File uploaded successfully
UPLOAD_FAILED        - Upload validation or processing failed
DOWNLOAD_SUCCESS     - File downloaded successfully
DOWNLOAD_FAILED      - Download failed
AUTH_FAILED          - Authentication failed
REPLAY_DETECTED      - Replay attack detected (critical)
RATE_LIMIT_HIT       - Rate limit exceeded
SIGNED_URL_CREATED   - Signed URL generated
SIGNED_URL_USED      - Signed URL download completed
SIGNED_URL_EXPIRED   - Signed URL expired/invalid
FILE_DELETED         - File deleted
```

Logs are never written with raw secrets—only token IDs and hashes.

## Configuration

### config/secure-transfer.php

```php
return [
    // Authentication settings
    'auth' => [
        'strategy' => 'bearer',           // 'bearer', 'hmac', or 'jwt'
        'token' => '',
        'hmac_secret' => '',
        'jwt_secret' => '',
    ],

    // Storage settings
    'storage' => [
        'path' => storage_path('app/secure-transfers'),
        'max_size' => 104857600,          // 100MB
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            // ... more MIME types
        ],
    ],

    // Security settings
    'security' => [
        'replay_protection_ttl' => 60,
        'rate_limit' => [
            'requests' => 60,
            'window' => 60,
        ],
        'signed_url_ttl' => 3600,
        'signing_secret' => 'default-signing-secret',
    ],

    // Logging settings
    'logging' => [
        'channel' => 'single',
    ],

    // API prefix
    'api_prefix' => 'secure-transfer',
];
```

## Architecture

### Core Components

- **AuthenticatorInterface**: Pluggable authentication strategies
- **NonceStore**: Replay attack prevention
- **RateLimiter**: Request throttling
- **FileValidator**: File validation (size, MIME, filename)
- **SecureFileStore**: Safe file storage with metadata
- **TransferLogger**: Structured audit logging
- **SecureTransferAuthMiddleware**: Request authentication
- **SecureTransferController**: API endpoints
- **TransferClient**: Server-to-server client library

### Storage

Files are stored with:
- UUID filenames (not original names)
- JSON metadata sidecars containing file information
- Directory outside web root
- Restrictive directory permissions (0750)

## Security Best Practices

1. **Use environment variables** for all secrets
2. **Store files outside web root** - configure `SECURE_TRANSFER_STORAGE_PATH`
3. **Use HTTPS** for all transfers
4. **Rotate secrets regularly**
5. **Monitor logs** for suspicious activity
6. **Use JWT or HMAC** instead of Bearer tokens for production
7. **Enable IP binding** for signed URLs in trusted environments
8. **Set appropriate rate limits** for your use case
9. **Restrict file types** to those actually needed
10. **Use strong signing secrets** for signed URLs

## Testing

The package includes comprehensive feature tests:

```bash
php artisan test
```

Tests cover:
- All authentication strategies
- Replay protection
- Rate limiting
- File validation
- Upload/download workflows
- Signed URLs
- Security edge cases

## Support

For issues, questions, or contributions, please visit the repository:
https://github.com/heygeeks/secure-file-transfer

## License

MIT License - see LICENSE file for details.
