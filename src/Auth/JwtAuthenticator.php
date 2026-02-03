<?php

namespace HeyGeeks\SecureFileTransfer\Auth;

use Illuminate\Http\Request;
use HeyGeeks\SecureFileTransfer\Exceptions\AuthenticationFailedException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuthenticator implements AuthenticatorInterface
{
    protected string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function authenticate(Request $request): bool
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthenticationFailedException('Missing or invalid JWT token');
        }

        $token = substr($authHeader, 7);

        try {
            // Decode JWT with explicit algorithm verification
            JWT::decode($token, new Key($this->secret, 'HS256'));
            return true;
        } catch (\Exception $e) {
            throw new AuthenticationFailedException('Invalid JWT token: ' . $e->getMessage());
        }
    }

    public function getIdentity(Request $request): string
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader) {
            return 'unknown';
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return $decoded->sub ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    public function getTokenId(Request $request): string
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader) {
            return hash('sha256', 'unknown');
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return hash('sha256', $decoded->jti ?? $token);
        } catch (\Exception $e) {
            return hash('sha256', 'unknown');
        }
    }
}
