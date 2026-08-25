<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Events\StorageConnectionMissing;
use AlexKassel\DomainCore\Exceptions\DatabaseProvisioningException;
use AlexKassel\DomainCore\Exceptions\StorageConnectionNotFoundException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseProvisioner
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly Dispatcher $events,
    ) {}

    public function provision(StorageContext $context): void
    {
        if (!$context->isDatabase()) {
            return; // Non-relational storage (e.g. FileStorage, RedisStorage) does not require DB connection provisioning
        }

        $db = $context->asDatabase();
        $connectionConfig = config("database.connections.{$db->connectionName}");

        if ($connectionConfig === null) {
            if (!$db->autoCreateSqliteDatabase) {
                $suggestedAction = "Define database connection '{$db->connectionName}' in config/database.php or enable autoCreateSqliteDatabase: true in StorageContext.";
                $this->events->dispatch(new StorageConnectionMissing(
                    domainSlug: $context->domainSlug,
                    contextSlug: $context->contextSlug,
                    connectionName: $db->connectionName,
                    autoCreateSqliteDatabase: false,
                    suggestedAction: $suggestedAction,
                ));

                throw StorageConnectionNotFoundException::forConnection(
                    $context->domainSlug,
                    $context->contextSlug,
                    $db->connectionName
                );
            }

            $dbPath = database_path("{$context->domainSlug}_{$context->contextSlug}.sqlite");
            config([
                "database.connections.{$db->connectionName}" => [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                    'prefix' => $db->tablePrefix,
                    'foreign_key_constraints' => true,
                    'busy_timeout' => 5000,
                    'journal_mode' => 'WAL',
                ],
            ]);

            DB::purge($db->connectionName);
            $this->ensureSqliteFileExists($dbPath, $db->connectionName);
            return;
        }

        if (($connectionConfig['driver'] ?? null) === 'sqlite') {
            $dbPath = $connectionConfig['database'] ?? null;
            if ($dbPath !== null && $db->autoCreateSqliteDatabase) {
                $this->ensureSqliteFileExists($dbPath, $db->connectionName);
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
