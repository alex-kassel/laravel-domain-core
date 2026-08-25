<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;
use Throwable;

final class MigrationExecutionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forContext(string $domainSlug, string $contextSlug, string $message, ?Throwable $previous = null): self
    {
        return new self(
            "[PROBLEM] Migration failed for domain '{$domainSlug}' under context '{$contextSlug}'. ".
            "[CAUSE] {$message}. ".
            "[RESOLUTION] Review migration file syntax, database connection permissions, and schema constraints for context '{$contextSlug}'.",
            0,
            $previous
        );
    }
}
