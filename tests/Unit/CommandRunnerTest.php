<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainProfile;
use AlexKassel\DomainCore\Enums\ExecutionStatus;
use AlexKassel\DomainCore\Events\CommandExecutionFailed;
use AlexKassel\DomainCore\Exceptions\DomainNotFoundException;
use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class CommandRunnerTest extends TestCase
{
    private CommandRunnerInterface $runner;

    private DomainRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->runner = $this->app->make(CommandRunnerInterface::class);
        $this->registry = $this->app->make(DomainRegistryInterface::class);

        $this->registry->registerDomain('domain-a', 'Domain A');
        $this->registry->registerDomain('domain-b', 'Domain B');
        $this->registry->registerDomain('domain-c', 'Domain C');
    }

    public function test_parses_cli_options(): void
    {
        $options = $this->runner->parseCliOptions([
            'all' => true,
            'domains' => 'domain-a,domain-b',
            'except-domains' => 'domain-b',
            'context' => 'primary',
            'force' => true,
            'dry-run' => false,
        ]);

        self::assertTrue($options->all);
        self::assertEqualsCanonicalizing(['domain-a', 'domain-b'], $options->domains);
        self::assertEqualsCanonicalizing(['domain-b'], $options->exceptDomains);
        self::assertSame('primary', $options->context);
        self::assertTrue($options->force);
        self::assertFalse($options->dryRun);
    }

    public function test_resolves_target_domains(): void
    {
        $options = new CommandOptionsDTO(
            all: false,
            domains: ['domain-a', 'domain-b'],
            exceptDomains: ['domain-b']
        );

        $targets = $this->runner->resolveTargetDomains($options);

        self::assertCount(1, $targets);
        self::assertSame('domain-a', $targets[0]->slug);
    }

    public function test_throws_domain_not_found_exception_when_target_domain_not_registered(): void
    {
        $options = new CommandOptionsDTO(
            all: false,
            domains: ['domain-a', 'unknown-domain-x']
        );

        $this->expectException(DomainNotFoundException::class);
        $this->runner->resolveTargetDomains($options);
    }

    public function test_executes_domain_with_report(): void
    {
        $domain = $this->registry->getDomain('domain-a');
        $options = new CommandOptionsDTO;

        $report = $this->runner->executeDomain($domain, 'runner-one', function (DomainProfile $d) {
            return 42;
        }, $options);

        self::assertTrue($report->isSuccess());
        self::assertSame(ExecutionStatus::SUCCESS, $report->status);
        self::assertSame('domain-a', $report->domainSlug);
        self::assertSame('runner-one', $report->componentKey);
        self::assertSame(42, $report->itemsProcessed);
    }

    public function test_dispatches_command_execution_failed_event_on_failure(): void
    {
        Event::fake([CommandExecutionFailed::class]);
        $this->app->forgetInstance(CommandRunnerInterface::class);
        $runner = $this->app->make(CommandRunnerInterface::class);

        $domain = $this->registry->getDomain('domain-a');
        $options = new CommandOptionsDTO;

        $report = $runner->executeDomain($domain, 'failing-task', function () {
            throw new RuntimeException('Intentional domain logic error');
        }, $options);

        self::assertFalse($report->isSuccess());
        self::assertSame(ExecutionStatus::FAILED, $report->status);
        self::assertSame('Intentional domain logic error', $report->message);

        Event::assertDispatched(CommandExecutionFailed::class, function ($event) {
            return $event->domainSlug === 'domain-a' && $event->componentKey === 'failing-task';
        });
    }
}
