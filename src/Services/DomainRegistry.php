<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\DomainProfile;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\DomainNotFoundException;
use AlexKassel\DomainCore\Exceptions\StorageContextCollisionException;
use AlexKassel\DomainCore\Exceptions\StorageContextNotFoundException;

final class DomainRegistry implements DomainRegistryInterface
{
    /**
     * @var array<string, DomainProfile>
     */
    private array $domains = [];

    /**
     * Map of "connectionName:tablePrefix" => domainSlug for collision detection
     *
     * @var array<string, string>
     */
    private array $contextIdentityMap = [];

    public function registerDomain(string $slug, string $name, array $metadata = []): DomainProfile
    {
        $slug = trim($slug);

        if ($slug === '' || !preg_match('/^[a-z0-9\-_]+$/i', $slug)) {
            throw new \InvalidArgumentException(
                "Invalid domain slug '{$slug}'. Slug must be a non-empty string containing only alphanumeric characters, dashes, and underscores."
            );
        }

        if (!isset($this->domains[$slug])) {
            $this->domains[$slug] = new DomainProfile(
                slug: $slug,
                name: $name,
                contexts: [],
                metadata: $metadata,
            );
        } else {
            // Merge metadata
            $existing = $this->domains[$slug];
            $this->domains[$slug] = new DomainProfile(
                slug: $slug,
                name: $name !== '' ? $name : $existing->name,
                contexts: $existing->contexts,
                metadata: array_merge($existing->metadata, $metadata),
            );
        }

        return $this->domains[$slug];
    }

    public function registerStorageContext(StorageContext $context): void
    {
        // 1. Ensure domain profile exists (create placeholder if needed)
        if (!isset($this->domains[$context->domainSlug])) {
            $this->registerDomain($context->domainSlug, ucfirst(str_replace('-', ' ', $context->domainSlug)));
        }

        // 2. Collision Detection: Ensure identity key is not owned by another domain
        $identityKey = $context->getIdentityKey();
        if (isset($this->contextIdentityMap[$identityKey]) && $this->contextIdentityMap[$identityKey] !== $context->domainSlug) {
            throw StorageContextCollisionException::forCollision(
                newDomainSlug: $context->domainSlug,
                existingDomainSlug: $this->contextIdentityMap[$identityKey],
                connectionName: $context->isDatabase() ? $context->asDatabase()->connectionName : $identityKey,
                tablePrefix: $context->isDatabase() ? $context->asDatabase()->tablePrefix : ''
            );
        }

        // 3. Deduplication / Merge with existing context if present
        $profile = $this->domains[$context->domainSlug];
        $existing = $profile->getContext($context->contextSlug);

        if ($existing !== null) {
            if ($existing->storage->getDriverType() !== $context->storage->getDriverType() || $existing->getIdentityKey() !== $context->getIdentityKey()) {
                throw StorageContextCollisionException::forCollision(
                    newDomainSlug: $context->domainSlug,
                    existingDomainSlug: $context->domainSlug,
                    connectionName: $context->isDatabase() ? $context->asDatabase()->connectionName : $identityKey,
                    tablePrefix: $context->isDatabase() ? $context->asDatabase()->tablePrefix : ''
                );
            }

            if ($context->isDatabase() && $existing->isDatabase()) {
                $dbExisting = $existing->asDatabase();
                $dbNew = $context->asDatabase();
                $mergedPaths = array_values(array_unique(array_merge($dbExisting->migrationPaths, $dbNew->migrationPaths)));
                $mergedOptions = array_merge($existing->extraOptions, $context->extraOptions);

                $mergedContext = StorageContext::database(
                    domainSlug: $context->domainSlug,
                    contextSlug: $context->contextSlug,
                    connectionName: $dbNew->connectionName,
                    tablePrefix: $dbNew->tablePrefix,
                    migrationPaths: $mergedPaths,
                    autoCreateSqliteDatabase: $dbNew->autoCreateSqliteDatabase || $dbExisting->autoCreateSqliteDatabase,
                    extraOptions: $mergedOptions,
                );
                $profile->addContext($mergedContext);
            } else {
                $mergedOptions = array_merge($existing->extraOptions, $context->extraOptions);
                $profile->addContext(new StorageContext(
                    domainSlug: $context->domainSlug,
                    contextSlug: $context->contextSlug,
                    storage: $context->storage,
                    extraOptions: $mergedOptions
                ));
            }
        } else {
            $this->contextIdentityMap[$identityKey] = $context->domainSlug;
            $profile->addContext($context);
        }
    }

    public function hasDomain(string $slug): bool
    {
        return isset($this->domains[$slug]);
    }

    public function getDomain(string $slug): DomainProfile
    {
        if (!isset($this->domains[$slug])) {
            throw DomainNotFoundException::forSlug($slug);
        }

        return $this->domains[$slug];
    }

    public function allDomains(): array
    {
        return $this->domains;
    }

    public function hasStorageContext(string $domainSlug, string $contextSlug): bool
    {
        return isset($this->domains[$domainSlug]) && $this->domains[$domainSlug]->hasContext($contextSlug);
    }

    public function getStorageContext(string $domainSlug, string $contextSlug): StorageContext
    {
        $domain = $this->getDomain($domainSlug);
        $context = $domain->getContext($contextSlug);

        if ($context === null) {
            throw StorageContextNotFoundException::forContext($domainSlug, $contextSlug);
        }

        return $context;
    }

    public function allStorageContexts(): array
    {
        $all = [];
        foreach ($this->domains as $domain) {
            foreach ($domain->allContexts() as $context) {
                $all["{$context->domainSlug}:{$context->contextSlug}"] = $context;
            }
        }

        return $all;
    }

    public function compileCache(): array
    {
        $data = [
            'domains' => [],
            'identities' => $this->contextIdentityMap,
        ];

        foreach ($this->domains as $slug => $profile) {
            $data['domains'][$slug] = $profile->toArray();
        }

        return $data;
    }

    public function loadFromCache(array $cachedData): void
    {
        $this->clear();

        $this->contextIdentityMap = (array) ($cachedData['identities'] ?? []);

        foreach ((array) ($cachedData['domains'] ?? []) as $slug => $profileData) {
            $this->domains[$slug] = DomainProfile::fromArray($profileData);
        }
    }

    public function clear(): void
    {
        $this->domains = [];
        $this->contextIdentityMap = [];
    }
}
