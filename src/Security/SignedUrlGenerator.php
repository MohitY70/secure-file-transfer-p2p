<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Security;

class SignedUrlGenerator
{
    public function generate(string $fileId, string $clientIp): array
    {
        $expiresAt = time() + (int) config('secure-transfer.security.signed_urls.ttl_seconds', 60);
        $bindIp = (bool) config('secure-transfer.security.signed_urls.allowed_ips', true);

        $ipHash = $bindIp ? hash('sha256', $clientIp) : '';
        $payload = implode('|', [$fileId, $expiresAt, $ipHash]);

        $secret = (string) config('secure-transfer.auth.hmac.secret', '');
        $signature = hash_hmac('sha256', $payload, $secret);

        $url = route('secure-transfer.signed-download', ['id' => $fileId, 'expires' => $expiresAt, 'ip' => $ipHash, 'sig' => $signature]);

        return [
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }
}
