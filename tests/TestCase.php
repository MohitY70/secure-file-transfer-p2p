<?php

declare(strict_types=1);

namespace Heygeeks\SecureFileTransfer\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Heygeeks\SecureFileTransfer\Providers\SecureFileTransferServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SecureFileTransferServiceProvider::class];
    }
}
