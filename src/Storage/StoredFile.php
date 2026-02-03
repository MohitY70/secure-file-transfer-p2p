<?php

namespace HeyGeeks\SecureFileTransfer\Storage;

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
            id: $data['id'],
            originalName: $data['original_name'],
            storedName: $data['stored_name'],
            mime: $data['mime'],
            size: $data['size'],
            hash: $data['hash'],
            uploadedAt: $data['uploaded_at'],
            clientId: $data['client_id'],
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
