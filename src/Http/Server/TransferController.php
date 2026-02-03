<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Http\Server;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Heygeeks\SecureFileTransfer\Exceptions\FileValidationException;
use Heygeeks\SecureFileTransfer\Logging\TransferLogger;
use Heygeeks\SecureFileTransfer\Security\SignedUrlGenerator;
use Heygeeks\SecureFileTransfer\Storage\FileValidator;
use Heygeeks\SecureFileTransfer\Storage\SecureFileStore;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransferController
{
    public function __construct(
        private readonly FileValidator $validator,
        private readonly SecureFileStore $store,
        private readonly TransferLogger $logger,
        private readonly SignedUrlGenerator $signedUrlGenerator,
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        $file = $request->file('file');

        if ($file === null) {
            return response()->json(['error' => 'No file provided'], 400);
        }

        try {
            $this->validator->validate($file);
        } catch (FileValidationException $exception) {
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        $hash = $this->validator->computeHash($file);
        $stored = $this->store->store($file, $hash);

        $tokenId = (string) $request->attributes->get('tokenId', 'unknown');
        $this->logger->logUpload($tokenId, $stored->id, $stored->hash, (string) $request->ip());

        return response()->json([
            'status' => 'success',
            'file_id' => $stored->id,
            'hash' => $stored->hash,
            'size' => $stored->size,
            'stored_at' => $stored->uploadedAt,
        ], 201);
    }

    public function download(Request $request, string $id): StreamedResponse|JsonResponse
    {
        if (!$this->store->exists($id)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $file = $this->store->retrieve($id);
        $tokenId = (string) $request->attributes->get('tokenId', 'unknown');
        $this->logger->logDownload($tokenId, $file->id, (string) $request->ip());

        return response()->stream(function () use ($id): void {
            readfile($this->store->getPath($id));
        }, 200, [
            'Content-Type' => $file->mime,
            'Content-Disposition' => 'attachment; filename="' . $file->originalName . '"',
            'Content-Length' => (string) $file->size,
        ]);
    }

    public function requestSignedUrl(Request $request, string $id): JsonResponse
    {
        if (!$this->store->exists($id)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $signed = $this->signedUrlGenerator->generate($id, (string) $request->ip());

        $this->logger->log(TransferLogger::SIGNED_URL_CREATED, [
            'fileId' => $id,
            'ip' => $request->ip(),
            'status' => 'success',
        ]);

        return response()->json([
            'url' => $signed['url'],
            'expires_at' => $signed['expires_at'],
        ], 200);
    }

    public function status(Request $request, string $id): JsonResponse
    {
        if (!$this->store->exists($id)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $file = $this->store->retrieve($id);

        return response()->json($file->toArray(), 200);
    }
}
