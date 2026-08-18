<?php

namespace AlexKassel\DomainCore\DTOs;

final readonly class DomainContext
{
    /**
     * @param array<string, mixed> $extraConfig
     */
    public function __construct(
        public string $domainSlug,
        public string $packageSlug,
        public string $connectionName,
        public string $tablePrefix,
        public ?string $className = null,
        public bool $isEnabled = true,
        public bool $autoCreateSqliteDatabase = false,
        public array $extraConfig = [],
    ) {}
}
