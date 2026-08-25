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
        $profile = $this->registry->registerDomain('domain-one', 'Domain One', ['category' => 'leasing']);

        self::assertTrue($this->registry->hasDomain('domain-one'));
        self::assertSame('domain-one', $profile->slug);
        self::assertSame('Domain One', $profile->name);
        self::assertSame(['category' => 'leasing'], $profile->metadata);

        $retrieved = $this->registry->getDomain('domain-one');
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
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_domain_one_primary',
            tablePrefix: 'one_primary_',
            migrationPaths: ['/path/to/migrations'],
        );

        $this->registry->registerStorageContext($context);

        self::assertTrue($this->registry->hasStorageContext('domain-one', 'primary'));
        $retrieved = $this->registry->getStorageContext('domain-one', 'primary');

        self::assertSame('domain-one', $retrieved->domainSlug);
        self::assertSame('primary', $retrieved->contextSlug);
        self::assertSame('sqlite_domain_one_primary', $retrieved->connectionName);
        self::assertSame('one_primary_', $retrieved->tablePrefix);
    }

    public function testThrowsExceptionOnStorageContextCollisionBetweenDifferentDomains(): void
    {
        $context1 = new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'shared_db',
            tablePrefix: 'shared_prefix_',
        );

        $context2 = new StorageContext(
            domainSlug: 'domain-two',
            contextSlug: 'primary',
            connectionName: 'shared_db',
            tablePrefix: 'shared_prefix_', // Exact same connection + prefix collision!
        );

        $this->registry->registerStorageContext($context1);

        $this->expectException(StorageContextCollisionException::class);
        $this->registry->registerStorageContext($context2);
    }

    public function testDeduplicatesAndMergesContextForSameDomainAndContextSlug(): void
    {
        $context1 = new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'db_one',
            tablePrefix: 'one_',
            migrationPaths: ['/path/one'],
        );

        $context2 = new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'db_one',
            tablePrefix: 'one_',
            migrationPaths: ['/path/two'],
        );

        $this->registry->registerStorageContext($context1);
        $this->registry->registerStorageContext($context2);

        $merged = $this->registry->getStorageContext('domain-one', 'primary');
        self::assertEqualsCanonicalizing(['/path/one', '/path/two'], $merged->migrationPaths);
    }

    public function testThrowsExceptionOnInvalidDomainSlug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->registerDomain('invalid slug with spaces', 'Invalid Domain');
    }

    public function testThrowsExceptionOnStorageContextMergeWithDifferentConnection(): void
    {
        $context1 = new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'db_one',
            tablePrefix: 'one_',
        );

        $context2 = new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'db_two', // Different connection under same contextSlug!
            tablePrefix: 'one_',
        );

        $this->registry->registerStorageContext($context1);

        $this->expectException(StorageContextCollisionException::class);
        $this->registry->registerStorageContext($context2);
    }

    public function testCanCompileAndLoadCache(): void
    {
        $this->registry->registerDomain('domain-one', 'Domain One');
        $this->registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'db_one_primary',
            tablePrefix: 'one_primary_',
        ));

        $compiled = $this->registry->compileCache();

        $newRegistry = new DomainRegistry();
        $newRegistry->loadFromCache($compiled);

        self::assertTrue($newRegistry->hasDomain('domain-one'));
        self::assertTrue($newRegistry->hasStorageContext('domain-one', 'primary'));
        self::assertSame('one_primary_', $newRegistry->getStorageContext('domain-one', 'primary')->tablePrefix);
    }
}
