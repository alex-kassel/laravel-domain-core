<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Database\Models\ContextAwareModel;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\IncompatibleStorageException;
use AlexKassel\DomainCore\Exceptions\NoActiveStorageContextException;
use AlexKassel\DomainCore\Facades\DomainContext;
use AlexKassel\DomainCore\Tests\TestCase;

final class DummyGenericItemModel extends ContextAwareModel
{
    protected $table = 'items';
}

final class DummyExplicitArchiveModel extends ContextAwareModel
{
    protected ?string $explicitContext = 'archive';

    protected $table = 'archives';
}

final class DummyStaticBoundModel extends ContextAwareModel
{
    protected ?string $explicitDomain = 'domain-one';

    protected ?string $explicitContext = 'primary';

    protected $table = 'static_items';
}

final class ContextAwareModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $registry = $this->app->make(DomainRegistryInterface::class);

        $registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_domain_one_primary',
            tablePrefix: 'one_primary_',
            autoCreateSqliteDatabase: true,
        ));

        $registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-one',
            contextSlug: 'archive',
            connectionName: 'sqlite_domain_one_archive',
            tablePrefix: 'one_archive_',
            autoCreateSqliteDatabase: true,
        ));

        $registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-two',
            contextSlug: 'primary',
            connectionName: 'sqlite_domain_two_primary',
            tablePrefix: 'two_primary_',
            autoCreateSqliteDatabase: true,
        ));

        $registry->registerStorageContext(StorageContext::filesystem(
            domainSlug: 'domain-one',
            contextSlug: 'files',
            disk: 'local'
        ));
    }

    public function test_model_resolves_connection_and_prefixed_table_in_active_context(): void
    {
        $model = new DummyGenericItemModel;

        // 1. Without context: returns base table, but throws on getConnectionName
        self::assertSame('items', $model->getTable());

        // 2. In Primary Context (Connection has prefix set in database config, so getTable returns 'items' and Connection handles prefix)
        DomainContext::using('domain-one', 'primary', function () use ($model) {
            self::assertSame('sqlite_domain_one_primary', $model->getConnectionName());
            self::assertSame('items', $model->getTable());
        });

        // 3. In Archive Context
        DomainContext::using('domain-one', 'archive', function () use ($model) {
            self::assertSame('sqlite_domain_one_archive', $model->getConnectionName());
            self::assertSame('items', $model->getTable());
        });

        // 4. Back to no context
        self::assertSame('items', $model->getTable());
    }

    public function test_model_prefixes_table_when_connection_prefix_is_empty(): void
    {
        // Configure connection WITHOUT prefix in config
        config([
            'database.connections.sqlite_no_conn_prefix' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        $registry = $this->app->make(DomainRegistryInterface::class);
        $registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-one',
            contextSlug: 'no-conn-prefix',
            connectionName: 'sqlite_no_conn_prefix',
            tablePrefix: 'dynamic_prefix_',
        ));

        $model = new DummyGenericItemModel;

        DomainContext::using('domain-one', 'no-conn-prefix', function () use ($model) {
            self::assertSame('dynamic_prefix_items', $model->getTable());
        });
    }

    public function test_model_throws_no_active_storage_context_exception_outside_scope_when_strict(): void
    {
        $model = new DummyGenericItemModel;

        $this->expectException(NoActiveStorageContextException::class);
        $model->getConnectionName();
    }

    public function test_model_throws_incompatible_storage_exception_in_filesystem_scope(): void
    {
        $model = new DummyGenericItemModel;

        $this->expectException(IncompatibleStorageException::class);
        DomainContext::using('domain-one', 'files', function () use ($model) {
            $model->getConnectionName();
        });
    }

    public function test_model_with_explicit_context_overrides_ambient_context(): void
    {
        $explicitModel = new DummyExplicitArchiveModel;

        // Inside 'primary' scope, the explicit archive model resolves 'archive' context
        DomainContext::using('domain-one', 'primary', function () use ($explicitModel) {
            self::assertSame('sqlite_domain_one_archive', $explicitModel->getConnectionName());
            self::assertSame('one_archive_archives', $explicitModel->getTable());
        });
    }

    public function test_model_with_static_domain_and_context_resolves_without_ambient_scope(): void
    {
        $staticModel = new DummyStaticBoundModel;

        // Resolves without any DomainContext::using scope
        self::assertSame('sqlite_domain_one_primary', $staticModel->getConnectionName());
        self::assertSame('one_primary_static_items', $staticModel->getTable());
    }

    public function test_static_domain_binding_prevents_context_hijacking_in_ambient_scope(): void
    {
        $staticModel = new DummyStaticBoundModel; // explicitDomain = 'domain-one', explicitContext = 'primary'

        // Executing inside domain-two scope MUST NOT hijack domain-one model
        DomainContext::using('domain-two', 'primary', function () use ($staticModel) {
            self::assertSame('sqlite_domain_one_primary', $staticModel->getConnectionName());
            self::assertSame('one_primary_static_items', $staticModel->getTable());
        });
    }
}
