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
        public readonly bool $autoCreateSqliteDatabase = true,
        public readonly array $extraOptions = [],
    ) {}

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
            migrationPaths: (array) ($data['migrationPaths'] ?? $data['migration_paths'] ?? []),
            autoCreateSqliteDatabase: (bool) ($data['autoCreateSqliteDatabase'] ?? $data['auto_create_sqlite_database'] ?? true),
            extraOptions: (array) ($data['extraOptions'] ?? $data['extra_options'] ?? []),
        );
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
