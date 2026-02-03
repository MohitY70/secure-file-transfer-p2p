<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Heygeeks\SecureFileTransfer\Tests\TestCase;

class UploadFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('secure-transfer.storage.base_path', storage_path('framework/testing/secure-transfers'));
        config()->set('secure-transfer.auth.strategy', 'bearer');
        config()->set('secure-transfer.auth.bearer.token', 'test-token');
    }

    public function test_upload_succeeds_with_valid_file(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $response = $this->post('/secure-transfer/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['file_id', 'hash', 'size']);
    }

    public function test_upload_fails_when_file_exceeds_max_size(): void
    {
        config()->set('secure-transfer.storage.max_file_size_bytes', 1);
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $response = $this->post('/secure-transfer/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('File exceeds maximum size', $response->json('error'));
    }

    public function test_upload_fails_with_disallowed_mime_type(): void
    {
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload');

        $response = $this->post('/secure-transfer/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('MIME type', $response->json('error'));
    }

    public function test_upload_fails_with_no_file(): void
    {
        $response = $this->post('/secure-transfer/upload', [], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(400);
    }

    public function test_stored_file_is_outside_web_root(): void
    {
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');

        $response = $this->post('/secure-transfer/upload', ['file' => $file], [
            'Authorization' => 'Bearer test-token',
        ]);

        $response->assertStatus(201);
        $fileId = $response->json('file_id');

        $basePath = config('secure-transfer.storage.base_path');
        $metaPath = $basePath . DIRECTORY_SEPARATOR . $fileId . '.meta.json';

        $this->assertFileExists($metaPath);
        $this->assertStringStartsWith(storage_path(), $basePath);
        $this->assertStringNotContainsString(public_path(), $basePath);
    }
}
