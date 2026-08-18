<?php

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

class DomainResolutionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forMissingDomains(array $missingDomains): self
    {
        $list = implode(', ', $missingDomains);
        return new self("Specified target domains do not exist or are disabled: [{$list}].");
    }
}
