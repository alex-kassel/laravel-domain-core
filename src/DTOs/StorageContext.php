<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

final class StorageContext
{
    /**
     * @param string $domainSlug Unique domain slug (e.g., 'domain-one')
     * @param string $contextSlug Unique context slug (e.g., 'primary', 'archive', 'analytics')
     * @param string $connectionName Database connection name (e.g., 'sqlite_domain_one_primary')
     * @param string $tablePrefix Table prefix (e.g., 'one_primary_')
     * @param array<int, string> $migrationPaths Absolute directory paths containing migrations
     * @param bool $autoCreateSqliteDatabase Whether to automatically create SQLite database file if missing
     * @param array<string, mixed> $extraOptions Custom metadata for context storage
     */
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $contextSlug,
        public readonly string $connectionName,
        public readonly string $tablePrefix = '',
        public readonly array $migrationPaths = [],
        public readonly bool $autoCreateSqliteDatabase = false,
        public readonly array $extraOptions = [],
    ) {
        $this->validateSlug('domainSlug', $this->domainSlug);
        $this->validateSlug('contextSlug', $this->contextSlug);

        if (trim($this->connectionName) === '') {
            throw new \InvalidArgumentException('Database connection name cannot be empty.');
        }

        foreach ($this->migrationPaths as $path) {
            if (!is_string($path) || trim($path) === '') {
                throw new \InvalidArgumentException('Migration paths must be an array of non-empty strings.');
            }
        }
    }

    public function getIdentityKey(): string
    {
        return "{$this->connectionName}:{$this->tablePrefix}";
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            domainSlug: (string) ($data['domainSlug'] ?? $data['domain_slug'] ?? ''),
            contextSlug: (string) ($data['contextSlug'] ?? $data['context_slug'] ?? ''),
            connectionName: (string) ($data['connectionName'] ?? $data['connection_name'] ?? ''),
            tablePrefix: (string) ($data['tablePrefix'] ?? $data['table_prefix'] ?? ''),
            migrationPaths: array_values(array_filter(
                (array) ($data['migrationPaths'] ?? $data['migration_paths'] ?? []),
                static fn($path) => is_string($path) && trim($path) !== ''
            )),
            autoCreateSqliteDatabase: (bool) ($data['autoCreateSqliteDatabase'] ?? $data['auto_create_sqlite_database'] ?? false),
            extraOptions: (array) ($data['extraOptions'] ?? $data['extra_options'] ?? []),
        );
    }

    private function validateSlug(string $field, string $value): void
    {
        if (trim($value) === '' || !preg_match('/^[a-z0-9\-_]+$/i', $value)) {
            throw new \InvalidArgumentException(
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
            'connectionName' => $this->connectionName,
            'tablePrefix' => $this->tablePrefix,
            'migrationPaths' => $this->migrationPaths,
            'autoCreateSqliteDatabase' => $this->autoCreateSqliteDatabase,
            'extraOptions' => $this->extraOptions,
        ];
    }
}
