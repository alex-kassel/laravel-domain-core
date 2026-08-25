<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class StorageContextNotFoundException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forContext(string $domainSlug, string $contextSlug): self
    {
        return new self(
            "[PROBLEM] Storage context '{$contextSlug}' is not registered under domain '{$domainSlug}'. " .
            "[CAUSE] The domain profile exists in DomainRegistry, but no StorageContext was registered with contextSlug: '{$contextSlug}'. " .
            "[RESOLUTION] Register the storage context via DomainRegistry::registerStorageContext(new StorageContext(domainSlug: '{$domainSlug}', contextSlug: '{$contextSlug}', ...)) in your DomainServiceProvider::boot() method."
        );
    }
}
