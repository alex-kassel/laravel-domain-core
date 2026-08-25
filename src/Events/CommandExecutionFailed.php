<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Events;

use Throwable;

final class CommandExecutionFailed
{
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $componentKey,
        public readonly string $exceptionClass,
        public readonly string $errorMessage,
        public readonly string $stackTrace
    ) {}

    public static function fromThrowable(string $domainSlug, string $componentKey, Throwable $throwable): self
    {
        return new self(
            domainSlug: $domainSlug,
            componentKey: $componentKey,
            exceptionClass: get_class($throwable),
            errorMessage: $throwable->getMessage(),
            stackTrace: $throwable->getTraceAsString()
        );
    }
}
