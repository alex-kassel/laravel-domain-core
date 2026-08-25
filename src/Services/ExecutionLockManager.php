<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Events\LockAcquisitionFailed;
use AlexKassel\DomainCore\Exceptions\LockAcquisitionException;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
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
        int $ttlSeconds = 300,
        bool $force = false
    ): bool {
        $lockKey = $this->formatLockKey($domainSlug, $componentKey);

        if ($force) {
            $this->releaseLock($domainSlug, $componentKey);
        }

        try {
            $lock = $this->createLock($lockKey, $ttlSeconds);
            $acquired = $lock->get();
        } catch (Throwable $e) {
            $this->events->dispatch(LockAcquisitionFailed::fromThrowable($domainSlug, $componentKey, $e));
            throw LockAcquisitionException::forDomain($domainSlug, $componentKey, $e);
        }

        if (! $acquired) {
            return false; // Lock occupied, skipped
        }

        // Register POSIX signal handling on supported platforms (Linux/macOS)
        $hasPcntl = extension_loaded('pcntl')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals')
            && defined('SIGTERM')
            && defined('SIGINT');
        if ($hasPcntl) {
            pcntl_async_signals(true);
            $signalHandler = function () use ($domainSlug, $componentKey) {
                $this->releaseLock($domainSlug, $componentKey);
                exit(1);
            };
            pcntl_signal(SIGTERM, $signalHandler);
            pcntl_signal(SIGINT, $signalHandler);
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

        try {
            $lock = $this->createLock($lockKey, 1);

            if ($lock->get()) {
                $lock->release();

                return false;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function createLock(string $lockKey, int $ttlSeconds): Lock
    {
        if (method_exists($this->cache, 'lock')) {
            /** @var Lock */
            return $this->cache->lock($lockKey, $ttlSeconds);
        }

        $store = $this->cache->getStore();
        if ($store instanceof LockProvider || method_exists($store, 'lock')) {
            /** @var Lock */
            return $store->lock($lockKey, $ttlSeconds);
        }

        throw new LockAcquisitionException('Cache store does not support atomic locks.');
    }

    private function formatLockKey(string $domainSlug, string $componentKey): string
    {
        return "domain_core:lock:{$domainSlug}:{$componentKey}";
    }
}
