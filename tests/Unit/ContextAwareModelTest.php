<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Database\Models\ContextAwareModel;
use AlexKassel\DomainCore\DTOs\StorageContext;
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

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_domain_one_primary',
            tablePrefix: 'one_primary_',
        ));

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'archive',
            connectionName: 'sqlite_domain_one_archive',
            tablePrefix: 'one_archive_',
        ));
    }

    public function testModelResolvesConnectionAndPrefixedTableInActiveContext(): void
    {
        $model = new DummyGenericItemModel();

        // 1. Without context: returns default model table and null connection
        self::assertSame('items', $model->getTable());

        // 2. In Primary Context
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

    public function testModelWithExplicitContextOverridesAmbientContext(): void
    {
        $explicitModel = new DummyExplicitArchiveModel();

        // Inside 'primary' scope, the explicit archive model resolves 'archive' context
        DomainContext::using('domain-one', 'primary', function () use ($explicitModel) {
            self::assertSame('sqlite_domain_one_archive', $explicitModel->getConnectionName());
        });
    }

    public function testModelWithStaticDomainAndContextResolvesWithoutAmbientScope(): void
    {
        $staticModel = new DummyStaticBoundModel();

        // Resolves without any DomainContext::using scope
        self::assertSame('sqlite_domain_one_primary', $staticModel->getConnectionName());
    }
}
