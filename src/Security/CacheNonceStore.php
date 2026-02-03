<?php

namespace HeyGeeks\SecureFileTransfer\Security;

use Illuminate\Support\Facades\Cache;

class CacheNonceStore implements NonceStore
{
    protected string $prefix = 'secure_transfer_nonce:';

    public function exists(string $nonce): bool
    {
        return Cache::has($this->prefix . $nonce);
    }

    public function record(string $nonce, int $ttl = 60): void
    {
        Cache::put($this->prefix . $nonce, true, $ttl);
    }
}
