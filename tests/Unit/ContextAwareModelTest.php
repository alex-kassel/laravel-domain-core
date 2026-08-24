<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Database\Models\ContextAwareModel;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Facades\DomainContext;
use AlexKassel\DomainCore\Tests\TestCase;

final class DummyGenericContentModel extends ContextAwareModel
{
    protected $table = 'contents';
}

final class ContextAwareModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $registry = $this->app->make(DomainRegistryInterface::class);

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'scraping',
            connectionName: 'sqlite_car_subscription_raw',
            tablePrefix: 'cs_raw_',
        ));

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'normalization',
            connectionName: 'sqlite_car_subscription_norm',
            tablePrefix: 'cs_norm_',
        ));
    }

    public function testModelResolvesConnectionAndPrefixedTableInActiveContext(): void
    {
        $model = new DummyGenericContentModel();

        // 1. Without context: returns default model table and null connection
        self::assertSame('contents', $model->getTable());

        // 2. In Scraping Context
        DomainContext::using('car-subscription', 'scraping', function () use ($model) {
            self::assertSame('sqlite_car_subscription_raw', $model->getConnectionName());
            self::assertSame('cs_raw_contents', $model->getTable());
        });

        // 3. In Normalization Context
        DomainContext::using('car-subscription', 'normalization', function () use ($model) {
            self::assertSame('sqlite_car_subscription_norm', $model->getConnectionName());
            self::assertSame('cs_norm_contents', $model->getTable());
        });

        // 4. Back to no context
        self::assertSame('contents', $model->getTable());
    }
}
