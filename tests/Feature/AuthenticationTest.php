<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Heygeeks\SecureFileTransfer\Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('secure-transfer.storage.base_path', storage_path('framework/testing/secure-transfers'));
    }

    public function test_bearer_auth_succeeds_with_valid_token(): void
    {
        config()->set('secure-transfer.auth.strategy', 'bearer');
        config()->set('secure-transfer.auth.bearer.token', 'test-token');

        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $response = $this->post('/secure-transfer/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(201);
    }

    public function test_bearer_auth_fails_with_wrong_token(): void
    {
        config()->set('secure-transfer.auth.strategy', 'bearer');
        config()->set('secure-transfer.auth.bearer.token', 'test-token');

        $response = $this->post('/secure-transfer/status/missing', [], [
            'Authorization' => 'Bearer wrong-token',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['error']);
    }

    public function test_bearer_auth_fails_with_missing_header(): void
    {
        config()->set('secure-transfer.auth.strategy', 'bearer');
        config()->set('secure-transfer.auth.bearer.token', 'test-token');

        $response = $this->post('/secure-transfer/status/missing');

        $response->assertStatus(401);
    }

    public function test_hmac_auth_succeeds_with_valid_signature(): void
    {
        config()->set('secure-transfer.auth.strategy', 'hmac');
        config()->set('secure-transfer.auth.hmac.secret', 'hmac-secret');

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $payload = implode("\n", ['GET', '/secure-transfer/status/missing', $timestamp, $nonce, hash('sha256', '')]);
        $signature = hash_hmac('sha256', $payload, 'hmac-secret');

        $response = $this->get('/secure-transfer/status/missing', [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(404);
    }

    public function test_hmac_auth_fails_with_expired_timestamp(): void
    {
        config()->set('secure-transfer.auth.strategy', 'hmac');
        config()->set('secure-transfer.auth.hmac.secret', 'hmac-secret');

        $timestamp = (string) (time() - 60);
        $nonce = bin2hex(random_bytes(16));
        $payload = implode("\n", ['GET', '/secure-transfer/status/missing', $timestamp, $nonce, hash('sha256', '')]);
        $signature = hash_hmac('sha256', $payload, 'hmac-secret');

        $response = $this->get('/secure-transfer/status/missing', [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(401);
    }

    public function test_hmac_auth_fails_with_replayed_nonce(): void
    {
        config()->set('secure-transfer.auth.strategy', 'hmac');
        config()->set('secure-transfer.auth.hmac.secret', 'hmac-secret');

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $payload = implode("\n", ['GET', '/secure-transfer/status/missing', $timestamp, $nonce, hash('sha256', '')]);
        $signature = hash_hmac('sha256', $payload, 'hmac-secret');

        $headers = [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
        ];

        $this->get('/secure-transfer/status/missing', $headers)->assertStatus(404);
        $this->get('/secure-transfer/status/missing', $headers)->assertStatus(401);
    }

    public function test_jwt_auth_succeeds_with_valid_token(): void
    {
        config()->set('secure-transfer.auth.strategy', 'jwt');
        config()->set('secure-transfer.auth.jwt.secret', 'jwt-secret');
        config()->set('secure-transfer.auth.jwt.issuer', 'file-transfer-client');

        $token = JWT::encode([
            'iss' => 'file-transfer-client',
            'exp' => time() + 3600,
            'jti' => 'token-123',
        ], 'jwt-secret', 'HS256');

        $response = $this->get('/secure-transfer/status/missing', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(404);
    }

    public function test_jwt_auth_fails_with_expired_token(): void
    {
        config()->set('secure-transfer.auth.strategy', 'jwt');
        config()->set('secure-transfer.auth.jwt.secret', 'jwt-secret');
        config()->set('secure-transfer.auth.jwt.issuer', 'file-transfer-client');

        $token = JWT::encode([
            'iss' => 'file-transfer-client',
            'exp' => time() - 3600,
            'jti' => 'token-123',
        ], 'jwt-secret', 'HS256');

        $response = $this->get('/secure-transfer/status/missing', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(401);
    }

    public function test_jwt_auth_fails_with_wrong_algorithm(): void
    {
        config()->set('secure-transfer.auth.strategy', 'jwt');
        config()->set('secure-transfer.auth.jwt.secret', 'jwt-secret');
        config()->set('secure-transfer.auth.jwt.issuer', 'file-transfer-client');
        config()->set('secure-transfer.auth.jwt.algorithm', 'HS256');

        $token = JWT::encode([
            'iss' => 'file-transfer-client',
            'exp' => time() + 3600,
            'jti' => 'token-123',
        ], 'jwt-secret', 'HS384');

        $response = $this->get('/secure-transfer/status/missing', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(401);
    }
}
