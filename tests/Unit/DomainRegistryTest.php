<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\DomainNotFoundException;
use AlexKassel\DomainCore\Exceptions\StorageContextCollisionException;
use AlexKassel\DomainCore\Exceptions\StorageContextNotFoundException;
use AlexKassel\DomainCore\Services\DomainRegistry;
use AlexKassel\DomainCore\Tests\TestCase;

final class DomainRegistryTest extends TestCase
{
    private DomainRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DomainRegistry();
    }

    public function testCanRegisterAndRetrieveDomainProfile(): void
    {
        $profile = $this->registry->registerDomain('car-subscription', 'Car Subscription', ['category' => 'automotive']);

        self::assertTrue($this->registry->hasDomain('car-subscription'));
        self::assertSame('car-subscription', $profile->slug);
        self::assertSame('Car Subscription', $profile->name);
        self::assertSame(['category' => 'automotive'], $profile->metadata);

        $retrieved = $this->registry->getDomain('car-subscription');
        self::assertSame($profile, $retrieved);
    }

    public function testThrowsExceptionWhenResolvingUnregisteredDomain(): void
    {
        $this->expectException(DomainNotFoundException::class);
        $this->registry->getDomain('unknown-domain');
    }

    public function testCanRegisterAndRetrieveStorageContext(): void
    {
        $context = new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'scraping',
            connectionName: 'sqlite_car_subscription_raw',
            tablePrefix: 'cs_raw_',
            migrationPaths: ['/path/to/migrations'],
        );

        $this->registry->registerStorageContext($context);

        self::assertTrue($this->registry->hasStorageContext('car-subscription', 'scraping'));
        $retrieved = $this->registry->getStorageContext('car-subscription', 'scraping');

        self::assertSame('car-subscription', $retrieved->domainSlug);
        self::assertSame('scraping', $retrieved->capabilitySlug);
        self::assertSame('sqlite_car_subscription_raw', $retrieved->connectionName);
        self::assertSame('cs_raw_', $retrieved->tablePrefix);
    }

    public function testThrowsExceptionOnStorageContextCollisionBetweenDifferentDomains(): void
    {
        $context1 = new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'scraping',
            connectionName: 'shared_db',
            tablePrefix: 'shared_prefix_',
        );

        $context2 = new StorageContext(
            domainSlug: 'real-estate',
            capabilitySlug: 'scraping',
            connectionName: 'shared_db',
            tablePrefix: 'shared_prefix_', // Exact same connection + prefix collision!
        );

        $this->registry->registerStorageContext($context1);

        $this->expectException(StorageContextCollisionException::class);
        $this->registry->registerStorageContext($context2);
    }

    public function testDeduplicatesAndMergesContextForSameDomainAndCapability(): void
    {
        $context1 = new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'scraping',
            connectionName: 'db_cs',
            tablePrefix: 'cs_',
            migrationPaths: ['/path/one'],
        );

        $context2 = new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'scraping',
            connectionName: 'db_cs',
            tablePrefix: 'cs_',
            migrationPaths: ['/path/two'],
        );

        $this->registry->registerStorageContext($context1);
        $this->registry->registerStorageContext($context2);

        $merged = $this->registry->getStorageContext('car-subscription', 'scraping');
        self::assertEqualsCanonicalizing(['/path/one', '/path/two'], $merged->migrationPaths);
    }

    public function testCanCompileAndLoadCache(): void
    {
        $this->registry->registerDomain('car-subscription', 'Car Subscription');
        $this->registry->registerStorageContext(new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'scraping',
            connectionName: 'db_raw',
            tablePrefix: 'cs_raw_',
        ));

        $compiled = $this->registry->compileCache();

        $newRegistry = new DomainRegistry();
        $newRegistry->loadFromCache($compiled);

        self::assertTrue($newRegistry->hasDomain('car-subscription'));
        self::assertTrue($newRegistry->hasStorageContext('car-subscription', 'scraping'));
        self::assertSame('cs_raw_', $newRegistry->getStorageContext('car-subscription', 'scraping')->tablePrefix);
    }
}
