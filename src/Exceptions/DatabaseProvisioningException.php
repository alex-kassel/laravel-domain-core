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
            "Failed to provision database for connection '{$connectionName}': {$reason}",
            0,
            $previous
        );
    }
}
