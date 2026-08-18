<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Database\Models\ContextAwareModel;
use AlexKassel\DomainCore\DTOs\DomainContext;
use AlexKassel\DomainCore\Services\DomainRegistry;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class DummyModel extends ContextAwareModel
{
    protected ?string $domainSlug = 'domain-test';
    protected $table = 'items';
}

class ContextAwareModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        $registry = new DomainRegistry();

        $registry->register(new DomainContext(
            domainSlug: 'domain-test',
            packageSlug: 'alex-kassel/package-test',
            connectionName: 'custom_conn',
            tablePrefix: 'test_prefix'
        ));

        $container->singleton(DomainRegistryInterface::class, fn () => $registry);
        Container::setInstance($container);
    }

    public function test_model_resolves_dynamic_connection_and_table_prefix(): void
    {
        $model = new DummyModel();

        $this->assertSame('custom_conn', $model->getConnectionName());
        $this->assertSame('test_prefix_items', $model->getTable());
    }
}
