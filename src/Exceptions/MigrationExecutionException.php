<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;
use Throwable;

final class MigrationExecutionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forContext(string $domainSlug, string $capabilitySlug, string $message, ?Throwable $previous = null): self
    {
        return new self(
            "Migration failed for domain '{$domainSlug}' under capability '{$capabilitySlug}': {$message}",
            0,
            $previous
        );
    }
}
