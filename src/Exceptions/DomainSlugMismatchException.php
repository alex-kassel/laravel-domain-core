<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

class DomainSlugMismatchException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forMismatch(string $class, string $existingSlug, string $newSlug): self
    {
        return new self(
            "Domain slug mismatch: registered domain class [{$class}] was bound to slug [{$existingSlug}], cannot re-register as [{$newSlug}]."
        );
    }
}
