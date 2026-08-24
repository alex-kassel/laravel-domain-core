<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class StorageContextCollisionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forCollision(string $newDomainSlug, string $existingDomainSlug, string $connectionName, string $tablePrefix): self
    {
        return new self(
            "Storage context collision detected: Domain '{$newDomainSlug}' attempted to register connection '{$connectionName}' with prefix '{$tablePrefix}', which is already owned by domain '{$existingDomainSlug}'."
        );
    }
}
