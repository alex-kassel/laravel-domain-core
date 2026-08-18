<?php

namespace AlexKassel\DomainCore\DTOs;

final readonly class CommandOptionsDTO
{
    /**
     * @param array<string> $domains
     * @param array<string> $exceptDomains
     * @param array<string, mixed> $extraOptions
     */
    public function __construct(
        public bool $all = false,
        public array $domains = [],
        public array $exceptDomains = [],
        public bool $force = false,
        public bool $dryRun = false,
        public array $extraOptions = [],
    ) {}
}
