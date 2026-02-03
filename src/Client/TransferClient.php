<?php

namespace HeyGeeks\SecureFileTransfer\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TransferClient
{
    protected HttpClientInterface $httpClient;
    protected string $baseUrl;
    protected string $authType; // 'bearer', 'hmac', 'jwt'
    protected string $secret;
    protected ?string $clientId;

    public function __construct(
        string $baseUrl,
        string $authType = 'bearer',
        string $secret = '',
        ?string $clientId = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->authType = $authType;
        $this->secret = $secret;
        $this->clientId = $clientId;
        $this->httpClient = HttpClient::create();
    }

    /**
     * Upload a file to the remote server.
     */
    public function upload(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $filename = basename($filePath);
        $headers = $this->buildHeaders('POST', '/secure-transfer/upload');

        $response = $this->httpClient->request('POST', "{$this->baseUrl}/secure-transfer/upload", [
            'headers' => $headers,
            'file' => fopen($filePath, 'r'),
        ]);

        return json_decode($response->getContent(false), true);
    }

    /**
     * Download a file directly (authenticated).
     */
    public function download(string $fileId, string $outputPath): void
    {
        $headers = $this->buildHeaders('GET', "/secure-transfer/download/{$fileId}");

        $response = $this->httpClient->request('GET', "{$this->baseUrl}/secure-transfer/download/{$fileId}", [
            'headers' => $headers,
        ]);

        // Verify hash if provided
        $hash = $response->getHeaders()['x-file-hash'][0] ?? null;

        file_put_contents($outputPath, $response->getContent());

        // Verify file hash
        if ($hash && hash_file('sha256', $outputPath) !== $hash) {
            unlink($outputPath);
            throw new \RuntimeException('Downloaded file hash does not match');
        }
    }

    /**
     * Get a signed URL for temporary download (no auth required for download).
     */
    public function getSignedUrl(string $fileId, int $expiresIn = 3600, bool $ipBound = false): string
    {
        $headers = $this->buildHeaders('POST', "/secure-transfer/request-url/{$fileId}");

        $response = $this->httpClient->request('POST', "{$this->baseUrl}/secure-transfer/request-url/{$fileId}", [
            'headers' => $headers,
            'json' => [
                'expires_in' => $expiresIn,
                'ip_bound' => $ipBound,
            ],
        ]);

        $data = json_decode($response->getContent(false), true);
        return $data['url'] ?? '';
    }

    /**
     * Download via signed URL (no authentication needed).
     */
    public function downloadViaSignedUrl(string $signedUrl, string $outputPath): void
    {
        $response = $this->httpClient->request('GET', $signedUrl);

        file_put_contents($outputPath, $response->getContent());

        // Verify hash if provided
        $hash = $response->getHeaders()['x-file-hash'][0] ?? null;
        if ($hash && hash_file('sha256', $outputPath) !== $hash) {
            unlink($outputPath);
            throw new \RuntimeException('Downloaded file hash does not match');
        }
    }

    /**
     * Get file status/metadata.
     */
    public function getStatus(string $fileId): array
    {
        $headers = $this->buildHeaders('GET', "/secure-transfer/status/{$fileId}");

        $response = $this->httpClient->request('GET', "{$this->baseUrl}/secure-transfer/status/{$fileId}", [
            'headers' => $headers,
        ]);

        return json_decode($response->getContent(false), true);
    }

    protected function buildHeaders(string $method, string $path, ?string $body = null): array
    {
        $headers = [
            'Accept' => 'application/json',
        ];

        match ($this->authType) {
            'bearer' => $headers['Authorization'] = "Bearer {$this->secret}",
            'hmac' => array_merge($headers, $this->buildHmacHeaders($method, $path, $body)),
            'jwt' => $headers['Authorization'] = "Bearer {$this->secret}",
            default => throw new \InvalidArgumentException("Invalid auth type: {$this->authType}"),
        };

        return $headers;
    }

    protected function buildHmacHeaders(string $method, string $path, ?string $body = null): array
    {
        $timestamp = time();
        $clientId = $this->clientId ?? 'client';
        $body = $body ?? '';

        $payload = "{$method}|{$path}|{$body}|{$timestamp}|{$clientId}";
        $signature = hash_hmac('sha256', $payload, $this->secret);

        return [
            'X-Signature' => $signature,
            'X-Timestamp' => (string)$timestamp,
            'X-Client-ID' => $clientId,
        ];
    }
}
