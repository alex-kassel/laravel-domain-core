<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Contracts;

use AlexKassel\DomainCore\DTOs\DomainProfile;
use AlexKassel\DomainCore\DTOs\StorageContext;

interface DomainRegistryInterface
{
    /**
     * Register or update a domain profile.
     *
     * @param string $slug Unique domain slug
     * @param string $name Human-readable domain name
     * @param array<string, mixed> $metadata Arbitrary metadata
     */
    public function registerDomain(string $slug, string $name, array $metadata = []): DomainProfile;

    /**
     * Register a storage context with automatic collision detection and deduplication.
     */
    public function registerStorageContext(StorageContext $context): void;

    public function hasDomain(string $slug): bool;

    public function getDomain(string $slug): DomainProfile;

    /**
     * @return array<string, DomainProfile>
     */
    public function allDomains(): array;

    public function hasStorageContext(string $domainSlug, string $contextSlug): bool;

    public function getStorageContext(string $domainSlug, string $contextSlug): StorageContext;

    /**
     * @return array<string, StorageContext>
     */
    public function allStorageContexts(): array;

    /**
     * @return array<string, mixed>
     */
    public function compileCache(): array;

    /**
     * @param array<string, mixed> $cachedData
     */
    public function loadFromCache(array $cachedData): void;

    public function clear(): void;
}
