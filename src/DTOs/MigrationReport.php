<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

use AlexKassel\DomainCore\Enums\MigrationStatus;

final class MigrationReport
{
    public readonly MigrationStatus $status;

    /**
     * @param string $domainSlug
     * @param string $contextSlug
     * @param string $connectionName
     * @param array<int, string> $executedMigrations
     * @param float $durationSeconds
     * @param MigrationStatus|string $status
     * @param string|null $errorMessage
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $contextSlug,
        public readonly string $connectionName,
        public readonly array $executedMigrations = [],
        public readonly float $durationSeconds = 0.0,
        MigrationStatus|string $status = MigrationStatus::SUCCESS,
        public readonly ?string $errorMessage = null,
    ) {
        $this->status = is_string($status) ? (MigrationStatus::tryFrom($status) ?? MigrationStatus::FAILED) : $status;
    }

    public function isSuccess(): bool
    {
        return $this->status === MigrationStatus::SUCCESS;
    }
}
