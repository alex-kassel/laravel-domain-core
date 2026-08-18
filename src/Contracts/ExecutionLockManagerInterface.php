<?php

namespace AlexKassel\DomainCore\Contracts;

interface ExecutionLockManagerInterface
{
    /**
     * Acquire a distributed execution lock for the domain slug and component.
     */
    public function acquire(string $domainSlug, string $componentKey, int $ttlSeconds = 3600): bool;

    /**
     * Release an acquired execution lock.
     */
    public function release(string $domainSlug, string $componentKey): void;

    /**
     * Check if an execution lock is currently held.
     */
    public function isLocked(string $domainSlug, string $componentKey): bool;

    /**
     * Force release an execution lock (for operator administrative override).
     */
    public function forceRelease(string $domainSlug, string $componentKey): void;
}
