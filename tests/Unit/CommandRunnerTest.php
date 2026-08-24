<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainProfile;
use AlexKassel\DomainCore\Tests\TestCase;

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

    public function testParsesCliOptions(): void
    {
        $options = $this->runner->parseCliOptions([
            'all' => true,
            'domains' => 'domain-a,domain-b',
            'except-domains' => 'domain-b',
            'capability' => 'scraping',
            'force' => true,
            'dry-run' => false,
        ]);

        self::assertTrue($options->all);
        self::assertEqualsCanonicalizing(['domain-a', 'domain-b'], $options->domains);
        self::assertEqualsCanonicalizing(['domain-b'], $options->exceptDomains);
        self::assertSame('scraping', $options->capability);
        self::assertTrue($options->force);
        self::assertFalse($options->dryRun);
    }

    public function testResolvesTargetDomains(): void
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

    public function testExecutesDomainWithReport(): void
    {
        $domain = $this->registry->getDomain('domain-a');
        $options = new CommandOptionsDTO();

        $report = $this->runner->executeDomain($domain, 'crawler', function (DomainProfile $d) {
            return 42;
        }, $options);

        self::assertTrue($report->isSuccess());
        self::assertSame('domain-a', $report->domainSlug);
        self::assertSame('crawler', $report->componentKey);
        self::assertSame(42, $report->itemsProcessed);
    }
}
