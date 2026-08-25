<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Storage;

use AlexKassel\DomainCore\Contracts\Storage\MigratableStorageInterface;
use AlexKassel\DomainCore\Enums\StorageDriverType;
use InvalidArgumentException;

final class DatabaseStorage implements MigratableStorageInterface
{
    /**
     * @param string $connectionName Database connection name (e.g., 'sqlite_domain_one_primary')
     * @param string $tablePrefix Table prefix (e.g., 'one_primary_')
     * @param array<int, string> $migrationPaths Absolute directory paths containing migrations
     * @param bool $autoCreateSqliteDatabase Whether to automatically create SQLite database file if missing
     * @param array<string, mixed> $extraOptions Custom metadata
     */
    public function __construct(
        public readonly string $connectionName,
        public readonly string $tablePrefix = '',
        public readonly array $migrationPaths = [],
        public readonly bool $autoCreateSqliteDatabase = false,
        public readonly array $extraOptions = [],
    ) {
        if (trim($this->connectionName) === '') {
            throw new InvalidArgumentException('Database connection name cannot be empty.');
        }

        foreach ($this->migrationPaths as $path) {
            if (!is_string($path) || trim($path) === '') {
                throw new InvalidArgumentException('Migration paths must be an array of non-empty strings.');
            }
        }
    }

    public function getDriverType(): StorageDriverType
    {
        return StorageDriverType::DATABASE;
    }

    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    public function getMigrationPaths(): array
    {
        return $this->migrationPaths;
    }

    public function shouldAutoCreateSqliteDatabase(): bool
    {
        return $this->autoCreateSqliteDatabase;
    }

    public function getIdentityKey(): string
    {
        return "database:{$this->connectionName}:{$this->tablePrefix}";
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            connectionName: (string) ($data['connectionName'] ?? $data['connection_name'] ?? $data['connection'] ?? ''),
            tablePrefix: (string) ($data['tablePrefix'] ?? $data['table_prefix'] ?? ''),
            migrationPaths: array_values(array_filter(
                (array) ($data['migrationPaths'] ?? $data['migration_paths'] ?? $data['migrations'] ?? []),
                static fn($path) => is_string($path) && trim($path) !== ''
            )),
            autoCreateSqliteDatabase: (bool) ($data['autoCreateSqliteDatabase'] ?? $data['auto_create_sqlite_database'] ?? false),
            extraOptions: (array) ($data['extraOptions'] ?? $data['extra_options'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->getDriverType()->value,
            'connectionName' => $this->connectionName,
            'tablePrefix' => $this->tablePrefix,
            'migrationPaths' => $this->migrationPaths,
            'autoCreateSqliteDatabase' => $this->autoCreateSqliteDatabase,
            'extraOptions' => $this->extraOptions,
        ];
    }
}
