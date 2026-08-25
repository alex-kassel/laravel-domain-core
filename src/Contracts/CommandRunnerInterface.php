<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Contracts;

use AlexKassel\DomainCore\DTOs\CommandExecutionReport;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainProfile;
use Closure;

interface CommandRunnerInterface
{
    /**
     * @param  array<string, mixed>  $rawInput
     */
    public function parseCliOptions(array $rawInput): CommandOptionsDTO;

    /**
     * @return array<int, DomainProfile>
     */
    public function resolveTargetDomains(CommandOptionsDTO $options): array;

    /**
     * @template T
     *
     * @param  (Closure(DomainProfile, CommandOptionsDTO): T)  $callback
     */
    public function executeDomain(
        DomainProfile $domain,
        string $componentKey,
        Closure $callback,
        CommandOptionsDTO $options
    ): CommandExecutionReport;
}
