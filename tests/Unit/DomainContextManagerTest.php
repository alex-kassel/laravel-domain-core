<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainContextManagerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\NoActiveStorageContextException;
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
        $this->manager = new DomainContextManager($this->registry);

        $this->registry->registerStorageContext(new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'scraping',
            connectionName: 'sqlite_cs_raw',
            tablePrefix: 'cs_raw_',
        ));

        $this->registry->registerStorageContext(new StorageContext(
            domainSlug: 'car-subscription',
            capabilitySlug: 'normalization',
            connectionName: 'sqlite_cs_norm',
            tablePrefix: 'cs_norm_',
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

        $executed = $this->manager->using('car-subscription', 'scraping', function (StorageContext $ctx) {
            self::assertTrue($this->manager->hasCurrent());
            self::assertSame('car-subscription', $ctx->domainSlug);
            self::assertSame('scraping', $ctx->capabilitySlug);
            self::assertSame('cs_raw_', $this->manager->current()->tablePrefix);

            return 'result_123';
        });

        self::assertSame('result_123', $executed);
        self::assertFalse($this->manager->hasCurrent());
    }

    public function testSupportsNestedScopesWithLIFORestoration(): void
    {
        $this->manager->using('car-subscription', 'scraping', function () {
            self::assertSame('scraping', $this->manager->current()->capabilitySlug);

            // Nested scope
            $this->manager->using('car-subscription', 'normalization', function () {
                self::assertSame('normalization', $this->manager->current()->capabilitySlug);
            });

            // Restored back to outer scope
            self::assertSame('scraping', $this->manager->current()->capabilitySlug);
        });

        self::assertFalse($this->manager->hasCurrent());
    }

    public function testManualSetCurrentAndClearCurrent(): void
    {
        $ctx = $this->manager->setCurrent('car-subscription', 'normalization');

        self::assertTrue($this->manager->hasCurrent());
        self::assertSame('normalization', $this->manager->current()->capabilitySlug);

        $this->manager->clearCurrent();
        self::assertFalse($this->manager->hasCurrent());
    }
}
