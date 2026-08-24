<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

final class MigrationReport
{
    /**
     * @param string $domainSlug
     * @param string $capabilitySlug
     * @param string $connectionName
     * @param array<int, string> $executedMigrations
     * @param float $durationSeconds
     * @param string $status ('SUCCESS', 'FAILED', 'NO_OP')
     * @param string|null $errorMessage
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $capabilitySlug,
        public readonly string $connectionName,
        public readonly array $executedMigrations = [],
        public readonly float $durationSeconds = 0.0,
        public readonly string $status = 'SUCCESS',
        public readonly ?string $errorMessage = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === 'SUCCESS';
    }
}
