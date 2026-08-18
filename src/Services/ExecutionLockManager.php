<?php

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class ExecutionLockManager implements ExecutionLockManagerInterface
{
    public function __construct(
        protected CacheRepository $cache,
        protected string $prefix = 'domain_core:lock:',
    ) {}

    public function acquire(string $domainSlug, string $componentKey, int $ttlSeconds = 3600): bool
    {
        $key = $this->getLockKey($domainSlug, $componentKey);

        return (bool) $this->cache->add($key, 1, $ttlSeconds);
    }

    public function release(string $domainSlug, string $componentKey): void
    {
        $key = $this->getLockKey($domainSlug, $componentKey);
        $this->cache->forget($key);
    }

    public function isLocked(string $domainSlug, string $componentKey): bool
    {
        $key = $this->getLockKey($domainSlug, $componentKey);

        return $this->cache->has($key);
    }

    public function forceRelease(string $domainSlug, string $componentKey): void
    {
        $key = $this->getLockKey($domainSlug, $componentKey);
        $this->cache->forget($key);
    }

    protected function getLockKey(string $domainSlug, string $componentKey): string
    {
        return $this->prefix . $componentKey . ':' . $domainSlug;
    }
}
