<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Contracts;

use Closure;

interface ExecutionLockManagerInterface
{
    /**
     * Attempt to execute a callback with an exclusive lock for domain + component.
     * Returns true if lock was acquired and callback ran, false if skipped due to overlap.
     *
     * @param string $domainSlug
     * @param string $componentKey
     * @param Closure(): mixed $callback
     * @param int $ttlSeconds
     * @param bool $force
     * @return bool
     */
    public function withLock(
        string $domainSlug,
        string $componentKey,
        Closure $callback,
        int $ttlSeconds = 300,
        bool $force = false
    ): bool;

    public function releaseLock(string $domainSlug, string $componentKey): bool;

    public function isLocked(string $domainSlug, string $componentKey): bool;
}
