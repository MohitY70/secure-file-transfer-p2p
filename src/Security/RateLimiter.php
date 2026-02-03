<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Security;

use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Heygeeks\SecureFileTransfer\Exceptions\TransferLimitExceededException;

class RateLimiter
{
    public function hit(string $tokenId): void
    {
        $key = $this->key($tokenId);
        $maxAttempts = (int) config('secure-transfer.security.rate_limit.max_requests_per_minute', 60);

        if (!RateLimiterFacade::attempt($key, $maxAttempts, function (): void {
        }, 60)) {
            throw new TransferLimitExceededException('Rate limit exceeded');
        }
    }

    public function isAllowed(string $tokenId): bool
    {
        $key = $this->key($tokenId);
        $maxAttempts = (int) config('secure-transfer.security.rate_limit.max_requests_per_minute', 60);

        return !RateLimiterFacade::tooManyAttempts($key, $maxAttempts);
    }

    private function key(string $tokenId): string
    {
        return "secure_transfer:{$tokenId}";
    }
}
