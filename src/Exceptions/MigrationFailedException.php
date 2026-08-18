<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;
use Throwable;

class MigrationFailedException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forError(string $domainSlug, string $error): self
    {
        return new self("Migration execution failed for domain [{$domainSlug}]: {$error}");
    }

    public static function withDetails(string $domainSlug, string $packageSlug, string $errorMessage, ?Throwable $previous = null): self
    {
        return new self("Migration failed for domain [{$domainSlug}] in package [{$packageSlug}]: {$errorMessage}", 0, $previous);
    }
}
