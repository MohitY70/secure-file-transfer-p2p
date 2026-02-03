# Setup Guide - Secure File Transfer Package

## Overview

This guide walks you through setting up the `heygeeks/secure-file-transfer` package for production use.

## Step 1: Install the Package

Add to your Laravel application:

```bash
composer require heygeeks/secure-file-transfer
```

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=secure-transfer
```

This creates `config/secure-transfer.php` in your application.

## Step 3: Choose Authentication Strategy

### Option A: Bearer Token (Simple)

Best for: Direct server-to-server with static credentials

```env
SECURE_TRANSFER_AUTH=bearer
SECURE_TRANSFER_TOKEN=your-strong-random-token-here-32-chars-minimum
```

Generate a secure token:
```bash
php artisan tinker
> Str::random(64)
```

### Option B: HMAC (Recommended for Production)

Best for: Secure request signing with timestamp validation

```env
SECURE_TRANSFER_AUTH=hmac
SECURE_TRANSFER_HMAC_SECRET=your-strong-random-secret-here-32-chars-minimum
```

### Option C: JWT (OAuth-style)

Best for: Token-based authentication with expiration

```env
SECURE_TRANSFER_AUTH=jwt
SECURE_TRANSFER_JWT_SECRET=your-strong-random-secret-here-32-chars-minimum
```

Generate JWT tokens on Server A:

```php
use Firebase\JWT\JWT;

$payload = [
    'sub' => 'client-id',
    'jti' => uniqid(),
    'iat' => time(),
    'exp' => time() + 3600,
];

$token = JWT::encode($payload, env('SECURE_TRANSFER_JWT_SECRET'), 'HS256');
```

## Step 4: Configure Storage Path

Create a secure directory outside your web root:

```bash
mkdir -p /var/secure/file-transfers
chmod 0750 /var/secure/file-transfers
chown www-data:www-data /var/secure/file-transfers
```

Add to `.env`:

```env
SECURE_TRANSFER_STORAGE_PATH=/var/secure/file-transfers
SECURE_TRANSFER_MAX_SIZE=104857600  # 100MB
```

## Step 5: Configure Security Settings

```env
# Replay protection
SECURE_TRANSFER_REPLAY_TTL=60

# Rate limiting
SECURE_TRANSFER_RATE_LIMIT_REQUESTS=60
SECURE_TRANSFER_RATE_LIMIT_WINDOW=60

# Signed URLs
SECURE_TRANSFER_SIGNED_URL_TTL=3600
SECURE_TRANSFER_SIGNING_SECRET=your-strong-signing-secret
```

## Step 6: Configure Logging

```env
SECURE_TRANSFER_LOG_CHANNEL=stack  # or 'single', 'daily', etc.
```

Ensure you have a log channel configured in `config/logging.php`.

## Step 7: Verify Installation (Optional)

Create a test route:

```php
// routes/api.php
Route::get('/secure-transfer/test', function (Request $request) {
    $transfer = app(\HeyGeeks\SecureFileTransfer\SecureFileTransfer::class);
    
    return response()->json([
        'status' => 'ok',
        'auth_strategy' => config('secure-transfer.auth.strategy'),
        'storage_path' => config('secure-transfer.storage.path'),
        'rate_limit' => config('secure-transfer.security.rate_limit'),
    ]);
});
```

Test:
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost/api/secure-transfer/test
```

## Step 8: Setup Server A (Client)

On your Server A, initialize the client:

```php
use HeyGeeks\SecureFileTransfer\Client\TransferClient;

// Create client
$client = new TransferClient(
    baseUrl: env('SECURE_TRANSFER_SERVER_URL'), // e.g., https://server-b.com
    authType: env('SECURE_TRANSFER_AUTH'),
    secret: env('SECURE_TRANSFER_TOKEN'), // or HMAC_SECRET, or JWT_SECRET
    clientId: env('SECURE_TRANSFER_CLIENT_ID'),
);

// Upload file
$response = $client->upload('/path/to/file.pdf');
$fileId = $response['id'];

// Download file
$client->download($fileId, '/path/to/downloaded/file.pdf');

// Get signed URL
$signedUrl = $client->getSignedUrl($fileId, expiresIn: 3600, ipBound: true);

// Download via signed URL (no auth needed)
$client->downloadViaSignedUrl($signedUrl, '/path/to/file.pdf');
```

## Step 9: Test with cURL

### Upload (Bearer Token)

```bash
curl -X POST https://server-b.com/secure-transfer/upload \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@/path/to/file.pdf"
```

### Upload (HMAC)

```bash
TIMESTAMP=$(date +%s)
CLIENT_ID="client-1"
PAYLOAD="POST|/secure-transfer/upload||${TIMESTAMP}|${CLIENT_ID}"
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "YOUR_SECRET" -binary | xxd -p)

curl -X POST https://server-b.com/secure-transfer/upload \
  -H "X-Signature: $SIGNATURE" \
  -H "X-Timestamp: $TIMESTAMP" \
  -H "X-Client-ID: $CLIENT_ID" \
  -F "file=@/path/to/file.pdf"
```

### Download

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://server-b.com/secure-transfer/download/FILE_ID \
  -o downloaded-file.pdf
```

### Get Status

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://server-b.com/secure-transfer/status/FILE_ID
```

## Security Checklist

- [ ] Use HTTPS for all transfers
- [ ] Store secrets in `.env` (never commit)
- [ ] Use strong, random secrets (minimum 32 characters)
- [ ] Configure storage path outside web root
- [ ] Set appropriate file size limits
- [ ] Restrict MIME types to those needed
- [ ] Enable replay protection (via nonces)
- [ ] Set reasonable rate limits
- [ ] Monitor logs for suspicious activity
- [ ] Rotate secrets regularly
- [ ] Use JWT or HMAC (not Bearer tokens) in production
- [ ] Enable IP binding for signed URLs if possible
- [ ] Implement proper error handling and logging

## Troubleshooting

### "Authentication failed"

- Verify correct auth strategy in `.env`
- Check token/secret matches on both servers
- For HMAC: verify timestamp is within 5 minutes
- For JWT: verify token hasn't expired

### "Rate limit exceeded"

- Check `SECURE_TRANSFER_RATE_LIMIT_REQUESTS` and `SECURE_TRANSFER_RATE_LIMIT_WINDOW`
- Verify cache is working (Redis preferred for production)

### "File validation failed"

- Verify file MIME type is in allowed list
- Check file size is under `SECURE_TRANSFER_MAX_SIZE`
- Check filename doesn't contain special characters

### "Replay attack detected"

- Ensure you're including unique `X-Nonce` headers
- Verify nonce isn't being reused within TTL window

## Production Recommendations

1. **Use Redis for caching** instead of file cache
   ```env
   CACHE_DRIVER=redis
   ```

2. **Use HTTPS everywhere** - no exceptions

3. **Rotate secrets quarterly** - implement a key rotation strategy

4. **Monitor logs** - set up alerts for:
   - AUTH_FAILED
   - REPLAY_DETECTED
   - RATE_LIMIT_HIT

5. **Backup configuration** - keep secure backup of secrets

6. **Use environment-specific secrets** - different for staging/production

7. **Implement request logging** - log all uploads/downloads for audit

8. **Set up alerts** - for failed uploads, suspicious activity

9. **Test disaster recovery** - ensure you can restore from backups

10. **Review permissions** - ensure storage directory has correct permissions

## Support

For issues or questions, see the main README.md or contact support.
