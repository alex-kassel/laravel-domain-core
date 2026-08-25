<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

use AlexKassel\DomainCore\Contracts\Storage\StorageInterface;
use AlexKassel\DomainCore\Enums\StorageDriverType;
use AlexKassel\DomainCore\Exceptions\IncompatibleStorageException;
use AlexKassel\DomainCore\Storage\DatabaseStorage;
use AlexKassel\DomainCore\Storage\FileStorage;
use AlexKassel\DomainCore\Storage\RedisStorage;
use AlexKassel\DomainCore\Storage\StorageFactory;
use InvalidArgumentException;

/**
 * Logical Domain Storage Context.
 *
 * @property-read string $connectionName
 * @property-read string $tablePrefix
 * @property-read array<int, string> $migrationPaths
 * @property-read bool $autoCreateSqliteDatabase
 */
final class StorageContext
{
    /**
     * @param string $domainSlug Unique domain slug (e.g., 'domain-one')
     * @param string $contextSlug Unique context slug (e.g., 'primary', 'archive', 'analytics')
     * @param StorageInterface $storage Physical storage backend instance
     * @param array<string, mixed> $extraOptions Custom metadata for context storage
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $contextSlug,
        public readonly StorageInterface $storage,
        public readonly array $extraOptions = [],
    ) {
        $this->validateSlug('domainSlug', $this->domainSlug);
        $this->validateSlug('contextSlug', $this->contextSlug);
    }

    /**
     * Create a Relational Database Storage Context.
     *
     * @param string $domainSlug Domain slug (e.g. 'leasing')
     * @param string $contextSlug Context slug (e.g. 'primary', 'scraping')
     * @param string $connectionName Database connection name (e.g. 'sqlite_leasing_primary')
     * @param string $tablePrefix Table prefix (e.g. 'leasing_primary_')
     * @param array<int, string> $migrationPaths Absolute directory paths with migrations
     * @param bool $autoCreateSqliteDatabase Whether to auto-create SQLite DB
     * @param array<string, mixed> $extraOptions Custom metadata
     */
    public static function database(
        string $domainSlug,
        string $contextSlug,
        string $connectionName,
        string $tablePrefix = '',
        array $migrationPaths = [],
        bool $autoCreateSqliteDatabase = false,
        array $extraOptions = [],
    ): self {
        return new self(
            domainSlug: $domainSlug,
            contextSlug: $contextSlug,
            storage: new DatabaseStorage(
                connectionName: $connectionName,
                tablePrefix: $tablePrefix,
                migrationPaths: $migrationPaths,
                autoCreateSqliteDatabase: $autoCreateSqliteDatabase,
                extraOptions: $extraOptions,
            ),
            extraOptions: $extraOptions,
        );
    }

    /**
     * Create a Filesystem / Object Storage Context.
     *
     * @param string $domainSlug Domain slug (e.g. 'leasing')
     * @param string $contextSlug Context slug (e.g. 'frontend', 'raw')
     * @param string $disk Laravel filesystem disk (e.g. 's3', 'local')
     * @param string $basePath Base path/folder (e.g. 'leasing/raw/')
     * @param array<string, mixed> $extraOptions Custom metadata
     */
    public static function filesystem(
        string $domainSlug,
        string $contextSlug,
        string $disk,
        string $basePath = '',
        array $extraOptions = [],
    ): self {
        return new self(
            domainSlug: $domainSlug,
            contextSlug: $contextSlug,
            storage: new FileStorage(
                disk: $disk,
                basePath: $basePath,
                extraOptions: $extraOptions,
            ),
            extraOptions: $extraOptions,
        );
    }

    /**
     * Create a Redis Storage Context.
     *
     * @param string $domainSlug Domain slug (e.g. 'leasing')
     * @param string $contextSlug Context slug (e.g. 'queue', 'cache')
     * @param string $connection Redis connection name (e.g. 'default')
     * @param string $keyPrefix Redis key prefix (e.g. 'leasing:queue:')
     * @param array<string, mixed> $extraOptions Custom metadata
     */
    public static function redis(
        string $domainSlug,
        string $contextSlug,
        string $connection = 'default',
        string $keyPrefix = '',
        array $extraOptions = [],
    ): self {
        return new self(
            domainSlug: $domainSlug,
            contextSlug: $contextSlug,
            storage: new RedisStorage(
                connection: $connection,
                keyPrefix: $keyPrefix,
                extraOptions: $extraOptions,
            ),
            extraOptions: $extraOptions,
        );
    }

    public function isDatabase(): bool
    {
        return $this->storage instanceof DatabaseStorage;
    }

    public function asDatabase(): DatabaseStorage
    {
        if (!$this->isDatabase()) {
            throw IncompatibleStorageException::forTypeMismatch(
                $this->domainSlug,
                $this->contextSlug,
                $this->storage->getDriverType(),
                'Database'
            );
        }

        /** @var DatabaseStorage */
        return $this->storage;
    }

    public function isFilesystem(): bool
    {
        return $this->storage instanceof FileStorage;
    }

    public function asFilesystem(): FileStorage
    {
        if (!$this->isFilesystem()) {
            throw IncompatibleStorageException::forTypeMismatch(
                $this->domainSlug,
                $this->contextSlug,
                $this->storage->getDriverType(),
                'Filesystem'
            );
        }

        /** @var FileStorage */
        return $this->storage;
    }

    public function isRedis(): bool
    {
        return $this->storage instanceof RedisStorage;
    }

    public function asRedis(): RedisStorage
    {
        if (!$this->isRedis()) {
            throw IncompatibleStorageException::forTypeMismatch(
                $this->domainSlug,
                $this->contextSlug,
                $this->storage->getDriverType(),
                'Redis'
            );
        }

        /** @var RedisStorage */
        return $this->storage;
    }

    public function getIdentityKey(): string
    {
        return $this->storage->getIdentityKey();
    }

    /**
     * Backward compatibility property access for DatabaseStorage fields.
     */
    public function __get(string $name): mixed
    {
        if ($this->isDatabase()) {
            $db = $this->asDatabase();
            return match ($name) {
                'connectionName' => $db->connectionName,
                'tablePrefix' => $db->tablePrefix,
                'migrationPaths' => $db->migrationPaths,
                'autoCreateSqliteDatabase' => $db->autoCreateSqliteDatabase,
                default => throw new InvalidArgumentException("Undefined property [{$name}] on StorageContext."),
            };
        }

        throw new InvalidArgumentException("Undefined property [{$name}] on StorageContext with driver '{$this->storage->getDriverType()->value}'.");
    }

    public function __isset(string $name): bool
    {
        if ($this->isDatabase()) {
            return in_array($name, ['connectionName', 'tablePrefix', 'migrationPaths', 'autoCreateSqliteDatabase'], true);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $domainSlug = (string) ($data['domainSlug'] ?? $data['domain_slug'] ?? '');
        $contextSlug = (string) ($data['contextSlug'] ?? $data['context_slug'] ?? '');
        $extraOptions = (array) ($data['extraOptions'] ?? $data['extra_options'] ?? []);

        $storageData = isset($data['storage']) && is_array($data['storage'])
            ? $data['storage']
            : $data;

        $storage = StorageFactory::fromArray($storageData);

        return new self(
            domainSlug: $domainSlug,
            contextSlug: $contextSlug,
            storage: $storage,
            extraOptions: $extraOptions,
        );
    }

    private function validateSlug(string $field, string $value): void
    {
        if (trim($value) === '' || !preg_match('/^[a-z0-9\-_]+$/i', $value)) {
            throw new InvalidArgumentException(
                "Invalid {$field} '{$value}'. Slug must be a non-empty string containing only alphanumeric characters, dashes, and underscores."
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'domainSlug' => $this->domainSlug,
            'contextSlug' => $this->contextSlug,
            'storage' => $this->storage->toArray(),
            'extraOptions' => $this->extraOptions,
        ];
    }
}
