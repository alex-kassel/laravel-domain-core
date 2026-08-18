<?php

namespace AlexKassel\DomainCore\DTOs;

final readonly class CommandExecutionReport
{
    public function __construct(
        public string $status, // 'SUCCESS', 'SKIPPED', 'FAILED'
        public string $domainSlug,
        public string $componentKey,
        public int $executedItemsCount = 0,
        public float $durationSeconds = 0.0,
        public ?string $errorMessage = null,
    ) {}
}
