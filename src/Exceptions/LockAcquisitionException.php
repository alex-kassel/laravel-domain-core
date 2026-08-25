<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;
use Throwable;

final class LockAcquisitionException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forDomain(string $domainSlug, string $componentKey, ?Throwable $previous = null): self
    {
        return new self(
            "[PROBLEM] Lock system failed while processing domain '{$domainSlug}' on component '{$componentKey}'. ".
            '[CAUSE] The cache/lock provider encountered an internal transport or backend failure: '.($previous ? $previous->getMessage() : 'unknown error').'. '.
            '[RESOLUTION] Check that your cache driver (Redis, Memcached, Database) is running and reachable.',
            0,
            $previous
        );
    }
}
