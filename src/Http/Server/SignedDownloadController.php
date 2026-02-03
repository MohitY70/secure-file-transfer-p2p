<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Http\Server;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Heygeeks\SecureFileTransfer\Logging\TransferLogger;
use Heygeeks\SecureFileTransfer\Storage\SecureFileStore;

class SignedDownloadController
{
    public function __construct(
        private readonly SecureFileStore $store,
        private readonly TransferLogger $logger,
    ) {
    }

    public function download(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $expires = $request->query('expires');
        $ipParam = $request->query('ip');
        $signature = $request->query('sig');

        if ($expires === null || $ipParam === null || $signature === null) {
            return response()->json(['error' => 'Invalid signed URL'], 401);
        }

        if (time() > (int) $expires) {
            $this->logger->log(TransferLogger::SIGNED_URL_EXPIRED, [
                'fileId' => $id,
                'ip' => $request->ip(),
                'status' => 'failed',
            ]);

            return response()->json(['error' => 'URL expired'], 401);
        }

        if (Cache::has($this->usedKey($signature))) {
            return response()->json(['error' => 'Invalid signed URL'], 401);
        }

        $bindIp = (bool) config('secure-transfer.security.signed_urls.allowed_ips', true);
        $clientIp = (string) $request->ip();
        $ipHash = $bindIp ? hash('sha256', $clientIp) : '';

        if ($bindIp && $ipHash !== (string) $ipParam) {
            return response()->json(['error' => 'IP mismatch'], 401);
        }

        $payload = implode('|', [$id, $expires, $ipHash]);
        $secret = (string) config('secure-transfer.auth.hmac.secret', '');
        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, (string) $signature)) {
            return response()->json(['error' => 'Invalid signed URL'], 401);
        }

        if (!$this->store->exists($id)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        Cache::put($this->usedKey($signature), true, max(1, (int) $expires - time()));

        $file = $this->store->retrieve($id);

        $this->logger->log(TransferLogger::SIGNED_URL_USED, [
            'fileId' => $id,
            'ip' => $clientIp,
            'status' => 'success',
        ]);

        return response()->stream(function () use ($id): void {
            readfile($this->store->getPath($id));
        }, 200, [
            'Content-Type' => $file->mime,
            'Content-Disposition' => 'attachment; filename="' . $file->originalName . '"',
            'Content-Length' => (string) $file->size,
        ]);
    }

    private function usedKey(string $signature): string
    {
        return "used_signed_url:{$signature}";
    }
}
