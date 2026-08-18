<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

class DomainSlugCollisionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forCollision(string $slug, string $existingClass, string $newClass): self
    {
        return new self(
            "Domain slug collision: slug [{$slug}] is already bound to class [{$existingClass}], cannot bind to [{$newClass}]."
        );
    }
}
