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
     * LIFO stack of active scoped StorageContext instances (from using() calls)
     *
     * @var array<int, StorageContext>
     */
    private array $contextStack = [];

    /**
     * Base manual context set via setCurrent/setCurrentContext
     */
    private ?StorageContext $manualContext = null;

    public function __construct(
        private readonly DomainRegistryInterface $registry,
        private readonly DatabaseProvisioner $provisioner,
    ) {}

    public function using(string $domainSlug, string $contextSlug, Closure $callback): mixed
    {
        $context = $this->registry->getStorageContext($domainSlug, $contextSlug);
        $this->pushContext($context);

        try {
            return $callback($context);
        } finally {
            $this->popContext();
        }
    }

    public function setCurrent(string $domainSlug, string $contextSlug): StorageContext
    {
        $context = $this->registry->getStorageContext($domainSlug, $contextSlug);
        $this->setCurrentContext($context);

        return $context;
    }

    public function setCurrentContext(StorageContext $context): void
    {
        $this->provisioner->provision($context);
        $this->manualContext = $context;
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
        if (!empty($this->contextStack)) {
            return $this->contextStack[array_key_last($this->contextStack)];
        }

        return $this->manualContext;
    }

    public function hasCurrent(): bool
    {
        return !empty($this->contextStack) || $this->manualContext !== null;
    }

    public function clearCurrent(): void
    {
        $this->contextStack = [];
        $this->manualContext = null;
    }

    private function pushContext(StorageContext $context): void
    {
        $this->provisioner->provision($context);

        $this->contextStack[] = $context;
    }

    private function popContext(): ?StorageContext
    {
        return array_pop($this->contextStack);
    }
}
