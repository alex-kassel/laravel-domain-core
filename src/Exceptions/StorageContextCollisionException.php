<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class StorageContextCollisionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forCollision(string $newDomainSlug, string $existingDomainSlug, string $connectionName, string $tablePrefix): self
    {
        return new self(
            "[PROBLEM] Storage context collision: Domain '{$newDomainSlug}' attempted to claim connection '{$connectionName}' with table prefix '{$tablePrefix}'. ".
            "[CAUSE] Connection '{$connectionName}' and prefix '{$tablePrefix}' are already owned by domain '{$existingDomainSlug}'. ".
            "[RESOLUTION] Assign a unique connection name or distinct table prefix for domain '{$newDomainSlug}' to prevent cross-domain table collisions."
        );
    }
}
