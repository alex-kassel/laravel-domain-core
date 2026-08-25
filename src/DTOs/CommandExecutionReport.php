<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

use AlexKassel\DomainCore\Enums\ExecutionStatus;

final class CommandExecutionReport
{
    /**
     * @param  array<string, mixed>  $extraMetrics
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $componentKey,
        public readonly ExecutionStatus $status = ExecutionStatus::SUCCESS,
        public readonly int $itemsProcessed = 0,
        public readonly float $durationSeconds = 0.0,
        public readonly ?string $message = null,
        public readonly array $extraMetrics = [],
    ) {
        if (trim($this->domainSlug) === '') {
            throw new \InvalidArgumentException('Domain slug cannot be empty in execution report.');
        }
        if (trim($this->componentKey) === '') {
            throw new \InvalidArgumentException('Component key cannot be empty in execution report.');
        }
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
