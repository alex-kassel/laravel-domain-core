<?php

namespace AlexKassel\DomainCore\Contracts;

use AlexKassel\DomainCore\DTOs\DomainContext;

interface DomainRegistryInterface
{
    /**
     * Register a domain context configuration.
     */
    public function register(DomainContext $context): void;

    /**
     * Resolve a registered domain context by its slug/identifier.
     */
    public function resolve(string $domainSlug): DomainContext;

    /**
     * Check if a domain context is registered and enabled.
     */
    public function has(string $domainSlug): bool;

    /**
     * Get all registered and enabled domain contexts.
     *
     * @return array<string, DomainContext>
     */
    public function all(): array;
}
