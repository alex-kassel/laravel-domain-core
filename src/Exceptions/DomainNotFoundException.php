<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

class DomainNotFoundException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forSlug(string $slug): self
    {
        return new self("Domain context [{$slug}] is not registered or is disabled.");
    }
}
