<?php

namespace HeyGeeks\SecureFileTransfer\Logging;

use Illuminate\Support\Facades\Log;

class TransferLogger
{
    protected string $channel;

    public function __construct(string $channel = 'secure_transfer')
    {
        $this->channel = $channel;
    }

    // Upload events
    public function uploadSuccess(string $fileId, string $originalName, int $size, string $clientId): void
    {
        Log::channel($this->channel)->info('File uploaded successfully', [
            'event' => 'UPLOAD_SUCCESS',
            'file_id' => $fileId,
            'file_name' => $originalName,
            'file_size' => $size,
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function uploadFailed(string $originalName, string $reason, string $clientId): void
    {
        Log::channel($this->channel)->warning('File upload failed', [
            'event' => 'UPLOAD_FAILED',
            'file_name' => $originalName,
            'reason' => $reason,
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // Download events
    public function downloadSuccess(string $fileId, string $clientId): void
    {
        Log::channel($this->channel)->info('File downloaded successfully', [
            'event' => 'DOWNLOAD_SUCCESS',
            'file_id' => $fileId,
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function downloadFailed(string $fileId, string $reason, string $clientId): void
    {
        Log::channel($this->channel)->warning('File download failed', [
            'event' => 'DOWNLOAD_FAILED',
            'file_id' => $fileId,
            'reason' => $reason,
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // Authentication events
    public function authenticationFailed(string $reason, ?string $clientId = null): void
    {
        Log::channel($this->channel)->warning('Authentication failed', [
            'event' => 'AUTH_FAILED',
            'reason' => $reason,
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // Replay attack detection
    public function replayDetected(string $nonce, string $clientId): void
    {
        Log::channel($this->channel)->critical('Replay attack detected', [
            'event' => 'REPLAY_DETECTED',
            'nonce_hash' => hash('sha256', $nonce),
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // Rate limit events
    public function rateLimitHit(string $clientId): void
    {
        Log::channel($this->channel)->warning('Rate limit exceeded', [
            'event' => 'RATE_LIMIT_HIT',
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // Signed URL events
    public function signedUrlCreated(string $fileId, string $clientId): void
    {
        Log::channel($this->channel)->info('Signed URL created', [
            'event' => 'SIGNED_URL_CREATED',
            'file_id' => $fileId,
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function signedUrlUsed(string $fileId, ?string $ipAddress = null): void
    {
        Log::channel($this->channel)->info('Signed URL used', [
            'event' => 'SIGNED_URL_USED',
            'file_id' => $fileId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function signedUrlExpired(string $fileId): void
    {
        Log::channel($this->channel)->info('Signed URL expired', [
            'event' => 'SIGNED_URL_EXPIRED',
            'file_id' => $fileId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // File deletion events
    public function fileDeleted(string $fileId, string $clientId): void
    {
        Log::channel($this->channel)->info('File deleted', [
            'event' => 'FILE_DELETED',
            'file_id' => $fileId,
            'client_id' => $clientId,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
