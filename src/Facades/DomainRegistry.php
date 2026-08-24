<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Facades;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\DomainProfile;
use AlexKassel\DomainCore\DTOs\StorageContext;
use Illuminate\Support\Facades\Facade;

/**
 * @method static DomainProfile registerDomain(string $slug, string $name, array $metadata = [])
 * @method static void registerStorageContext(StorageContext $context)
 * @method static bool hasDomain(string $slug)
 * @method static DomainProfile getDomain(string $slug)
 * @method static array<string, DomainProfile> allDomains()
 * @method static bool hasStorageContext(string $domainSlug, string $capabilitySlug)
 * @method static StorageContext getStorageContext(string $domainSlug, string $capabilitySlug)
 * @method static array<string, StorageContext> allStorageContexts()
 * @method static array compileCache()
 * @method static void loadFromCache(array $cachedData)
 * @method static void clear()
 *
 * @see \AlexKassel\DomainCore\Services\DomainRegistry
 */
final class DomainRegistry extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DomainRegistryInterface::class;
    }
}
