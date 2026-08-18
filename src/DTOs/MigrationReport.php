<?php

namespace AlexKassel\DomainCore\DTOs;

final readonly class MigrationReport
{
    /**
     * @param array<int, string> $executedMigrations
     * @param array<int, string> $failedMigrations
     */
    public function __construct(
        public string $domainSlug,
        public string $packageSlug,
        public string $connectionName,
        public string $tablePrefix,
        public array $executedMigrations,
        public array $failedMigrations = [],
        public ?string $errorMessage = null,
        public float $durationSeconds = 0.0,
    ) {}
}
