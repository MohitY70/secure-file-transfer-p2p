<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Auth;

use Illuminate\Http\Request;
use Heygeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;
use Heygeeks\SecureFileTransfer\Exceptions\ReplayAttackException;
use Heygeeks\SecureFileTransfer\Security\NonceStore;

class HmacAuthenticator implements AuthenticatorInterface
{
    public function __construct(private readonly NonceStore $nonceStore)
    {
    }

    public function authenticate(Request $request): bool
    {
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');
        $signature = (string) $request->header('X-Signature', '');

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            throw new AuthenticationFailedException('Missing HMAC headers');
        }

        $tolerance = (int) config('secure-transfer.auth.hmac.timestamp_tolerance_seconds', 30);
        $currentTime = time();

        if (abs($currentTime - (int) $timestamp) > $tolerance) {
            throw new AuthenticationFailedException('Timestamp expired');
        }

        $this->nonceStore->validate($nonce);

        $payload = $this->buildCanonicalPayload($request, $timestamp, $nonce);
        $secret = (string) config('secure-transfer.auth.hmac.secret', '');
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new AuthenticationFailedException('Signature invalid');
        }

        $this->nonceStore->record($nonce);

        return true;
    }

    public function getIdentity(Request $request): string
    {
        return 'hmac';
    }

    public function getTokenId(Request $request): string
    {
        $signature = (string) $request->header('X-Signature', '');

        return substr($signature, 0, 8);
    }

    private function buildCanonicalPayload(Request $request, string $timestamp, string $nonce): string
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getPathInfo();
        $bodyHash = hash('sha256', $request->getContent() ?? '');

        return implode("\n", [$method, $path, $timestamp, $nonce, $bodyHash]);
    }
}
