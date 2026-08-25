<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;
use Throwable;

final class DatabaseProvisioningException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forConnection(string $connectionName, string $reason, ?Throwable $previous = null): self
    {
        return new self(
            "[PROBLEM] Failed to provision database for connection '{$connectionName}'. " .
            "[CAUSE] {$reason}. " .
            "[RESOLUTION] Verify filesystem write permissions, disk space, and directory paths configured for database connection '{$connectionName}'.",
            0,
            $previous
        );
    }
}
