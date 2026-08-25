<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Facades;

use AlexKassel\DomainCore\Contracts\DomainContextManagerInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed using(string $domainSlug, string $contextSlug, Closure $callback)
 * @method static StorageContext setCurrent(string $domainSlug, string $contextSlug)
 * @method static void setCurrentContext(StorageContext $context)
 * @method static StorageContext current()
 * @method static ?StorageContext currentOrNull()
 * @method static bool hasCurrent()
 * @method static void clearCurrent()
 *
 * @see \AlexKassel\DomainCore\Services\DomainContextManager
 */
final class DomainContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DomainContextManagerInterface::class;
    }
}
