<?php

namespace HeyGeeks\SecureFileTransfer\Auth;

use Illuminate\Http\Request;
use HeyGeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;

class HmacAuthenticator implements AuthenticatorInterface
{
    protected string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function authenticate(Request $request): bool
    {
        $signature = $request->header('X-Signature');
        $timestamp = $request->header('X-Timestamp');
        $clientId = $request->header('X-Client-ID');

        if (!$signature || !$timestamp || !$clientId) {
            throw new AuthenticationFailedException('Missing HMAC authentication headers');
        }

        // Validate timestamp (prevent very old requests)
        $requestTime = (int)$timestamp;
        $currentTime = time();
        if (abs($currentTime - $requestTime) > 300) { // 5 minutes
            throw new AuthenticationFailedException('Request timestamp is too old');
        }

        // Build payload to sign
        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $body = $request->getContent();

        $payload = "{$method}|{$path}|{$body}|{$timestamp}|{$clientId}";

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->secret);

        // Timing-safe comparison
        if (!hash_equals($expectedSignature, $signature)) {
            throw new AuthenticationFailedException('Invalid HMAC signature');
        }

        return true;
    }

    public function getIdentity(Request $request): string
    {
        return $request->header('X-Client-ID') ?? 'unknown';
    }

    public function getTokenId(Request $request): string
    {
        $clientId = $request->header('X-Client-ID') ?? 'unknown';
        return hash('sha256', $clientId . '|' . $this->secret);
    }
}
