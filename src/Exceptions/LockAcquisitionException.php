<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

class LockAcquisitionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forBackendFailure(string $domainSlug, string $componentKey, string $error): self
    {
        return new self("Execution lock backend failed for domain [{$domainSlug}] component [{$componentKey}]: {$error}");
    }
}
