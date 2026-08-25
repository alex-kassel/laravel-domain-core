<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Contracts;

use AlexKassel\DomainCore\DTOs\StorageContext;
use Closure;

interface DomainContextManagerInterface
{
    /**
     * Execute a callback inside an isolated active domain & context scope.
     * Automatically restores the previous scope upon completion or failure.
     *
     * @template T
     * @param string $domainSlug
     * @param string $contextSlug
     * @param (Closure(StorageContext): T) $callback
     * @return T
     */
    public function using(string $domainSlug, string $contextSlug, Closure $callback): mixed;

    /**
     * Set the current active context manually.
     */
    public function setCurrent(string $domainSlug, string $contextSlug): StorageContext;

    /**
     * Set active context directly from a StorageContext instance.
     */
    public function setCurrentContext(StorageContext $context): void;

    /**
     * Get the active StorageContext. Throws NoActiveStorageContextException if null.
     */
    public function current(): StorageContext;

    /**
     * Get the active StorageContext or null if none is active.
     */
    public function currentOrNull(): ?StorageContext;

    /**
     * Check if an active context is currently set.
     */
    public function hasCurrent(): bool;

    /**
     * Clear the current active context stack.
     */
    public function clearCurrent(): void;

    /**
     * Get the DatabaseStorage of the current active context.
     * Throws IncompatibleStorageException if the active context is not a database.
     */
    public function database(): \AlexKassel\DomainCore\Storage\DatabaseStorage;

    /**
     * Get the FileStorage of the current active context.
     * Throws IncompatibleStorageException if the active context is not a filesystem.
     */
    public function filesystem(): \AlexKassel\DomainCore\Storage\FileStorage;

    /**
     * Get the RedisStorage of the current active context.
     * Throws IncompatibleStorageException if the active context is not a redis backend.
     */
    public function redis(): \AlexKassel\DomainCore\Storage\RedisStorage;

    /**
     * Get a Laravel Filesystem adapter for the active filesystem storage context.
     */
    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem;
}
