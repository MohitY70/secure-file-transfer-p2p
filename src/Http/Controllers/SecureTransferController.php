<?php

namespace HeyGeeks\SecureFileTransfer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use HeyGeeks\SecureFileTransfer\SecureFileTransfer;
use HeyGeeks\SecureFileTransfer\Storage\FileValidator;
use HeyGeeks\SecureFileTransfer\Exceptions\FileValidationException;

class SecureTransferController
{
    protected FileValidator $validator;

    public function __construct(
        protected SecureFileTransfer $transfer,
        ?FileValidator $validator = null,
    ) {
        $this->validator = $validator ?? new FileValidator();
    }

    /**
     * Upload a file.
     */
    public function upload(Request $request)
    {
        try {
            $clientId = $request->attributes->get('secure_transfer.client_id');

            if (!$request->hasFile('file')) {
                $this->transfer->getLogger()->uploadFailed('unknown', 'No file provided', $clientId);
                return response()->json(['error' => 'No file provided'], 400);
            }

            $file = $request->file('file');

            // Validate file
            $hash = $this->validator->validate($file);

            // Store file
            $storedFile = $this->transfer->getFileStore()->store($file, $hash, $clientId);

            // Log success
            $this->transfer->getLogger()->uploadSuccess(
                $storedFile->id,
                $storedFile->originalName,
                $storedFile->size,
                $clientId
            );

            return response()->json([
                'id' => $storedFile->id,
                'original_name' => $storedFile->originalName,
                'size' => $storedFile->size,
                'mime' => $storedFile->mime,
                'hash' => $storedFile->hash,
                'uploaded_at' => $storedFile->uploadedAt,
            ], 201);
        } catch (FileValidationException $e) {
            $clientId = $request->attributes->get('secure_transfer.client_id');
            $this->transfer->getLogger()->uploadFailed(
                $request->file('file')?->getClientOriginalName() ?? 'unknown',
                $e->getMessage(),
                $clientId
            );
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            $clientId = $request->attributes->get('secure_transfer.client_id');
            $this->transfer->getLogger()->uploadFailed('unknown', $e->getMessage(), $clientId);
            return response()->json(['error' => 'Upload failed'], 500);
        }
    }

    /**
     * Download a file directly (authenticated).
     */
    public function download(Request $request, string $id)
    {
        try {
            $clientId = $request->attributes->get('secure_transfer.client_id');

            if (!$this->transfer->getFileStore()->exists($id)) {
                $this->transfer->getLogger()->downloadFailed($id, 'File not found', $clientId);
                return response()->json(['error' => 'File not found'], 404);
            }

            $storedFile = $this->transfer->getFileStore()->retrieve($id);
            $path = $this->transfer->getFileStore()->getPath($id);

            $this->transfer->getLogger()->downloadSuccess($id, $clientId);

            return response()->download($path, $storedFile->originalName, [
                'Content-Type' => $storedFile->mime,
                'X-File-Hash' => $storedFile->hash,
            ]);
        } catch (\Exception $e) {
            $clientId = $request->attributes->get('secure_transfer.client_id');
            $this->transfer->getLogger()->downloadFailed($id, $e->getMessage(), $clientId);
            return response()->json(['error' => 'Download failed'], 500);
        }
    }

    /**
     * Request a signed URL for temporary download.
     */
    public function requestSignedUrl(Request $request, string $id)
    {
        try {
            $clientId = $request->attributes->get('secure_transfer.client_id');
            $expiresIn = (int)$request->input('expires_in', 3600); // Default 1 hour
            $ipBound = $request->boolean('ip_bound', false);

            if (!$this->transfer->getFileStore()->exists($id)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            // Generate signed URL token
            $token = Str::random(64);
            $signature = $this->generateSignature($id, $token, $expiresIn, $ipBound ? $request->ip() : null);

            // Store token in cache
            \Illuminate\Support\Facades\Cache::put(
                "secure_transfer_signed_url:{$token}",
                [
                    'file_id' => $id,
                    'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
                    'ip_address' => $ipBound ? $request->ip() : null,
                    'signature' => $signature,
                ],
                $expiresIn
            );

            $this->transfer->getLogger()->signedUrlCreated($id, $clientId);

            $url = route('secure-transfer.signed-download', [
                'token' => $token,
            ]);

            return response()->json([
                'url' => $url,
                'expires_in' => $expiresIn,
                'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
            ], 201);
        } catch (\Exception $e) {
            $clientId = $request->attributes->get('secure_transfer.client_id');
            return response()->json(['error' => 'Failed to create signed URL'], 500);
        }
    }

    /**
     * Download via signed URL (no authentication required).
     */
    public function downloadViaSignedUrl(Request $request, string $token)
    {
        try {
            // Retrieve token data
            $tokenData = \Illuminate\Support\Facades\Cache::get("secure_transfer_signed_url:{$token}");

            if (!$tokenData) {
                $this->transfer->getLogger()->signedUrlExpired($request->input('file_id', 'unknown'));
                return response()->json(['error' => 'Signed URL expired or invalid'], 403);
            }

            // Verify IP binding if set
            if ($tokenData['ip_address'] && $tokenData['ip_address'] !== $request->ip()) {
                return response()->json(['error' => 'IP address does not match'], 403);
            }

            // Verify signature
            $expectedSignature = $this->generateSignature(
                $tokenData['file_id'],
                $token,
                null,
                $tokenData['ip_address']
            );

            if (!hash_equals($tokenData['signature'], $expectedSignature)) {
                return response()->json(['error' => 'Signature verification failed'], 403);
            }

            // Check if already used (single-use)
            \Illuminate\Support\Facades\Cache::forget("secure_transfer_signed_url:{$token}");

            $fileId = $tokenData['file_id'];
            $storedFile = $this->transfer->getFileStore()->retrieve($fileId);
            $path = $this->transfer->getFileStore()->getPath($fileId);

            $this->transfer->getLogger()->signedUrlUsed($fileId, $request->ip());

            return response()->download($path, $storedFile->originalName, [
                'Content-Type' => $storedFile->mime,
                'X-File-Hash' => $storedFile->hash,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Download failed'], 500);
        }
    }

    /**
     * Get file status/metadata.
     */
    public function status(Request $request, string $id)
    {
        try {
            $clientId = $request->attributes->get('secure_transfer.client_id');

            if (!$this->transfer->getFileStore()->exists($id)) {
                return response()->json(['error' => 'File not found'], 404);
            }

            $storedFile = $this->transfer->getFileStore()->retrieve($id);

            return response()->json([
                'id' => $storedFile->id,
                'original_name' => $storedFile->originalName,
                'size' => $storedFile->size,
                'mime' => $storedFile->mime,
                'hash' => $storedFile->hash,
                'uploaded_at' => $storedFile->uploadedAt,
                'uploaded_by' => $storedFile->clientId,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve status'], 500);
        }
    }

    protected function generateSignature(?string $fileId, string $token, ?int $expiresIn, ?string $ipAddress): string
    {
        $payload = "{$fileId}|{$token}";
        if ($expiresIn) {
            $payload .= "|{$expiresIn}";
        }
        if ($ipAddress) {
            $payload .= "|{$ipAddress}";
        }

        return hash_hmac('sha256', $payload, config('secure-transfer.signing_secret', 'default'));
    }
}
