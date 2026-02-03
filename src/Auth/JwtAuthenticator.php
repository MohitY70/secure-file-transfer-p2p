<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Heygeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;

class JwtAuthenticator implements AuthenticatorInterface
{
    public function authenticate(Request $request): bool
    {
        $token = $this->extractToken($request);

        if ($token === '') {
            throw new AuthenticationFailedException('Missing token');
        }

        $algorithm = (string) config('secure-transfer.auth.jwt.algorithm', 'HS256');
        $headerAlg = $this->readHeaderAlgorithm($token);

        if ($headerAlg !== $algorithm) {
            throw new AuthenticationFailedException('Invalid token algorithm');
        }

        $payload = $this->decodeToken($token, $algorithm);

        $issuer = (string) config('secure-transfer.auth.jwt.issuer', 'file-transfer-client');

        if (!isset($payload->exp) || (int) $payload->exp < time()) {
            throw new AuthenticationFailedException('Token expired');
        }

        if (!isset($payload->iss) || $payload->iss !== $issuer) {
            throw new AuthenticationFailedException('Invalid token issuer');
        }

        if (!isset($payload->jti) || $payload->jti === '') {
            throw new AuthenticationFailedException('Missing token identifier');
        }

        return true;
    }

    public function getIdentity(Request $request): string
    {
        return 'jwt';
    }

    public function getTokenId(Request $request): string
    {
        $token = $this->extractToken($request);
        $algorithm = (string) config('secure-transfer.auth.jwt.algorithm', 'HS256');
        $payload = $this->decodeToken($token, $algorithm);

        return (string) ($payload->jti ?? '');
    }

    private function extractToken(Request $request): string
    {
        $header = (string) $request->header('Authorization', '');

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return '';
    }

    private function readHeaderAlgorithm(string $token): string
    {
        $segments = explode('.', $token);

        if (count($segments) < 2) {
            throw new AuthenticationFailedException('Invalid token');
        }

        $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));

        return (string) ($header->alg ?? '');
    }

    private function decodeToken(string $token, string $algorithm): object
    {
        $secret = (string) config('secure-transfer.auth.jwt.secret', '');

        try {
            return JWT::decode($token, new Key($secret, $algorithm));
        } catch (\Throwable $exception) {
            throw new AuthenticationFailedException('Invalid token');
        }
    }
}
