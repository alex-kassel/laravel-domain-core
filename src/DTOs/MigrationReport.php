<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

use AlexKassel\DomainCore\Enums\MigrationStatus;

final class MigrationReport
{
    /**
     * @param string $domainSlug
     * @param string $contextSlug
     * @param string $connectionName
     * @param array<int, string> $executedMigrations
     * @param float $durationSeconds
     * @param MigrationStatus $status
     * @param string|null $errorMessage
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $contextSlug,
        public readonly string $connectionName,
        public readonly array $executedMigrations = [],
        public readonly float $durationSeconds = 0.0,
        public readonly MigrationStatus $status = MigrationStatus::SUCCESS,
        public readonly ?string $errorMessage = null,
    ) {
        if (trim($this->domainSlug) === '') {
            throw new \InvalidArgumentException('Domain slug cannot be empty in migration report.');
        }
        if (trim($this->contextSlug) === '') {
            throw new \InvalidArgumentException('Context slug cannot be empty in migration report.');
        }
    }

    public function isSuccess(): bool
    {
        return $this->status === MigrationStatus::SUCCESS;
    }
}
