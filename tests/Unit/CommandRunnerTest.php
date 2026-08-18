<?php

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainContext;
use AlexKassel\DomainCore\Events\CommandRunSkippedDueToOverlap;
use AlexKassel\DomainCore\Exceptions\DomainResolutionException;
use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class CommandRunnerTest extends TestCase
{
    protected DomainRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(DomainRegistryInterface::class);

        $this->registry->register(new DomainContext(
            domainSlug: 'domain-one',
            packageSlug: 'alex-kassel/domain-one',
            connectionName: 'domain_one',
            tablePrefix: 'd1_',
        ));

        $this->registry->register(new DomainContext(
            domainSlug: 'domain-two',
            packageSlug: 'alex-kassel/domain-two',
            connectionName: 'domain_two',
            tablePrefix: 'd2_',
        ));
    }

    public function test_it_parses_cli_options(): void
    {
        $runner = $this->app->make(CommandRunnerInterface::class);
        $options = $runner->parseCliOptions([
            'all' => false,
            'domains' => 'domain-one, domain-two',
            'except-domains' => 'domain-two',
            'force' => true,
            'dry-run' => false,
        ]);

        $this->assertInstanceOf(CommandOptionsDTO::class, $options);
        $this->assertFalse($options->all);
        $this->assertEquals(['domain-one', 'domain-two'], $options->domains);
        $this->assertEquals(['domain-two'], $options->exceptDomains);
        $this->assertTrue($options->force);
        $this->assertFalse($options->dryRun);
    }

    public function test_it_resolves_target_domains(): void
    {
        $runner = $this->app->make(CommandRunnerInterface::class);
        $options = $runner->parseCliOptions(['domains' => 'domain-one']);
        $resolved = $runner->resolveTargetDomains($options);

        $this->assertCount(1, $resolved);
        $this->assertArrayHasKey('domain-one', $resolved);
    }

    public function test_it_throws_exception_on_unregistered_domain(): void
    {
        $this->expectException(DomainResolutionException::class);

        $runner = $this->app->make(CommandRunnerInterface::class);
        $options = $runner->parseCliOptions(['domains' => 'unknown-domain']);
        $runner->resolveTargetDomains($options);
    }

    public function test_it_executes_domain_callback_with_lock(): void
    {
        $runner = $this->app->make(CommandRunnerInterface::class);
        $domain = $this->registry->resolve('domain-one');
        $options = new CommandOptionsDTO();

        $report = $runner->executeDomain(
            domain: $domain,
            componentKey: 'spider',
            callback: function ($ctx, $opts) {
                return 15;
            },
            options: $options
        );

        $this->assertEquals('SUCCESS', $report->status);
        $this->assertEquals('domain-one', $report->domainSlug);
        $this->assertEquals(15, $report->executedItemsCount);
    }

    public function test_it_handles_overlap_gracefully_and_dispatches_event(): void
    {
        Event::fake([CommandRunSkippedDueToOverlap::class]);

        $runner = $this->app->make(CommandRunnerInterface::class);
        $lockManager = $this->app->make(ExecutionLockManagerInterface::class);
        $lockManager->acquire('domain-one', 'spider');

        $domain = $this->registry->resolve('domain-one');
        $options = new CommandOptionsDTO();

        $report = $runner->executeDomain(
            domain: $domain,
            componentKey: 'spider',
            callback: function ($ctx, $opts) {
                return 10;
            },
            options: $options
        );

        $this->assertEquals('SKIPPED', $report->status);
        $this->assertEquals(0, $report->executedItemsCount);

        Event::assertDispatched(CommandRunSkippedDueToOverlap::class, function ($event) {
            return $event->domainSlug === 'domain-one' && $event->componentKey === 'spider';
        });
    }
}
