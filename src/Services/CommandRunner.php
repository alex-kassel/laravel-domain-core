<?php

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\DTOs\CommandExecutionReport;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainContext;
use AlexKassel\DomainCore\Events\CommandRunSkippedDueToOverlap;
use AlexKassel\DomainCore\Exceptions\DomainResolutionException;
use Closure;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Throwable;

class CommandRunner implements CommandRunnerInterface
{
    public function __construct(
        protected DomainRegistryInterface $domainRegistry,
        protected ExecutionLockManagerInterface $lockManager,
        protected EventDispatcher $events,
    ) {}

    public function parseCliOptions(array $rawOptions): CommandOptionsDTO
    {
        $all = (bool) ($rawOptions['all'] ?? false);

        $domains = [];
        if (!empty($rawOptions['domains'])) {
            $domains = is_array($rawOptions['domains'])
                ? $rawOptions['domains']
                : array_filter(array_map('trim', explode(',', (string) $rawOptions['domains'])));
        }

        $exceptDomains = [];
        if (!empty($rawOptions['except-domains'])) {
            $exceptDomains = is_array($rawOptions['except-domains'])
                ? $rawOptions['except-domains']
                : array_filter(array_map('trim', explode(',', (string) $rawOptions['except-domains'])));
        }

        $force = (bool) ($rawOptions['force'] ?? false);
        $dryRun = (bool) ($rawOptions['dry-run'] ?? false);

        return new CommandOptionsDTO(
            all: $all,
            domains: array_values($domains),
            exceptDomains: array_values($exceptDomains),
            force: $force,
            dryRun: $dryRun,
        );
    }

    public function resolveTargetDomains(CommandOptionsDTO $options): array
    {
        $registered = $this->domainRegistry->all();

        if (!empty($options->domains)) {
            $resolved = [];
            $missing = [];
            foreach ($options->domains as $slug) {
                if (!isset($registered[$slug])) {
                    $missing[] = $slug;
                } else {
                    $resolved[$slug] = $registered[$slug];
                }
            }
            if (!empty($missing)) {
                throw DomainResolutionException::forMissingDomains($missing);
            }
        } elseif ($options->all) {
            $resolved = $registered;
        } else {
            $resolved = $registered;
        }

        if (!empty($options->exceptDomains)) {
            foreach ($options->exceptDomains as $exceptSlug) {
                unset($resolved[$exceptSlug]);
            }
        }

        return $resolved;
    }

    public function executeDomain(
        DomainContext $domain,
        string $componentKey,
        Closure $callback,
        CommandOptionsDTO $options
    ): CommandExecutionReport {
        $startTime = microtime(true);
        $slug = $domain->domainSlug;

        $acquired = $this->lockManager->acquire($slug, $componentKey);

        if (!$acquired) {
            $timestamp = date('c');
            $this->events->dispatch(new CommandRunSkippedDueToOverlap($slug, $componentKey, $timestamp));

            return new CommandExecutionReport(
                status: 'SKIPPED',
                domainSlug: $slug,
                componentKey: $componentKey,
                executedItemsCount: 0,
                durationSeconds: round(microtime(true) - $startTime, 4),
            );
        }

        try {
            $result = $callback($domain, $options);
            $itemsCount = is_int($result) ? $result : 0;

            return new CommandExecutionReport(
                status: 'SUCCESS',
                domainSlug: $slug,
                componentKey: $componentKey,
                executedItemsCount: $itemsCount,
                durationSeconds: round(microtime(true) - $startTime, 4),
            );
        } catch (Throwable $e) {
            return new CommandExecutionReport(
                status: 'FAILED',
                domainSlug: $slug,
                componentKey: $componentKey,
                executedItemsCount: 0,
                durationSeconds: round(microtime(true) - $startTime, 4),
                errorMessage: $e->getMessage(),
            );
        } finally {
            $this->lockManager->release($slug, $componentKey);
        }
    }
}
