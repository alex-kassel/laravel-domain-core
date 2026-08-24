<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Exceptions\LockAcquisitionException;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

final class ExecutionLockManager implements ExecutionLockManagerInterface
{
    public function __construct(
        private readonly CacheRepository $cache,
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
            $lock = $this->cache->lock($lockKey, $ttlSeconds);

            if (!$lock->get()) {
                return false; // Lock occupied, skipped
            }

            try {
                $callback();
                return true;
            } finally {
                $lock->release();
            }
        } catch (Throwable $e) {
            throw LockAcquisitionException::forDomain($domainSlug, $componentKey, $e);
        }
    }

    public function releaseLock(string $domainSlug, string $componentKey): bool
    {
        $lockKey = $this->formatLockKey($domainSlug, $componentKey);

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
