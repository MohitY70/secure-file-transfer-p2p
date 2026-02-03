<?php

namespace HeyGeeks\SecureFileTransfer\Storage;

use Illuminate\Http\UploadedFile;
use HeyGeeks\SecureFileTransfer\Exceptions\FileValidationException;

class FileValidator
{
    protected int $maxSize; // in bytes
    protected array $allowedMimes;

    public function __construct(int $maxSize = 104857600, array $allowedMimes = [])
    {
        $this->maxSize = $maxSize;
        // Default MIME whitelist
        $this->allowedMimes = $allowedMimes ?: [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain',
            'text/csv',
            'application/json',
            'application/zip',
            'application/x-7z-compressed',
            'application/gzip',
        ];
    }

    /**
     * Validate a file and return its hash.
     */
    public function validate(UploadedFile $file): string
    {
        $this->checkSize($file);
        $this->checkMimeType($file);
        $this->checkFilename($file);

        return $this->computeHash($file);
    }

    protected function checkSize(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxSize) {
            throw new FileValidationException(
                "File size exceeds maximum allowed size of {$this->maxSize} bytes"
            );
        }
    }

    protected function checkMimeType(UploadedFile $file): void
    {
        // Get real MIME type from magic bytes
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        if (!in_array($realMime, $this->allowedMimes, true)) {
            throw new FileValidationException(
                "File MIME type '{$realMime}' is not allowed"
            );
        }
    }

    protected function checkFilename(UploadedFile $file): void
    {
        $filename = $file->getClientOriginalName();

        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            throw new FileValidationException('Filename contains null bytes');
        }

        // Check for path traversal
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
            throw new FileValidationException('Filename contains path traversal characters');
        }

        // Check for shell metacharacters
        if (preg_match('/[<>"|&;`$()\\\\]/', $filename)) {
            throw new FileValidationException('Filename contains invalid shell characters');
        }
    }

    protected function computeHash(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }
}
