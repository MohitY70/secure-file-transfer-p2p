<?php

namespace HeyGeeks\SecureFileTransfer\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SecureFileStore
{
    protected string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = $storagePath;
        $this->ensureDirectoryExists();
    }

    /**
     * Store a file and return the StoredFile DTO.
     */
    public function store(UploadedFile $file, string $hash, string $clientId): StoredFile
    {
        $id = Str::uuid()->toString();
        $storedName = $id . '.' . $file->getClientOriginalExtension();

        // Store the file
        $file->move($this->storagePath, $storedName);

        // Create metadata
        $storedFile = new StoredFile(
            id: $id,
            originalName: $file->getClientOriginalName(),
            storedName: $storedName,
            mime: finfo_file(finfo_open(FILEINFO_MIME_TYPE), $this->storagePath . '/' . $storedName),
            size: $file->getSize(),
            hash: $hash,
            uploadedAt: now()->toIso8601String(),
            clientId: $clientId,
        );

        // Store metadata as JSON sidecar
        $metadataPath = $this->storagePath . '/' . $id . '.json';
        file_put_contents($metadataPath, json_encode($storedFile->toArray(), JSON_PRETTY_PRINT));

        return $storedFile;
    }

    /**
     * Retrieve file metadata.
     */
    public function retrieve(string $id): StoredFile
    {
        $metadataPath = $this->storagePath . '/' . $id . '.json';

        if (!file_exists($metadataPath)) {
            throw new \RuntimeException("File with ID '{$id}' not found");
        }

        $data = json_decode(file_get_contents($metadataPath), true);
        return StoredFile::fromArray($data);
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $id): bool
    {
        $metadataPath = $this->storagePath . '/' . $id . '.json';
        return file_exists($metadataPath);
    }

    /**
     * Delete a file and its metadata.
     */
    public function delete(string $id): void
    {
        $storedFile = $this->retrieve($id);

        $filePath = $this->storagePath . '/' . $storedFile->storedName;
        $metadataPath = $this->storagePath . '/' . $id . '.json';

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        if (file_exists($metadataPath)) {
            unlink($metadataPath);
        }
    }

    /**
     * Get the full file path.
     */
    public function getPath(string $id): string
    {
        $storedFile = $this->retrieve($id);
        return $this->storagePath . '/' . $storedFile->storedName;
    }

    protected function ensureDirectoryExists(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0750, true);
        }
    }
}
