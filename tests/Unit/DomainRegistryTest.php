<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\DTOs\DomainContext;
use AlexKassel\DomainCore\Exceptions\DomainNotFoundException;
use AlexKassel\DomainCore\Services\DomainRegistry;
use PHPUnit\Framework\TestCase;

class DomainRegistryTest extends TestCase
{
    public function test_it_registers_and_resolves_domain_context(): void
    {
        $registry = new DomainRegistry();

        $context = new DomainContext(
            domainSlug: 'domain-one',
            packageSlug: 'alex-kassel/package-one',
            connectionName: 'sqlite_one',
            tablePrefix: 'prefix_one'
        );

        $registry->register($context);

        $this->assertTrue($registry->has('domain-one'));
        $resolved = $registry->resolve('domain-one');

        $this->assertSame('domain-one', $resolved->domainSlug);
        $this->assertSame('alex-kassel/package-one', $resolved->packageSlug);
        $this->assertSame('sqlite_one', $resolved->connectionName);
        $this->assertSame('prefix_one', $resolved->tablePrefix);
    }

    public function test_it_auto_derives_slug_from_package_name_when_empty(): void
    {
        $registry = new DomainRegistry();

        $context = new DomainContext(
            domainSlug: '',
            packageSlug: 'alex-kassel/car-subscription-catalog',
            connectionName: 'sqlite',
            tablePrefix: 'car_'
        );

        $registry->register($context);

        $this->assertTrue($registry->has('car-subscription-catalog'));
        $resolved = $registry->resolve('car-subscription-catalog');
        $this->assertSame('car-subscription-catalog', $resolved->domainSlug);
    }

    public function test_it_throws_exception_when_domain_not_found(): void
    {
        $registry = new DomainRegistry();

        $this->expectException(DomainNotFoundException::class);
        $registry->resolve('non-existent');
    }
}
