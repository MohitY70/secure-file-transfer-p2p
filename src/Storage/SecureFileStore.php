<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class SecureFileStore
{
    private string $basePath;

    private array $mimeMap = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/zip' => 'zip',
    ];

    public function __construct()
    {
        $this->basePath = (string) config('secure-transfer.storage.base_path');

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    public function store(UploadedFile $file, string $hash): StoredFile
    {
        $uuid = (string) Str::uuid();
        $mimeType = (string) $file->getMimeType();
        $extension = $this->mimeMap[$mimeType] ?? 'bin';
        $storedName = $uuid . '.' . $extension;
        $storedPath = $this->basePath . DIRECTORY_SEPARATOR . $storedName;

        $file->move($this->basePath, $storedName);

        $clientId = (string) request()?->attributes->get('tokenId', 'unknown');
        $meta = [
            'id' => $uuid,
            'original_name' => (string) $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'mime' => $mimeType,
            'size' => (int) $file->getSize(),
            'hash' => $hash,
            'uploaded_at' => now()->toIso8601String(),
            'client_id' => $clientId,
        ];

        $this->writeMeta($uuid, $meta);

        return StoredFile::fromArray($meta);
    }

    public function retrieve(string $id): StoredFile
    {
        $metaPath = $this->metaPath($id);

        if (!file_exists($metaPath)) {
            throw new RuntimeException('File not found');
        }

        $data = json_decode((string) file_get_contents($metaPath), true);

        return StoredFile::fromArray($data);
    }

    public function getPath(string $id): string
    {
        $metaPath = $this->metaPath($id);

        if (file_exists($metaPath)) {
            $data = json_decode((string) file_get_contents($metaPath), true);

            return $this->basePath . DIRECTORY_SEPARATOR . $data['stored_name'];
        }

        return $this->basePath . DIRECTORY_SEPARATOR . $id;
    }

    public function delete(string $id): bool
    {
        $metaPath = $this->metaPath($id);
        $filePath = $this->getPath($id);

        $metaDeleted = !file_exists($metaPath) || unlink($metaPath);
        $fileDeleted = !file_exists($filePath) || unlink($filePath);

        return $metaDeleted && $fileDeleted;
    }

    public function exists(string $id): bool
    {
        $metaPath = $this->metaPath($id);
        $filePath = $this->getPath($id);

        return file_exists($metaPath) && file_exists($filePath);
    }

    private function metaPath(string $id): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . $id . '.meta.json';
    }

    private function writeMeta(string $id, array $meta): void
    {
        $path = $this->metaPath($id);
        file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT));
    }
}
