<?php

namespace HeyGeeks\SecureFileTransfer\Providers;

use Illuminate\Support\ServiceProvider;
use HeyGeeks\SecureFileTransfer\SecureFileTransfer;
use HeyGeeks\SecureFileTransfer\Auth\AuthenticatorInterface;
use HeyGeeks\SecureFileTransfer\Auth\BearerTokenAuthenticator;
use HeyGeeks\SecureFileTransfer\Auth\HmacAuthenticator;
use HeyGeeks\SecureFileTransfer\Auth\JwtAuthenticator;
use HeyGeeks\SecureFileTransfer\Security\NonceStore;
use HeyGeeks\SecureFileTransfer\Security\CacheNonceStore;
use HeyGeeks\SecureFileTransfer\Security\RateLimiter;
use HeyGeeks\SecureFileTransfer\Storage\SecureFileStore;
use HeyGeeks\SecureFileTransfer\Logging\TransferLogger;

class SecureTransferServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../../config/secure-transfer.php' => config_path('secure-transfer.php'),
        ], 'secure-transfer');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }

    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/secure-transfer.php',
            'secure-transfer'
        );

        // Register authenticator based on config
        $this->app->singleton(AuthenticatorInterface::class, function ($app) {
            $strategy = config('secure-transfer.auth.strategy', 'bearer');

            return match ($strategy) {
                'bearer' => new BearerTokenAuthenticator(
                    config('secure-transfer.auth.token', '')
                ),
                'hmac' => new HmacAuthenticator(
                    config('secure-transfer.auth.hmac_secret', '')
                ),
                'jwt' => new JwtAuthenticator(
                    config('secure-transfer.auth.jwt_secret', '')
                ),
                default => throw new \InvalidArgumentException("Invalid auth strategy: {$strategy}"),
            };
        });

        // Register nonce store
        $this->app->singleton(NonceStore::class, CacheNonceStore::class);

        // Register rate limiter
        $this->app->singleton(RateLimiter::class, function ($app) {
            return new RateLimiter(
                config('secure-transfer.security.rate_limit.requests', 60),
                config('secure-transfer.security.rate_limit.window', 60)
            );
        });

        // Register file store
        $this->app->singleton(SecureFileStore::class, function ($app) {
            return new SecureFileStore(
                config('secure-transfer.storage.path')
            );
        });

        // Register logger
        $this->app->singleton(TransferLogger::class, function ($app) {
            return new TransferLogger(
                config('secure-transfer.logging.channel', 'single')
            );
        });

        // Register main service
        $this->app->singleton(SecureFileTransfer::class, function ($app) {
            return new SecureFileTransfer(
                $app->make(AuthenticatorInterface::class),
                $app->make(NonceStore::class),
                $app->make(RateLimiter::class),
                $app->make(SecureFileStore::class),
                $app->make(TransferLogger::class),
            );
        });
    }
}
