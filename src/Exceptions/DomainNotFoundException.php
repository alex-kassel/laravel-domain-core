<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class DomainNotFoundException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forSlug(string $slug): self
    {
        return new self(
            "[PROBLEM] Domain profile '{$slug}' is not registered in DomainRegistry. " .
            "[CAUSE] No domain with slug '{$slug}' was discovered or registered during service provider boot. " .
            "[RESOLUTION] Register domain '{$slug}' via DomainRegistry::registerDomain('{$slug}', ...) in your package service provider, or check available domains using 'php artisan domain:status'."
        );
    }
}
