<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class DomainNotFoundException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forSlug(string $slug): self
    {
        return new self("Domain with slug '{$slug}' is not registered in DomainRegistry.");
    }
}
