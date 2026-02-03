<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Heygeeks\SecureFileTransfer\Http\Middleware\SecureTransferAuthMiddleware;

class SecureFileTransferServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/secure-transfer.php', 'secure-transfer');
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../Config/secure-transfer.php' => config_path('secure-transfer.php'),
        ], 'config');

        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        $router->aliasMiddleware('auth.secure-transfer', SecureTransferAuthMiddleware::class);
    }
}
