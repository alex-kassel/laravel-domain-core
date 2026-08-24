<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class NoActiveStorageContextException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function create(): self
    {
        return new self(
            'No active StorageContext is currently set. Wrap execution in DomainContext::using($domain, $capability, fn() => ... ) or call DomainContext::setCurrent($domain, $capability).'
        );
    }
}
