<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Services\ExecutionLockManager;
use AlexKassel\DomainCore\Tests\TestCase;

final class ExecutionLockManagerTest extends TestCase
{
    private ExecutionLockManagerInterface $lockManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockManager = new ExecutionLockManager($this->app->make('cache.store'));
    }

    public function testExecutesCallbackWithLock(): void
    {
        $executed = false;

        $success = $this->lockManager->withLock('domain-one', 'runner', function () use (&$executed) {
            $executed = true;
        });

        self::assertTrue($success);
        self::assertTrue($executed);
    }

    public function testReleaseLockAllowsSubsequentExecution(): void
    {
        $this->lockManager->releaseLock('domain-one', 'runner');
        self::assertFalse($this->lockManager->isLocked('domain-one', 'runner'));
    }
}
