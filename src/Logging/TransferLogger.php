<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Logging;

use Illuminate\Support\Facades\Log;

class TransferLogger
{
    public const UPLOAD_SUCCESS = 'UPLOAD_SUCCESS';
    public const UPLOAD_FAILED = 'UPLOAD_FAILED';
    public const DOWNLOAD_SUCCESS = 'DOWNLOAD_SUCCESS';
    public const DOWNLOAD_FAILED = 'DOWNLOAD_FAILED';
    public const AUTH_FAILED = 'AUTH_FAILED';
    public const REPLAY_DETECTED = 'REPLAY_DETECTED';
    public const RATE_LIMIT_HIT = 'RATE_LIMIT_HIT';
    public const SIGNED_URL_CREATED = 'SIGNED_URL_CREATED';
    public const SIGNED_URL_USED = 'SIGNED_URL_USED';
    public const SIGNED_URL_EXPIRED = 'SIGNED_URL_EXPIRED';
    public const FILE_DELETED = 'FILE_DELETED';

    public function log(string $eventType, array $context): void
    {
        if (!(bool) config('secure-transfer.logging.enabled', true)) {
            return;
        }

        $payload = [
            'event' => $eventType,
            'timestamp' => now()->toIso8601String(),
            'token_id' => $context['tokenId'] ?? null,
            'file_id' => $context['fileId'] ?? null,
            'ip' => (bool) config('secure-transfer.logging.log_ip', true) ? ($context['ip'] ?? null) : null,
            'hash' => (bool) config('secure-transfer.logging.log_file_hash', true) ? ($context['hash'] ?? null) : null,
            'status' => $context['status'] ?? null,
            'error' => $context['error'] ?? null,
            'user_agent' => $context['userAgent'] ?? null,
        ];

        $channel = (string) config('secure-transfer.logging.channel', 'default');
        $logger = Log::channel($channel);

        if (in_array($eventType, [self::AUTH_FAILED, self::REPLAY_DETECTED, self::RATE_LIMIT_HIT], true)) {
            $logger->warning('Secure transfer event', $payload);

            return;
        }

        $logger->info('Secure transfer event', $payload);
    }

    public function logUpload(string $tokenId, string $fileId, string $hash, string $ip): void
    {
        $this->log(self::UPLOAD_SUCCESS, [
            'tokenId' => $tokenId,
            'fileId' => $fileId,
            'hash' => $hash,
            'ip' => $ip,
            'status' => 'success',
        ]);
    }

    public function logDownload(string $tokenId, string $fileId, string $ip): void
    {
        $this->log(self::DOWNLOAD_SUCCESS, [
            'tokenId' => $tokenId,
            'fileId' => $fileId,
            'ip' => $ip,
            'status' => 'success',
        ]);
    }

    public function logAuthFailure(string $ip, string $reason): void
    {
        $this->log(self::AUTH_FAILED, [
            'ip' => $ip,
            'status' => 'failed',
            'error' => $reason,
        ]);
    }

    public function logReplayAttack(string $nonce, string $ip): void
    {
        $this->log(self::REPLAY_DETECTED, [
            'status' => 'failed',
            'error' => "Nonce replayed: {$nonce}",
            'ip' => $ip,
        ]);
    }
}
