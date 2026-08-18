<?php

namespace AlexKassel\DomainCore\Contracts;

use AlexKassel\DomainCore\DTOs\CommandExecutionReport;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainContext;
use Closure;

interface CommandRunnerInterface
{
    /**
     * Parse raw CLI options into a typed DTO.
     */
    public function parseCliOptions(array $rawOptions): CommandOptionsDTO;

    /**
     * Resolve target domain contexts matching CLI options (--all, --domains, --except-domains).
     *
     * @return array<string, DomainContext>
     */
    public function resolveTargetDomains(CommandOptionsDTO $options): array;

    /**
     * Execute a callback for a specific domain context with automatic lock management and SKIPPED recovery.
     */
    public function executeDomain(
        DomainContext $domain,
        string $componentKey,
        Closure $callback,
        CommandOptionsDTO $options
    ): CommandExecutionReport;
}
