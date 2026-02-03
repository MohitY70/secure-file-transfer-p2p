<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Storage;

use Illuminate\Http\UploadedFile;
use Heygeeks\SecureFileTransfer\Exceptions\FileValidationException;

class FileValidator
{
    private int $maxSize;
    private array $allowedMimes;

    public function __construct()
    {
        $this->maxSize = (int) config('secure-transfer.storage.max_file_size_bytes', 104857600);
        $this->allowedMimes = (array) config('secure-transfer.storage.allowed_mimes', []);
    }

    public function validate(UploadedFile $file): void
    {
        $this->checkSize($file);
        $this->checkMimeType($file);
        $this->checkFilename($file);
    }

    public function checkSize(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxSize) {
            throw new FileValidationException("File exceeds maximum size of {$this->maxSize} bytes");
        }
    }

    public function checkMimeType(UploadedFile $file): void
    {
        $mimeType = (string) $file->getMimeType();

        if (!in_array($mimeType, $this->allowedMimes, true)) {
            $allowed = implode(', ', $this->allowedMimes);
            throw new FileValidationException("MIME type {$mimeType} is not allowed. Allowed: {$allowed}");
        }
    }

    public function checkFilename(UploadedFile $file): void
    {
        $original = (string) $file->getClientOriginalName();
        $forbidden = ['../', '..\\', "\0", ';', '|', '`', '$', '(', ')'];

        foreach ($forbidden as $token) {
            if (str_contains($original, $token)) {
                throw new FileValidationException('Filename contains forbidden characters');
            }
        }
    }

    public function computeHash(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());

        return hash('sha256', $contents === false ? '' : $contents);
    }
}
