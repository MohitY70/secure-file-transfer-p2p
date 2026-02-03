<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Heygeeks\SecureFileTransfer\Tests\TestCase;

class DownloadFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('secure-transfer.storage.base_path', storage_path('framework/testing/secure-transfers'));
        config()->set('secure-transfer.auth.strategy', 'bearer');
        config()->set('secure-transfer.auth.bearer.token', 'test-token');
        config()->set('secure-transfer.auth.hmac.secret', 'hmac-secret');
    }

    public function test_direct_download_succeeds(): void
    {
        $fileId = $this->uploadFile();

        $response = $this->get('/secure-transfer/download/' . $fileId, [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(200);
    }

    public function test_download_fails_for_nonexistent_id(): void
    {
        $response = $this->get('/secure-transfer/download/' . 'missing-id', [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(404);
    }

    public function test_signed_url_download_succeeds(): void
    {
        $fileId = $this->uploadFile();

        $signed = $this->get('/secure-transfer/request-url/' . $fileId, [
            'Authorization' => 'Bearer test-token',
        ])->assertStatus(200)->json();

        $path = $this->signedUrlPath($signed['url']);
        $this->get($path)->assertStatus(200);
    }

    public function test_signed_url_expires(): void
    {
        $fileId = $this->uploadFile();
        $ipHash = hash('sha256', '127.0.0.1');
        $expires = time() - 10;
        $payload = implode('|', [$fileId, $expires, $ipHash]);
        $signature = hash_hmac('sha256', $payload, 'hmac-secret');

        $path = '/secure-transfer/signed-download/' . $fileId . '?expires=' . $expires . '&ip=' . $ipHash . '&sig=' . $signature;

        $response = $this->get($path);

        $response->assertStatus(401);
        $this->assertStringContainsString('expired', $response->json('error'));
    }

    public function test_signed_url_cannot_be_reused(): void
    {
        $fileId = $this->uploadFile();

        $signed = $this->get('/secure-transfer/request-url/' . $fileId, [
            'Authorization' => 'Bearer test-token',
        ])->assertStatus(200)->json();

        $path = $this->signedUrlPath($signed['url']);

        $this->get($path)->assertStatus(200);
        $this->get($path)->assertStatus(401);
    }

    private function uploadFile(): string
    {
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $response = $this->post('/secure-transfer/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(201);

        return $response->json('file_id');
    }

    private function signedUrlPath(string $url): string
    {
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';

        return $query === '' ? $path : $path . '?' . $query;
    }
}
