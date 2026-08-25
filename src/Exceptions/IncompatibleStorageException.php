<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use AlexKassel\DomainCore\Enums\StorageDriverType;
use RuntimeException;

final class IncompatibleStorageException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forTypeMismatch(
        string $domainSlug,
        string $contextSlug,
        StorageDriverType $actualType,
        string $expectedType
    ): self {
        return new self(
            "[PROBLEM] Incompatible storage driver requested for context '{$contextSlug}' in domain '{$domainSlug}'. " .
            "[CAUSE] Context is configured with driver '{$actualType->value}', but '{$expectedType}' was required by caller. " .
            "[RESOLUTION] Check context configuration in config/domain.php or use the appropriate typed getter/adapter (e.g. as{$expectedType}())."
        );
    }
}
