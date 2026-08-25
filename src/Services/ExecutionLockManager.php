<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Events\LockAcquisitionFailed;
use AlexKassel\DomainCore\Exceptions\LockAcquisitionException;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

final class ExecutionLockManager implements ExecutionLockManagerInterface
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
    ) {}

    public function withLock(
        string $domainSlug,
        string $componentKey,
        Closure $callback,
        int $ttlSeconds = 3600,
        bool $force = false
    ): bool {
        $lockKey = $this->formatLockKey($domainSlug, $componentKey);

        if ($force) {
            $this->releaseLock($domainSlug, $componentKey);
        }

        try {
            $lock = method_exists($this->cache, 'lock')
                ? $this->cache->lock($lockKey, $ttlSeconds)
                : $this->cache->getStore()->lock($lockKey, $ttlSeconds);

            $acquired = $lock->get();
        } catch (Throwable $e) {
            $this->events->dispatch(LockAcquisitionFailed::fromThrowable($domainSlug, $componentKey, $e));
            throw LockAcquisitionException::forDomain($domainSlug, $componentKey, $e);
        }

        if (!$acquired) {
            return false; // Lock occupied, skipped
        }

        try {
            $callback();
            return true;
        } finally {
            try {
                $lock->release();
            } catch (Throwable) {
                // Ignore release failures in finally to preserve primary exception
            }
        }
    }

    public function releaseLock(string $domainSlug, string $componentKey): bool
    {
        $lockKey = $this->formatLockKey($domainSlug, $componentKey);

        if (method_exists($this->cache, 'restoreLock')) {
            try {
                $this->cache->restoreLock($lockKey, '')->forceRelease();
                return true;
            } catch (Throwable) {
                // Fallback to cache forget
            }
        }

        return $this->cache->forget($lockKey);
    }

    public function isLocked(string $domainSlug, string $componentKey): bool
    {
        $lockKey = $this->formatLockKey($domainSlug, $componentKey);

        return $this->cache->has($lockKey);
    }

    private function formatLockKey(string $domainSlug, string $componentKey): string
    {
        return "domain_core:lock:{$domainSlug}:{$componentKey}";
    }
}
