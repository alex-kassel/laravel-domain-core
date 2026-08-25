<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests;

use AlexKassel\DomainCore\Providers\DomainCoreServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DomainCoreServiceProvider::class,
        ];
    }
}
