<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

use AlexKassel\DomainCore\Enums\ExecutionStatus;

final class CommandExecutionReport
{
    public readonly ExecutionStatus $status;

    /**
     * @param string $domainSlug
     * @param string $componentKey
     * @param ExecutionStatus|string $status
     * @param int $itemsProcessed
     * @param float $durationSeconds
     * @param string|null $message
     * @param array<string, mixed> $extraMetrics
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $componentKey,
        ExecutionStatus|string $status = ExecutionStatus::SUCCESS,
        public readonly int $itemsProcessed = 0,
        public readonly float $durationSeconds = 0.0,
        public readonly ?string $message = null,
        public readonly array $extraMetrics = [],
    ) {
        $this->status = is_string($status) ? (ExecutionStatus::tryFrom($status) ?? ExecutionStatus::FAILED) : $status;
    }

    public function isSuccess(): bool
    {
        return $this->status === ExecutionStatus::SUCCESS;
    }

    public function isSkipped(): bool
    {
        return $this->status === ExecutionStatus::SKIPPED;
    }
}
