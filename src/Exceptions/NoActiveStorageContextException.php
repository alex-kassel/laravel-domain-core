<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class NoActiveStorageContextException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function create(): self
    {
        return new self(
            "[PROBLEM] No active StorageContext is currently set in the context stack. " .
            "[CAUSE] An operation requiring an ambient domain storage context was invoked outside an active scope. " .
            "[RESOLUTION] Wrap execution in DomainContext::using(\$domainSlug, \$contextSlug, fn() => ...) or call DomainContext::setCurrent(\$domainSlug, \$contextSlug)."
        );
    }
}
