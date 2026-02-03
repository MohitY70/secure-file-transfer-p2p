<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Auth;

use Illuminate\Http\Request;
use Heygeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;

class TokenAuthenticator implements AuthenticatorInterface
{
    public function authenticate(Request $request): bool
    {
        $token = $this->extractToken($request);

        if ($token === '') {
            throw new AuthenticationFailedException('Missing token');
        }

        $expectedToken = (string) config('secure-transfer.auth.bearer.token', '');

        if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
            throw new AuthenticationFailedException('Invalid token');
        }

        return true;
    }

    public function getIdentity(Request $request): string
    {
        return 'static-bearer';
    }

    public function getTokenId(Request $request): string
    {
        $token = $this->extractToken($request);

        return substr(hash('sha256', $token), 0, 16);
    }

    private function extractToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return '';
    }
}
