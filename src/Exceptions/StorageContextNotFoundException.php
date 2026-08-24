<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class StorageContextNotFoundException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forCapability(string $domainSlug, string $capabilitySlug): self
    {
        return new self(
            "Storage context for capability '{$capabilitySlug}' not found in domain '{$domainSlug}'."
        );
    }
}
