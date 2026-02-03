<?php

namespace HeyGeeks\SecureFileTransfer;

use HeyGeeks\SecureFileTransfer\Auth\AuthenticatorInterface;
use HeyGeeks\SecureFileTransfer\Security\NonceStore;
use HeyGeeks\SecureFileTransfer\Security\RateLimiter;
use HeyGeeks\SecureFileTransfer\Storage\SecureFileStore;
use HeyGeeks\SecureFileTransfer\Logging\TransferLogger;

class SecureFileTransfer
{
    public function __construct(
        protected AuthenticatorInterface $authenticator,
        protected NonceStore $nonceStore,
        protected RateLimiter $rateLimiter,
        protected SecureFileStore $fileStore,
        protected TransferLogger $logger,
    ) {
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getNonceStore(): NonceStore
    {
        return $this->nonceStore;
    }

    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }

    public function getFileStore(): SecureFileStore
    {
        return $this->fileStore;
    }

    public function getLogger(): TransferLogger
    {
        return $this->logger;
    }
}
