<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Heygeeks\SecureFileTransfer\Exceptions\ReplayAttackException;
use Heygeeks\SecureFileTransfer\Security\NonceStore;
use Heygeeks\SecureFileTransfer\Tests\TestCase;

class ReplayProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_nonce_store_rejects_duplicate(): void
    {
        $store = new NonceStore();

        $store->record('abc123abcdef4567');

        $this->expectException(ReplayAttackException::class);
        $store->validate('abc123abcdef4567');
    }

    public function test_nonce_store_accepts_unique(): void
    {
        $store = new NonceStore();

        $store->validate('unique-nonce-' . uniqid());

        $this->assertTrue(true);
    }
}
