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

        if ($domainSlug !== null && $contextSlug !== null && !$this->registry->hasStorageContext($domainSlug, $contextSlug)) {
            throw StorageContextNotFoundException::forContext($domainSlug, $contextSlug);
        }

        $all = $this->registry->allStorageContexts();

        return array_values(array_filter($all, static function (StorageContext $context) use ($domainSlug, $contextSlug) {
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

        try {
            $this->ensureDatabaseExists($context);

            foreach ($context->migrationPaths as $path) {
                if (!$this->files->isDirectory($path)) {
                    throw MigrationExecutionException::forContext(
                        $context->domainSlug,
                        $context->contextSlug,
                        "Migration directory [{$path}] does not exist on filesystem."
                    );
                }
            }

            $migrator = $this->createMigratorForContext($context);

            // Prepare migration repository table
            if (!$migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            // Run migrations in ambient scope
            $ran = $this->contextManager->using(
                $context->domainSlug,
                $context->contextSlug,
                function () use ($migrator, $context, $force, $pretend) {
                    if (empty($context->migrationPaths)) {
                        return [];
                    }

                    return $migrator->run($context->migrationPaths, ['pretend' => $pretend, 'step' => false]);
                }
            );

            $duration = round(microtime(true) - $startTime, 4);

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $context->connectionName,
                executedMigrations: is_array($ran) ? array_map('strval', $ran) : [],
                durationSeconds: $duration,
                status: MigrationStatus::SUCCESS
            );
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 4);

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $context->connectionName,
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

        try {
            $this->ensureDatabaseExists($context);

            foreach ($context->migrationPaths as $path) {
                if (!$this->files->isDirectory($path)) {
                    throw MigrationExecutionException::forContext(
                        $context->domainSlug,
                        $context->contextSlug,
                        "Migration directory [{$path}] does not exist on filesystem."
                    );
                }
            }

            $migrator = $this->createMigratorForContext($context);

            if (!$migrator->repositoryExists()) {
                return new MigrationReport(
                    domainSlug: $context->domainSlug,
                    contextSlug: $context->contextSlug,
                    connectionName: $context->connectionName,
                    executedMigrations: [],
                    durationSeconds: round(microtime(true) - $startTime, 4),
                    status: MigrationStatus::NO_OP
                );
            }

            $rolledBack = $this->contextManager->using(
                $context->domainSlug,
                $context->contextSlug,
                function () use ($migrator, $context, $step) {
                    if (empty($context->migrationPaths)) {
                        return [];
                    }

                    return $migrator->rollback($context->migrationPaths, ['step' => $step]);
                }
            );

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $context->connectionName,
                executedMigrations: is_array($rolledBack) ? array_map('strval', $rolledBack) : [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: MigrationStatus::SUCCESS
            );
        } catch (Throwable $e) {
            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $context->connectionName,
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

        try {
            $this->ensureDatabaseExists($context);

            foreach ($context->migrationPaths as $path) {
                if (!$this->files->isDirectory($path)) {
                    throw MigrationExecutionException::forContext(
                        $context->domainSlug,
                        $context->contextSlug,
                        "Migration directory [{$path}] does not exist on filesystem."
                    );
                }
            }

            $migrator = $this->createMigratorForContext($context);

            if (!$migrator->repositoryExists()) {
                return new MigrationReport(
                    domainSlug: $context->domainSlug,
                    contextSlug: $context->contextSlug,
                    connectionName: $context->connectionName,
                    executedMigrations: [],
                    durationSeconds: round(microtime(true) - $startTime, 4),
                    status: MigrationStatus::NO_OP
                );
            }

            $rolledBack = $this->contextManager->using(
                $context->domainSlug,
                $context->contextSlug,
                function () use ($migrator, $context) {
                    if (empty($context->migrationPaths)) {
                        return [];
                    }

                    return $migrator->reset($context->migrationPaths);
                }
            );

            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $context->connectionName,
                executedMigrations: is_array($rolledBack) ? array_map('strval', $rolledBack) : [],
                durationSeconds: round(microtime(true) - $startTime, 4),
                status: MigrationStatus::SUCCESS
            );
        } catch (Throwable $e) {
            return new MigrationReport(
                domainSlug: $context->domainSlug,
                contextSlug: $context->contextSlug,
                connectionName: $context->connectionName,
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

        if ($context->tablePrefix !== '') {
            $connection = $this->app->make('db')->connection($context->connectionName);
            $schema = Schema::connection($context->connectionName);
            $tables = $schema->getTableListing();
            $contextTables = array_filter($tables, static fn(string $tbl) => str_starts_with($tbl, $context->tablePrefix));

            if (!empty($contextTables)) {
                $driver = $connection->getDriverName();
                if ($driver === 'sqlite') {
                    $connection->statement('PRAGMA foreign_keys = OFF;');
                } elseif ($driver === 'mysql') {
                    $connection->statement('SET FOREIGN_KEY_CHECKS = 0;');
                } elseif ($driver === 'pgsql') {
                    $connection->statement('SET CONSTRAINTS ALL DEFERRED;');
                }

                foreach ($contextTables as $tbl) {
                    $schema->dropIfExists($tbl);
                }

                if ($driver === 'sqlite') {
                    $connection->statement('PRAGMA foreign_keys = ON;');
                } elseif ($driver === 'mysql') {
                    $connection->statement('SET FOREIGN_KEY_CHECKS = 1;');
                }
            }
            return;
        }

        Schema::connection($context->connectionName)->dropAllTables();
        Schema::connection($context->connectionName)->dropAllViews();
    }

    private function createMigratorForContext(StorageContext $context): Migrator
    {
        $table = $context->tablePrefix !== ''
            ? "{$context->tablePrefix}migrations"
            : "migrations_{$context->domainSlug}_{$context->contextSlug}";

        $repository = new DatabaseMigrationRepository($this->app->make('db'), $table);
        $repository->setSource($context->connectionName);

        $migrator = new Migrator($repository, $this->app->make('db'), $this->files, $this->app->make('events'));
        $migrator->setConnection($context->connectionName);

        return $migrator;
    }
}
