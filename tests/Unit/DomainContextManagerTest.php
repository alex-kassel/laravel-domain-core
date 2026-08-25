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
use Illuminate\Contracts\Filesystem\Filesystem;

final class DomainContextManagerTest extends TestCase
{
    private DomainRegistryInterface $registry;

    private DomainContextManagerInterface $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new DomainRegistry;
        $provisioner = $this->app->make(DatabaseProvisioner::class);
        $this->manager = new DomainContextManager($this->registry, $provisioner);

        $this->registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_one_primary',
            tablePrefix: 'one_primary_',
            autoCreateSqliteDatabase: true,
        ));

        $this->registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-one',
            contextSlug: 'archive',
            connectionName: 'sqlite_one_archive',
            tablePrefix: 'one_archive_',
            autoCreateSqliteDatabase: true,
        ));

        $this->registry->registerStorageContext(StorageContext::filesystem(
            domainSlug: 'domain-one',
            contextSlug: 'files',
            disk: 'local',
            basePath: 'domain-one/files'
        ));
    }

    public function test_throws_exception_when_accessing_current_without_active_context(): void
    {
        $this->expectException(NoActiveStorageContextException::class);
        $this->manager->current();
    }

    public function test_current_or_null_returns_null_when_no_active_context(): void
    {
        self::assertNull($this->manager->currentOrNull());
        self::assertFalse($this->manager->hasCurrent());
    }

    public function test_using_executes_inside_scope_and_restores_context_on_exit(): void
    {
        self::assertFalse($this->manager->hasCurrent());

        $executed = $this->manager->using('domain-one', 'primary', function (StorageContext $ctx) {
            self::assertTrue($this->manager->hasCurrent());
            self::assertSame('domain-one', $ctx->domainSlug);
            self::assertSame('primary', $ctx->contextSlug);
            self::assertSame('one_primary_', $this->manager->database()->tablePrefix);

            return 'result_123';
        });

        self::assertSame('result_123', $executed);
        self::assertFalse($this->manager->hasCurrent());
    }

    public function test_typed_storage_getters_in_active_scope(): void
    {
        // Database scope
        $this->manager->using('domain-one', 'primary', function () {
            self::assertSame('sqlite_one_primary', $this->manager->database()->connectionName);
        });

        // Filesystem scope
        $this->manager->using('domain-one', 'files', function () {
            self::assertSame('local', $this->manager->filesystem()->disk);
            self::assertSame('domain-one/files', $this->manager->filesystem()->basePath);
            self::assertInstanceOf(Filesystem::class, $this->manager->disk());
        });
    }

    public function test_supports_nested_scopes_with_lifo_restoration(): void
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

    public function test_manual_set_current_and_clear_current(): void
    {
        $ctx = $this->manager->setCurrent('domain-one', 'archive');

        self::assertTrue($this->manager->hasCurrent());
        self::assertSame('archive', $this->manager->current()->contextSlug);

        $this->manager->clearCurrent();
        self::assertFalse($this->manager->hasCurrent());
    }

    public function test_manual_set_current_does_not_break_scoped_using_stack(): void
    {
        $this->manager->using('domain-one', 'primary', function () {
            self::assertSame('primary', $this->manager->current()->contextSlug);

            // Set manual context while in using scope
            $this->manager->setCurrent('domain-one', 'archive');

            // Scoped stack still has precedence
            self::assertSame('primary', $this->manager->current()->contextSlug);
        });

        // After using() exits, the manual context is retained as fallback
        self::assertTrue($this->manager->hasCurrent());
        self::assertSame('archive', $this->manager->current()->contextSlug);

        $this->manager->clearCurrent();
        self::assertFalse($this->manager->hasCurrent());
    }

    public function test_throws_storage_connection_not_found_exception_when_connection_missing_and_auto_create_disabled(): void
    {
        $this->registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-two',
            contextSlug: 'custom-db',
            connectionName: 'non_existent_connection',
            autoCreateSqliteDatabase: false
        ));

        $this->expectException(StorageConnectionNotFoundException::class);
        $this->manager->setCurrent('domain-two', 'custom-db');
    }
}
