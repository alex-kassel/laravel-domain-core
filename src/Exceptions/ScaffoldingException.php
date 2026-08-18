<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

class ScaffoldingException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function targetExists(string $path): self
    {
        return new self("Target domain package directory already exists at [{$path}].");
    }

    public static function templateError(string $reason): self
    {
        return new self("Domain scaffolding failed: {$reason}");
    }
}
