<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Events\StorageConnectionMissing;
use AlexKassel\DomainCore\Exceptions\DatabaseProvisioningException;
use AlexKassel\DomainCore\Exceptions\StorageConnectionNotFoundException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Throwable;

final class DatabaseProvisioner
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Dispatcher $events,
    ) {}

    public function provision(StorageContext $context): void
    {
        $connectionConfig = config("database.connections.{$context->connectionName}");

        if ($connectionConfig === null) {
            if (!$context->autoCreateSqliteDatabase) {
                $suggestedAction = "Define database connection '{$context->connectionName}' in config/database.php or enable autoCreateSqliteDatabase: true in StorageContext.";
                $this->events->dispatch(new StorageConnectionMissing(
                    domainSlug: $context->domainSlug,
                    contextSlug: $context->contextSlug,
                    connectionName: $context->connectionName,
                    autoCreateSqliteDatabase: false,
                    suggestedAction: $suggestedAction,
                ));

                throw StorageConnectionNotFoundException::forConnection(
                    $context->domainSlug,
                    $context->contextSlug,
                    $context->connectionName
                );
            }

            $dbPath = database_path("{$context->domainSlug}_{$context->contextSlug}.sqlite");
            config([
                "database.connections.{$context->connectionName}" => [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                    'prefix' => $context->tablePrefix,
                    'foreign_key_constraints' => true,
                ],
            ]);

            $this->ensureSqliteFileExists($dbPath, $context->connectionName);
            return;
        }

        if (($connectionConfig['driver'] ?? null) === 'sqlite') {
            $dbPath = $connectionConfig['database'] ?? null;
            if ($dbPath !== null && $context->autoCreateSqliteDatabase) {
                $this->ensureSqliteFileExists($dbPath, $context->connectionName);
            }
        }
    }

    private function ensureSqliteFileExists(string $dbPath, string $connectionName): void
    {
        if ($dbPath === ':memory:' || str_starts_with($dbPath, 'file:')) {
            return;
        }

        try {
            $dir = dirname($dbPath);
            if (!$this->files->isDirectory($dir)) {
                $this->files->makeDirectory($dir, 0755, true, true);
            }

            if (!$this->files->exists($dbPath)) {
                if ($this->files->put($dbPath, '') === false) {
                    throw DatabaseProvisioningException::forConnection(
                        $connectionName,
                        "Could not create SQLite database file at '{$dbPath}'"
                    );
                }
            }
        } catch (Throwable $e) {
            if ($e instanceof DatabaseProvisioningException) {
                throw $e;
            }
            throw DatabaseProvisioningException::forConnection(
                $connectionName,
                "Filesystem error creating SQLite database file at '{$dbPath}': {$e->getMessage()}",
                $e
            );
        }
    }
}
