<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\DTOs\CommandExecutionReport;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainProfile;
use AlexKassel\DomainCore\Enums\ExecutionStatus;
use AlexKassel\DomainCore\Events\CommandExecutionFailed;
use AlexKassel\DomainCore\Events\CommandRunSkippedDueToOverlap;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final class CommandRunner implements CommandRunnerInterface
{
    public function __construct(
        private readonly DomainRegistryInterface $registry,
        private readonly ExecutionLockManagerInterface $lockManager,
        private readonly Dispatcher $events,
    ) {}

    public function parseCliOptions(array $rawInput): CommandOptionsDTO
    {
        return CommandOptionsDTO::fromArray($rawInput);
    }

    public function resolveTargetDomains(CommandOptionsDTO $options): array
    {
        $all = $this->registry->allDomains();

        if ($options->all) {
            $targets = array_values($all);
        } elseif (!empty($options->domains)) {
            $targets = [];
            foreach ($options->domains as $slug) {
                if (!$this->registry->hasDomain($slug)) {
                    throw DomainNotFoundException::forSlug($slug);
                }
                $targets[] = $this->registry->getDomain($slug);
            }
        } else {
            $targets = array_values($all);
        }

        if (!empty($options->exceptDomains)) {
            $targets = array_values(array_filter($targets, static function (DomainProfile $domain) use ($options) {
                return !in_array($domain->slug, $options->exceptDomains, true);
            }));
        }

        return $targets;
    }

    public function executeDomain(
        DomainProfile $domain,
        string $componentKey,
        Closure $callback,
        CommandOptionsDTO $options
    ): CommandExecutionReport {
        $startTime = microtime(true);
        $itemsProcessed = 0;
        $status = ExecutionStatus::SUCCESS;
        $message = null;

        if ($options->dryRun) {
            return new CommandExecutionReport(
                domainSlug: $domain->slug,
                componentKey: $componentKey,
                status: ExecutionStatus::SUCCESS,
                itemsProcessed: 0,
                durationSeconds: 0.0,
                message: 'Dry run completed without changes.'
            );
        }

        try {
            $lockAcquired = $this->lockManager->withLock(
                domainSlug: $domain->slug,
                componentKey: $componentKey,
                callback: function () use ($domain, $componentKey, $callback, $options, &$itemsProcessed) {
                    $result = $callback($domain, $options);
                    if (is_int($result)) {
                        $itemsProcessed = $result;
                    }
                },
                ttlSeconds: $options->lockTtl,
                force: $options->force
            );

            if (!$lockAcquired) {
                $this->events->dispatch(new CommandRunSkippedDueToOverlap($domain->slug, $componentKey));

                return new CommandExecutionReport(
                    domainSlug: $domain->slug,
                    componentKey: $componentKey,
                    status: ExecutionStatus::SKIPPED,
                    itemsProcessed: 0,
                    durationSeconds: round(microtime(true) - $startTime, 4),
                    message: "Execution skipped due to active overlap lock for domain '{$domain->slug}' on component '{$componentKey}'."
                );
            }
        } catch (Throwable $e) {
            $status = ExecutionStatus::FAILED;
            $message = $e->getMessage();
            $this->events->dispatch(CommandExecutionFailed::fromThrowable($domain->slug, $componentKey, $e));
        }

        $duration = round(microtime(true) - $startTime, 4);

        return new CommandExecutionReport(
            domainSlug: $domain->slug,
            componentKey: $componentKey,
            status: $status,
            itemsProcessed: $itemsProcessed,
            durationSeconds: $duration,
            message: $message
        );
    }
}
