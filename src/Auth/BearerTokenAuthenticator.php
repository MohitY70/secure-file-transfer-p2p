<?php

namespace HeyGeeks\SecureFileTransfer\Auth;

use Illuminate\Http\Request;
use HeyGeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;

class BearerTokenAuthenticator implements AuthenticatorInterface
{
    protected string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function authenticate(Request $request): bool
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationFailedException('Missing or invalid Bearer token');
        }

        $providedToken = substr($authHeader, 7);

        // Timing-safe comparison
        if (!hash_equals($this->token, $providedToken)) {
            throw new AuthenticationFailedException('Invalid Bearer token');
        }

        return true;
    }

    public function getIdentity(Request $request): string
    {
        return 'bearer_client';
    }

    public function getTokenId(Request $request): string
    {
        return hash('sha256', $this->token);
    }
}
