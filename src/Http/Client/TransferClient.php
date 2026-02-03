<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Http\Client;

use RuntimeException;
use Heygeeks\SecureFileTransfer\Storage\StoredFile;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TransferClient
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly string $baseUrl,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function upload(string $filePath): StoredFile
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('File not found');
        }

        $endpoint = rtrim($this->baseUrl, '/') . '/secure-transfer/upload';
        $headers = $this->buildAuthHeaders('POST', '/secure-transfer/upload', $filePath);

        $response = $this->httpClient->request('POST', $endpoint, [
            'headers' => $headers,
            'body' => [
                'file' => fopen($filePath, 'r'),
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException((string) $response->getContent(false));
        }

        $data = $response->toArray();

        return StoredFile::fromArray([
            'id' => $data['file_id'],
            'original_name' => basename($filePath),
            'stored_name' => $data['file_id'],
            'mime' => $data['mime'] ?? '',
            'size' => (int) $data['size'],
            'hash' => $data['hash'],
            'uploaded_at' => $data['stored_at'],
            'client_id' => $data['client_id'] ?? 'client',
        ]);
    }

    public function download(string $fileId, string $outputPath): StoredFile
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/secure-transfer/download/' . $fileId;
        $headers = $this->buildAuthHeaders('GET', '/secure-transfer/download/' . $fileId, null);

        $response = $this->httpClient->request('GET', $endpoint, [
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException((string) $response->getContent(false));
        }

        file_put_contents($outputPath, $response->toStream());

        $status = $this->getStatus($fileId);
        $downloadedHash = hash('sha256', (string) file_get_contents($outputPath));

        if ($downloadedHash !== $status->hash) {
            unlink($outputPath);
            throw new RuntimeException('Downloaded file hash mismatch');
        }

        return $status;
    }

    public function downloadViaSignedUrl(string $fileId, string $outputPath): StoredFile
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/secure-transfer/request-url/' . $fileId;
        $headers = $this->buildAuthHeaders('GET', '/secure-transfer/request-url/' . $fileId, null);

        $response = $this->httpClient->request('GET', $endpoint, [
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException((string) $response->getContent(false));
        }

        $payload = $response->toArray();
        $signedUrl = $payload['url'];

        $downloadResponse = $this->httpClient->request('GET', $signedUrl);

        if ($downloadResponse->getStatusCode() >= 400) {
            throw new RuntimeException((string) $downloadResponse->getContent(false));
        }

        file_put_contents($outputPath, $downloadResponse->toStream());

        $status = $this->getStatus($fileId);
        $downloadedHash = hash('sha256', (string) file_get_contents($outputPath));

        if ($downloadedHash !== $status->hash) {
            unlink($outputPath);
            throw new RuntimeException('Downloaded file hash mismatch');
        }

        return $status;
    }

    public function getStatus(string $fileId): StoredFile
    {
        $endpoint = rtrim($this->baseUrl, '/') . '/secure-transfer/status/' . $fileId;
        $headers = $this->buildAuthHeaders('GET', '/secure-transfer/status/' . $fileId, null);

        $response = $this->httpClient->request('GET', $endpoint, [
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException((string) $response->getContent(false));
        }

        $data = $response->toArray();

        return StoredFile::fromArray($data);
    }

    private function buildAuthHeaders(string $method, string $path, ?string $filePath): array
    {
        $strategy = (string) config('secure-transfer.auth.strategy', 'bearer');

        return match ($strategy) {
            'hmac' => $this->buildHmacHeaders($method, $path, $filePath),
            'jwt' => $this->buildJwtHeaders(),
            default => $this->buildBearerHeaders(),
        };
    }

    private function buildBearerHeaders(): array
    {
        $token = (string) config('secure-transfer.auth.bearer.token', '');

        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function buildJwtHeaders(): array
    {
        $token = (string) config('secure-transfer.auth.jwt.secret', '');

        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function buildHmacHeaders(string $method, string $path, ?string $filePath): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $bodyHash = $filePath ? hash('sha256', (string) file_get_contents($filePath)) : hash('sha256', '');
        $payload = implode("\n", [strtoupper($method), $path, $timestamp, $nonce, $bodyHash]);
        $secret = (string) config('secure-transfer.auth.hmac.secret', '');
        $signature = hash_hmac('sha256', $payload, $secret);

        return [
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => $signature,
        ];
    }
}
