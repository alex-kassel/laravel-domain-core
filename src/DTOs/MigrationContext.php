<?php

namespace AlexKassel\DomainCore\DTOs;

final readonly class MigrationContext
{
    public function __construct(
        public string $domainSlug,
        public string $packageSlug,
        public string $connectionName,
        public string $tablePrefix,
        public string $migrationPath,
        public ?string $databasePath = null,
        public bool $autoCreateDatabase = false,
    ) {}
}
