<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

class DomainConnectionNotFoundException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forConnection(string $connection, ?string $path = null): self
    {
        $message = "Database connection [{$connection}] could not be resolved.";
        if ($path !== null) {
            $message .= " SQLite database file not found at [{$path}].";
        }
        return new self($message);
    }
}
