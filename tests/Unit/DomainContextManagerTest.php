<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainContextManagerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\NoActiveStorageContextException;
use AlexKassel\DomainCore\Exceptions\StorageConnectionNotFoundException;
use AlexKassel\DomainCore\Services\DatabaseProvisioner;
use AlexKassel\DomainCore\Services\DomainContextManager;
use AlexKassel\DomainCore\Services\DomainRegistry;
use AlexKassel\DomainCore\Tests\TestCase;

final class DomainContextManagerTest extends TestCase
{
    private DomainRegistryInterface $registry;
    private DomainContextManagerInterface $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DomainRegistry();
        $provisioner = $this->app->make(DatabaseProvisioner::class);
        $this->manager = new DomainContextManager($this->registry, $provisioner);

        $this->registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_one_primary',
            tablePrefix: 'one_primary_',
        ));

        $this->registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'archive',
            connectionName: 'sqlite_one_archive',
            tablePrefix: 'one_archive_',
        ));
    }

    public function testThrowsExceptionWhenAccessingCurrentWithoutActiveContext(): void
    {
        $this->expectException(NoActiveStorageContextException::class);
        $this->manager->current();
    }

    public function testCurrentOrNullReturnsNullWhenNoActiveContext(): void
    {
        self::assertNull($this->manager->currentOrNull());
        self::assertFalse($this->manager->hasCurrent());
    }

    public function testUsingExecutesInsideScopeAndRestoresContextOnExit(): void
    {
        self::assertFalse($this->manager->hasCurrent());

        $executed = $this->manager->using('domain-one', 'primary', function (StorageContext $ctx) {
            self::assertTrue($this->manager->hasCurrent());
            self::assertSame('domain-one', $ctx->domainSlug);
            self::assertSame('primary', $ctx->contextSlug);
            self::assertSame('one_primary_', $this->manager->current()->tablePrefix);

            return 'result_123';
        });

        self::assertSame('result_123', $executed);
        self::assertFalse($this->manager->hasCurrent());
    }

    public function testSupportsNestedScopesWithLIFORestoration(): void
    {
        $this->manager->using('domain-one', 'primary', function () {
            self::assertSame('primary', $this->manager->current()->contextSlug);

            // Nested scope
            $this->manager->using('domain-one', 'archive', function () {
                self::assertSame('archive', $this->manager->current()->contextSlug);
            });

            // Restored back to outer scope
            self::assertSame('primary', $this->manager->current()->contextSlug);
        });

        self::assertFalse($this->manager->hasCurrent());
    }

    public function testManualSetCurrentAndClearCurrent(): void
    {
        $ctx = $this->manager->setCurrent('domain-one', 'archive');

        self::assertTrue($this->manager->hasCurrent());
        self::assertSame('archive', $this->manager->current()->contextSlug);

        $this->manager->clearCurrent();
        self::assertFalse($this->manager->hasCurrent());
    }

    public function testThrowsStorageConnectionNotFoundExceptionWhenConnectionMissingAndAutoCreateDisabled(): void
    {
        $this->registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-two',
            contextSlug: 'custom-db',
            connectionName: 'non_existent_connection',
            autoCreateSqliteDatabase: false
        ));

        $this->expectException(StorageConnectionNotFoundException::class);
        $this->manager->setCurrent('domain-two', 'custom-db');
    }
}
