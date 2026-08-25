<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Events;

use Throwable;

final class LockAcquisitionFailed
{
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $componentKey,
        public readonly ?string $errorMessage = null,
        public readonly ?string $stackTrace = null
    ) {}

    public static function fromThrowable(string $domainSlug, string $componentKey, Throwable $throwable): self
    {
        return new self(
            domainSlug: $domainSlug,
            componentKey: $componentKey,
            errorMessage: $throwable->getMessage(),
            stackTrace: $throwable->getTraceAsString()
        );
    }
}
