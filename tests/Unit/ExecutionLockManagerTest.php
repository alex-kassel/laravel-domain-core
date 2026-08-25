<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Services\ExecutionLockManager;
use AlexKassel\DomainCore\Tests\TestCase;
use InvalidArgumentException;

final class ExecutionLockManagerTest extends TestCase
{
    private ExecutionLockManagerInterface $lockManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockManager = new ExecutionLockManager(
            $this->app->make('cache.store'),
            $this->app->make('events')
        );
    }

    public function test_executes_callback_with_lock(): void
    {
        $executed = false;

        $success = $this->lockManager->withLock('domain-one', 'runner', function () use (&$executed) {
            $executed = true;
            self::assertTrue($this->lockManager->isLocked('domain-one', 'runner'));
        });

        self::assertTrue($success);
        self::assertTrue($executed);
        self::assertFalse($this->lockManager->isLocked('domain-one', 'runner'));
    }

    public function test_release_lock_allows_subsequent_execution(): void
    {
        $this->lockManager->releaseLock('domain-one', 'runner');
        self::assertFalse($this->lockManager->isLocked('domain-one', 'runner'));
    }

    public function test_does_not_mask_callback_exceptions_and_releases_lock_on_failure(): void
    {
        $caught = false;
        try {
            $this->lockManager->withLock('domain-one', 'failing-component', function () {
                throw new InvalidArgumentException('Domain validation failed');
            });
        } catch (InvalidArgumentException $e) {
            $caught = true;
            self::assertSame('Domain validation failed', $e->getMessage());
        }

        self::assertTrue($caught);
        self::assertFalse($this->lockManager->isLocked('domain-one', 'failing-component'));
    }
}
