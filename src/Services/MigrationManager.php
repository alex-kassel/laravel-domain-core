<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\DomainContextManagerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use AlexKassel\DomainCore\DTOs\MigrationReport;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\DatabaseProvisioningException;
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
        private readonly Filesystem $files,
    ) {}

    public function migrate(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        bool $force = false,
        bool $pretend = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $capabilitySlug);
        $reports = [];

        foreach ($contexts as $context) {
            $reports[] = $this->migrateContext($context, $pretend);
        }

        return $reports;
    }

    public function rollback(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        int $step = 1,
        bool $force = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $capabilitySlug);
        $reports = [];

        foreach ($contexts as $context) {
            $reports[] = $this->rollbackContext($context, $step);
        }

        return $reports;
    }

    public function reset(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        bool $force = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $capabilitySlug);
        $reports = [];

        foreach ($contexts as $context) {
            $reports[] = $this->resetContext($context);
        }

        return $reports;
    }

    public function fresh(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        bool $force = false
    ): array {
        $contexts = $this->resolveTargetContexts($domainSlug, $capabilitySlug);
        $reports = [];

        foreach ($contexts as $context) {
            $this->dropAllTablesForContext($context);
            $reports[] = $this->migrateContext($context, false);
        }

        return $reports;
    }

    public function ensureDatabaseExists(StorageContext $context): void
    {
        $connectionConfig = config("database.connections.{$context->connectionName}");

        // If connection not explicitly configured, try to derive SQLite default
        if ($connectionConfig === null && $context->autoCreateSqliteDatabase) {
            $dbPath = database_path("{$context->domainSlug}_{$context->capabilitySlug}.sqlite");
            config([
                "database.connections.{$context->connectionName}" => [
                    'driver' => 'sqlite',
                    'database' => $dbPath,
                    'prefix' => $context->tablePrefix,
                    'foreign_key_constraints' => true,
                ],
            ]);
            $connectionConfig = config("database.connections.{$context->connectionName}");
        }

        if ($connectionConfig !== null && ($connectionConfig['driver'] ?? null) === 'sqlite') {
            $dbPath = $connectionConfig['database'] ?? null;

            if ($dbPath !== null && $dbPath !== ':memory:' && !str_starts_with($dbPath, 'file:')) {
                $dir = dirname($dbPath);
                if (!$this->files->isDirectory($dir)) {
                    $this->files->makeDirectory($dir, 0755, true, true);
                }

                if (!$this->files->exists($dbPath)) {
                    if ($this->files->put($dbPath, '') === false) {
                        throw DatabaseProvisioningException::forConnection(
                            $context->connectionName,
                            "Could not create SQLite database file at {$dbPath}"
                        );
                    }
                }
            }
        }
    }

    /**
     * @return array<int, StorageContext>
     */
    private function resolveTargetContexts(?string $domainSlug, ?string $capabilitySlug): array
    {
        $all = $this->registry->allStorageContexts();

        return array_values(array_filter($all, static function (StorageContext $context) use ($domainSlug, $capabilitySlug) {
            if ($domainSlug !== null && $context->domainSlug !== $domainSlug) {
                return false;
            }
            if ($capabilitySlug !== null && $context->capabilitySlug !== $capabilitySlug) {
                return false;
            }
            return true;
        }));
    }

    private function migrateContext(StorageContext $context, bool $pretend): MigrationReport
    {
        $startTime = microtime(true);

        try {
            $this->ensureDatabaseExists($context);

            $migrator = $this->createMigratorForContext($context);

            // Prepare migration repository table
            if (!$migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            // Run migrations in ambient scope
            $ran = $this->contextManager->using(
                $context->domainSlug,
                $context->capabilitySlug,
                function () use ($migrator, $context, $pretend) {
                    $existingPaths = array_filter($context->migrationPaths, fn(string $path) => $this->files->isDirectory($path));

                    if (empty($existingPaths)) {
                        return [];
                    }

                    return $migrator->run($existingPaths, ['pretend' => $pretend]);
                }
            );

            $duration = round(microtime(true) - $startTime, 4);

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                capabilitySlug: $context->capabilitySlug,
                connectionName: $context->connectionName,
                executedMigrations: is_array($ran) ? array_map('strval', $ran) : [],
                durationSeconds: $duration,
                status: 'SUCCESS'
            );
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 4);

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                capabilitySlug: $context->capabilitySlug,
                connectionName: $context->connectionName,
                executedMigrations: [],
                durationSeconds: $duration,
                status: 'FAILED',
                errorMessage: $e->getMessage()
            );
        }
    }

    private function rollbackContext(StorageContext $context, int $step): MigrationReport
    {
        $startTime = microtime(true);

        try {
            $this->ensureDatabaseExists($context);
            $migrator = $this->createMigratorForContext($context);

            if (!$migrator->repositoryExists()) {
                return new MigrationReport(
                    domainSlug: $context->domainSlug,
                    capabilitySlug: $context->capabilitySlug,
                    connectionName: $context->connectionName,
                    executedMigrations: [],
                    durationSeconds: round(microtime(true) - $startTime, 4),
                    status: 'NO_OP'
                );
            }

            $rolledBack = $this->contextManager->using(
                $context->domainSlug,
                $context->capabilitySlug,
                function () use ($migrator, $context, $step) {
                    $existingPaths = array_filter($context->migrationPaths, fn(string $path) => $this->files->isDirectory($path));

                    if (empty($existingPaths)) {
                        return [];
                    }

                    return $migrator->rollback($existingPaths, ['step' => $step]);
                }
            );

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                capabilitySlug: $context->capabilitySlug,
                connectionName: $context->connectionName,
                executedMigrations: is_array($rolledBack) ? array_map('strval', $rolledBack) : [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: 'SUCCESS'
            );
        } catch (Throwable $e) {
            return new MigrationReport(
                domainSlug: $context->domainSlug,
                capabilitySlug: $context->capabilitySlug,
                connectionName: $context->connectionName,
                executedMigrations: [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: 'FAILED',
                errorMessage: $e->getMessage()
            );
        }
    }

    private function resetContext(StorageContext $context): MigrationReport
    {
        $startTime = microtime(true);

        try {
            $this->ensureDatabaseExists($context);
            $migrator = $this->createMigratorForContext($context);

            if (!$migrator->repositoryExists()) {
                return new MigrationReport(
                    domainSlug: $context->domainSlug,
                    capabilitySlug: $context->capabilitySlug,
                    connectionName: $context->connectionName,
                    executedMigrations: [],
                    durationSeconds: round(microtime(true) - $startTime, 4),
                    status: 'NO_OP'
                );
            }

            $rolledBack = $this->contextManager->using(
                $context->domainSlug,
                $context->capabilitySlug,
                function () use ($migrator, $context) {
                    $existingPaths = array_filter($context->migrationPaths, fn(string $path) => $this->files->isDirectory($path));

                    if (empty($existingPaths)) {
                        return [];
                    }

                    return $migrator->reset($existingPaths);
                }
            );

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                capabilitySlug: $context->capabilitySlug,
                connectionName: $context->connectionName,
                executedMigrations: is_array($rolledBack) ? array_map('strval', $rolledBack) : [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: 'SUCCESS'
            );
        } catch (Throwable $e) {
            return new MigrationReport(
                domainSlug: $context->domainSlug,
                capabilitySlug: $context->capabilitySlug,
                connectionName: $context->connectionName,
                executedMigrations: [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: 'FAILED',
                errorMessage: $e->getMessage()
            );
        }
    }

    private function dropAllTablesForContext(StorageContext $context): void
    {
        $this->ensureDatabaseExists($context);

        try {
            Schema::connection($context->connectionName)->dropAllTables();
            Schema::connection($context->connectionName)->dropAllViews();
        } catch (Throwable) {
            // Ignore if tables don't exist yet
        }
    }

    private function createMigratorForContext(StorageContext $context): Migrator
    {
        $table = 'migrations';
        $repository = new DatabaseMigrationRepository($this->app->make('db'), $table);
        $repository->setSource($context->connectionName);

        $migrator = new Migrator($repository, $this->app->make('db'), $this->files, $this->app->make('events'));
        $migrator->setConnection($context->connectionName);

        return $migrator;
    }
}
