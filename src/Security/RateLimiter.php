<?php

namespace HeyGeeks\SecureFileTransfer\Security;

use Illuminate\Support\Facades\Cache;
use HeyGeeks\SecureFileTransfer\Exceptions\TransferLimitExceededException;

class RateLimiter
{
    protected int $limit;
    protected int $window; // in seconds

    public function __construct(int $limit = 60, int $window = 60)
    {
        $this->limit = $limit;
        $this->window = $window;
    }

    /**
     * Check if the token has exceeded the rate limit.
     */
    public function isAllowed(string $tokenId): bool
    {
        $key = "secure_transfer:{$tokenId}";
        $count = Cache::get($key, 0);

        if ($count >= $this->limit) {
            throw new TransferLimitExceededException(
                "Rate limit exceeded. Maximum {$this->limit} requests per {$this->window}s"
            );
        }

        // Increment counter
        if ($count === 0) {
            Cache::put($key, 1, $this->window);
        } else {
            Cache::increment($key);
        }

        return true;
    }

    /**
     * Get the current count for a token.
     */
    public function getCount(string $tokenId): int
    {
        $key = "secure_transfer:{$tokenId}";
        return Cache::get($key, 0);
    }
}
