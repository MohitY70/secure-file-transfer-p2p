<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Storage;

use DateTimeImmutable;

class StoredFile
{
    public function __construct(
        public readonly string $id,
        public readonly string $originalName,
        public readonly string $storedName,
        public readonly string $mime,
        public readonly int $size,
        public readonly string $hash,
        public readonly string $uploadedAt,
        public readonly string $clientId,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['original_name'],
            $data['stored_name'],
            $data['mime'],
            (int) $data['size'],
            $data['hash'],
            $data['uploaded_at'],
            $data['client_id'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->originalName,
            'stored_name' => $this->storedName,
            'mime' => $this->mime,
            'size' => $this->size,
            'hash' => $this->hash,
            'uploaded_at' => $this->uploadedAt,
            'client_id' => $this->clientId,
        ];
    }
}
