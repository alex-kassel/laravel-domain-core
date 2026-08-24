<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

final class CommandExecutionReport
{
    /**
     * @param string $domainSlug
     * @param string $componentKey
     * @param string $status ('SUCCESS', 'FAILED', 'SKIPPED')
     * @param int $itemsProcessed
     * @param float $durationSeconds
     * @param string|null $message
     * @param array<string, mixed> $extraMetrics
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $componentKey,
        public readonly string $status = 'SUCCESS',
        public readonly int $itemsProcessed = 0,
        public readonly float $durationSeconds = 0.0,
        public readonly ?string $message = null,
        public readonly array $extraMetrics = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === 'SUCCESS';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'SKIPPED';
    }
}
