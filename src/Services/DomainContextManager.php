<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\DomainContextManagerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\NoActiveStorageContextException;
use Closure;
use Throwable;

final class DomainContextManager implements DomainContextManagerInterface
{
    /**
     * LIFO stack of active StorageContext instances
     *
     * @var array<int, StorageContext>
     */
    private array $contextStack = [];

    public function __construct(
        private readonly DomainRegistryInterface $registry,
    ) {}

    public function using(string $domainSlug, string $capabilitySlug, Closure $callback): mixed
    {
        $context = $this->registry->getStorageContext($domainSlug, $capabilitySlug);
        $this->pushContext($context);

        try {
            return $callback($context);
        } finally {
            $this->popContext();
        }
    }

    public function setCurrent(string $domainSlug, string $capabilitySlug): StorageContext
    {
        $context = $this->registry->getStorageContext($domainSlug, $capabilitySlug);
        $this->setCurrentContext($context);

        return $context;
    }

    public function setCurrentContext(StorageContext $context): void
    {
        // Replace current top of stack or push
        if (!empty($this->contextStack)) {
            array_pop($this->contextStack);
        }

        $this->contextStack[] = $context;
    }

    public function current(): StorageContext
    {
        $context = $this->currentOrNull();

        if ($context === null) {
            throw NoActiveStorageContextException::create();
        }

        return $context;
    }

    public function currentOrNull(): ?StorageContext
    {
        if (empty($this->contextStack)) {
            return null;
        }

        return end($this->contextStack);
    }

    public function hasCurrent(): bool
    {
        return !empty($this->contextStack);
    }

    public function clearCurrent(): void
    {
        $this->contextStack = [];
    }

    private function pushContext(StorageContext $context): void
    {
        $this->contextStack[] = $context;
    }

    private function popContext(): ?StorageContext
    {
        return array_pop($this->contextStack);
    }
}
