<?php

namespace AlexKassel\DomainCore\Tests\Feature;

use AlexKassel\DomainCore\Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_it_boots_service_provider_successfully(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(\AlexKassel\DomainCore\Providers\DomainCoreServiceProvider::class));
    }
}
