<?php

namespace AlexKassel\DomainCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommandRunSkippedDueToOverlap
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $domainSlug,
        public string $componentKey,
        public string $timestamp
    ) {}
}
