<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Security;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Heygeeks\SecureFileTransfer\Exceptions\ReplayAttackException;

class NonceStore
{
    public function isUsed(string $nonce): bool
    {
        return Cache::has($this->cacheKey($nonce));
    }

    public function record(string $nonce): void
    {
        $ttl = (int) config('secure-transfer.security.replay_protection.nonce_ttl_seconds', 60);

        Cache::put($this->cacheKey($nonce), true, $ttl);
    }

    public function validate(string $nonce): void
    {
        if ($nonce === '' || strlen($nonce) < 16) {
            throw new InvalidArgumentException('Nonce must be at least 16 characters.');
        }

        if ($this->isUsed($nonce)) {
            throw new ReplayAttackException('Nonce already used');
        }
    }

    private function cacheKey(string $nonce): string
    {
        return "secure_transfer_nonce:{$nonce}";
    }
}
