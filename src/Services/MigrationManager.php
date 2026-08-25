<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\DomainContextManagerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use AlexKassel\DomainCore\DTOs\MigrationReport;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Enums\MigrationStatus;
use AlexKassel\DomainCore\Exceptions\DomainNotFoundException;
use AlexKassel\DomainCore\Exceptions\IncompatibleStorageException;
use AlexKassel\DomainCore\Exceptions\MigrationExecutionException;
use AlexKassel\DomainCore\Exceptions\StorageContextNotFoundException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class MigrationManager implements MigrationManagerInterface
{
    public function __construct(
        private readonly Application $app,
        private readonly DomainRegistryInterface $registry,
        private readonly DomainContextManagerInterface $contextManager,
        private readonly DatabaseProvisioner $provisioner,
        private readonly Filesystem $files,
    ) {}

    public function migrate(
        ?string $domainSlug = null,
        ?string $contextSlug = null,
        bool $force = false,
        bool $pretend = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $contextSlug);
        $reports = [];

        foreach ($contexts as $context) {
            $reports[] = $this->migrateContext($context, $force, $pretend);
        }

        return $reports;
    }

    public function rollback(
        ?string $domainSlug = null,
        ?string $contextSlug = null,
        int $step = 1,
        bool $force = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $contextSlug);
        $reports = [];

        foreach ($contexts as $context) {
            $reports[] = $this->rollbackContext($context, $step, $force);
        }

        return $reports;
    }

    public function reset(
        ?string $domainSlug = null,
        ?string $contextSlug = null,
        bool $force = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $contextSlug);
        $reports = [];

        foreach ($contexts as $context) {
            $reports[] = $this->resetContext($context, $force);
        }

        return $reports;
    }

    public function fresh(
        ?string $domainSlug = null,
        ?string $contextSlug = null,
        bool $force = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $contextSlug);
        $reports = [];

        foreach ($contexts as $context) {
            $this->dropAllTablesForContext($context);
            $reports[] = $this->migrateContext($context, $force, false);
        }

        return $reports;
    }

    public function ensureDatabaseExists(StorageContext $context): void
    {
        $this->provisioner->provision($context);
    }

    /**
     * @return array<int, StorageContext>
     */
    private function resolveTargetContexts(?string $domainSlug, ?string $contextSlug): array
    {
        if ($domainSlug !== null && !$this->registry->hasDomain($domainSlug)) {
            throw DomainNotFoundException::forSlug($domainSlug);
        }

        if ($domainSlug !== null && $contextSlug !== null) {
            $context = $this->registry->getStorageContext($domainSlug, $contextSlug);
            if (!$context->isDatabase()) {
                throw IncompatibleStorageException::forTypeMismatch(
                    $domainSlug,
                    $contextSlug,
                    $context->storage->getDriverType(),
                    'Database'
                );
            }
            return [$context];
        }

        $all = $this->registry->allStorageContexts();

        return array_values(array_filter($all, static function (StorageContext $context) use ($domainSlug, $contextSlug) {
            if (!$context->isDatabase()) {
                return false; // Skip non-relational storages (FileStorage, RedisStorage) from automated migrations
            }
            if ($domainSlug !== null && $context->domainSlug !== $domainSlug) {
                return false;
            }
            if ($contextSlug !== null && $context->contextSlug !== $contextSlug) {
                return false;
            }
            return true;
        }));
    }

    private function migrateContext(StorageContext $context, bool $force, bool $pretend): MigrationReport
    {
        $startTime = microtime(true);
        $db = $context->asDatabase();

        try {
            $this->ensureDatabaseExists($context);

            foreach ($db->migrationPaths as $path) {
                if (!$this->files->isDirectory($path)) {
                    throw MigrationExecutionException::forContext(
                        $context->domainSlug,
                        $context->contextSlug,
                        "Migration directory [{$path}] does not exist on filesystem."
                    );
                }
            }

            $connection = $this->app->make('db')->connection($db->connectionName);
            $previousPrefix = $connection->getTablePrefix();
            $connection->setTablePrefix($db->tablePrefix);

            try {
                $migrator = $this->createMigratorForContext($context);

                // Prepare migration repository table
                if (!$migrator->repositoryExists()) {
                    $migrator->getRepository()->createRepository();
                }

                // Run migrations in ambient scope
                $ran = $this->contextManager->using(
                    $context->domainSlug,
                    $context->contextSlug,
                    function () use ($migrator, $db, $pretend) {
                        if (empty($db->migrationPaths)) {
                            return [];
                        }

                        return $migrator->run($db->migrationPaths, ['pretend' => $pretend, 'step' => false]);
                    }
                );
            } finally {
                $connection->setTablePrefix($previousPrefix);
            }

            $duration = round(microtime(true) - $startTime, 4);

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $db->connectionName,
                executedMigrations: array_map('strval', (array) $ran),
                durationSeconds: $duration,
                status: MigrationStatus::SUCCESS
            );
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 4);

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $db->connectionName,
                executedMigrations: [],
                durationSeconds: $duration,
                status: MigrationStatus::FAILED,
                errorMessage: $e->getMessage()
            );
        }
    }

    private function rollbackContext(StorageContext $context, int $step, bool $force): MigrationReport
    {
        $startTime = microtime(true);
        $db = $context->asDatabase();

        try {
            $this->ensureDatabaseExists($context);

            foreach ($db->migrationPaths as $path) {
                if (!$this->files->isDirectory($path)) {
                    throw MigrationExecutionException::forContext(
                        $context->domainSlug,
                        $context->contextSlug,
                        "Migration directory [{$path}] does not exist on filesystem."
                    );
                }
            }

            $connection = $this->app->make('db')->connection($db->connectionName);
            $previousPrefix = $connection->getTablePrefix();
            $connection->setTablePrefix($db->tablePrefix);

            try {
                $migrator = $this->createMigratorForContext($context);

                if (!$migrator->repositoryExists()) {
                    return new MigrationReport(
                        domainSlug: $context->domainSlug,
                        contextSlug: $context->contextSlug,
                        connectionName: $db->connectionName,
                        executedMigrations: [],
                        durationSeconds: round(microtime(true) - $startTime, 4),
                        status: MigrationStatus::NO_OP
                    );
                }

                $rolledBack = $this->contextManager->using(
                    $context->domainSlug,
                    $context->contextSlug,
                    function () use ($migrator, $db, $step) {
                        if (empty($db->migrationPaths)) {
                            return [];
                        }

                        return $migrator->rollback($db->migrationPaths, ['step' => $step]);
                    }
                );
            } finally {
                $connection->setTablePrefix($previousPrefix);
            }

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $db->connectionName,
                executedMigrations: array_map('strval', (array) $rolledBack),
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: MigrationStatus::SUCCESS
            );
        } catch (Throwable $e) {
            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $db->connectionName,
                executedMigrations: [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: MigrationStatus::FAILED,
                errorMessage: $e->getMessage()
            );
        }
    }

    private function resetContext(StorageContext $context, bool $force): MigrationReport
    {
        $startTime = microtime(true);
        $db = $context->asDatabase();

        try {
            $this->ensureDatabaseExists($context);

            foreach ($db->migrationPaths as $path) {
                if (!$this->files->isDirectory($path)) {
                    throw MigrationExecutionException::forContext(
                        $context->domainSlug,
                        $context->contextSlug,
                        "Migration directory [{$path}] does not exist on filesystem."
                    );
                }
            }

            $connection = $this->app->make('db')->connection($db->connectionName);
            $previousPrefix = $connection->getTablePrefix();
            $connection->setTablePrefix($db->tablePrefix);

            try {
                $migrator = $this->createMigratorForContext($context);

                if (!$migrator->repositoryExists()) {
                    return new MigrationReport(
                        domainSlug: $context->domainSlug,
                        contextSlug: $context->contextSlug,
                        connectionName: $db->connectionName,
                        executedMigrations: [],
                        durationSeconds: round(microtime(true) - $startTime, 4),
                        status: MigrationStatus::NO_OP
                    );
                }

                $rolledBack = $this->contextManager->using(
                    $context->domainSlug,
                    $context->contextSlug,
                    function () use ($migrator, $db) {
                        if (empty($db->migrationPaths)) {
                            return [];
                        }

                        return $migrator->reset($db->migrationPaths);
                    }
                );
            } finally {
                $connection->setTablePrefix($previousPrefix);
            }

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $db->connectionName,
                executedMigrations: array_map('strval', (array) $rolledBack),
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: MigrationStatus::SUCCESS
            );
        } catch (Throwable $e) {
            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $db->connectionName,
                executedMigrations: [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: MigrationStatus::FAILED,
                errorMessage: $e->getMessage()
            );
        }
    }

    private function dropAllTablesForContext(StorageContext $context): void
    {
        $this->ensureDatabaseExists($context);
        $db = $context->asDatabase();
        $connection = $this->app->make('db')->connection($db->connectionName);
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF;');
            /** @var array<int, object{name: string, type: string}> $rows */
            $rows = $connection->select("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'");
            foreach ($rows as $row) {
                $name = $row->name;
                $type = strtoupper((string) $row->type);
                if ($db->tablePrefix === '' || str_starts_with($name, $db->tablePrefix)) {
                    $connection->statement("DROP {$type} IF EXISTS \"{$name}\"");
                }
            }
            $connection->statement('PRAGMA foreign_keys = ON;');
            return;
        }

        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS = 0;');
            if ($db->tablePrefix !== '') {
                $escapedPrefix = addcslashes($db->tablePrefix, '%_');
                /** @var array<int, object> $rows */
                $rows = $connection->select("SHOW TABLES LIKE '{$escapedPrefix}%'");
                foreach ($rows as $row) {
                    $vals = array_values((array) $row);
                    $name = (string) $vals[0];
                    $connection->statement("DROP TABLE IF EXISTS `{$name}`");
                }
            } else {
                $schema = Schema::connection($db->connectionName);
                $schema->dropAllViews();
                $schema->dropAllTables();
            }
            $connection->statement('SET FOREIGN_KEY_CHECKS = 1;');
            return;
        }

        if ($driver === 'pgsql') {
            $connection->statement('SET CONSTRAINTS ALL DEFERRED;');
            if ($db->tablePrefix !== '') {
                $escapedPrefix = addcslashes($db->tablePrefix, '%_');
                /** @var array<int, object{tablename: string}> $rows */
                $rows = $connection->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename LIKE ?", ["{$escapedPrefix}%"]);
                foreach ($rows as $row) {
                    $name = $row->tablename;
                    $connection->statement("DROP TABLE IF EXISTS \"{$name}\" CASCADE");
                }
            } else {
                $schema = Schema::connection($db->connectionName);
                $schema->dropAllViews();
                $schema->dropAllTables();
            }
            return;
        }

        Schema::connection($db->connectionName)->dropAllTables();
    }

    private function createMigratorForContext(StorageContext $context): Migrator
    {
        $db = $context->asDatabase();
        $table = $db->tablePrefix !== ''
            ? 'migrations'
            : "migrations_{$context->domainSlug}_{$context->contextSlug}";

        $repository = new DatabaseMigrationRepository($this->app->make('db'), $table);
        $repository->setSource($db->connectionName);

        $migrator = new Migrator($repository, $this->app->make('db'), $this->files, $this->app->make('events'));
        $migrator->setConnection($db->connectionName);

        return $migrator;
    }
}
