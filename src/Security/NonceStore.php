<?php

namespace HeyGeeks\SecureFileTransfer\Security;

interface NonceStore
{
    /**
     * Check if a nonce exists and is valid.
     */
    public function exists(string $nonce): bool;

    /**
     * Record a nonce as used.
     */
    public function record(string $nonce, int $ttl = 60): void;
}
