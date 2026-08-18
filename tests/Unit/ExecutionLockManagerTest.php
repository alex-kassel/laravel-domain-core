<?php

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Tests\TestCase;

class ExecutionLockManagerTest extends TestCase
{
    protected ExecutionLockManagerInterface $lockManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockManager = $this->app->make(ExecutionLockManagerInterface::class);
    }

    public function test_it_acquires_and_releases_locks(): void
    {
        $acquired = $this->lockManager->acquire('domain-one', 'spider');
        $this->assertTrue($acquired);

        $this->assertTrue($this->lockManager->isLocked('domain-one', 'spider'));

        $this->lockManager->release('domain-one', 'spider');
        $this->assertFalse($this->lockManager->isLocked('domain-one', 'spider'));
    }

    public function test_it_force_releases_locks(): void
    {
        $this->lockManager->acquire('domain-one', 'normalizer');
        $this->assertTrue($this->lockManager->isLocked('domain-one', 'normalizer'));

        $this->lockManager->forceRelease('domain-one', 'normalizer');
        $this->assertFalse($this->lockManager->isLocked('domain-one', 'normalizer'));
    }
}
