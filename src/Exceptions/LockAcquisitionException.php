<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;
use Throwable;

final class LockAcquisitionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forDomain(string $domainSlug, string $componentKey, ?Throwable $previous = null): self
    {
        return new self(
            "Failed to acquire lock backend for domain '{$domainSlug}' on component '{$componentKey}'.",
            0,
            $previous
        );
    }
}
