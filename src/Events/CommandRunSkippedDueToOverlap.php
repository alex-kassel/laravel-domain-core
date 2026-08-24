<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Events;

final class CommandRunSkippedDueToOverlap
{
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $componentKey,
    ) {}
}
