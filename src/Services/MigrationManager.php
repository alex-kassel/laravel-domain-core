<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use AlexKassel\DomainCore\DTOs\MigrationContext;
use AlexKassel\DomainCore\DTOs\MigrationReport;
use AlexKassel\DomainCore\Exceptions\DomainConnectionNotFoundException;
use AlexKassel\DomainCore\Exceptions\MigrationFailedException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;

class MigrationManager implements MigrationManagerInterface
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly DomainRegistryInterface $registry,
        private readonly string $appDatabasePath = ''
    ) {}

    public function migrate(MigrationContext $context): MigrationReport
    {
        $startTime = microtime(true);
        $this->ensureDatabaseConnection($context);

        $connection = $this->db->connection($context->connectionName);
        $tableName = $this->getMigrationsTableName($context);
        $this->ensureMigrationTableExists($connection, $tableName);

        $migrationFiles = $this->findMigrationFiles($context->migrationPath);
        $executedBatch = $this->getNextBatchNumber($connection, $tableName);

        $executed = [];
        $failed = [];
        $errorMessage = null;

        foreach ($migrationFiles as $file) {
            $filename = basename($file);
            $migrationKey = sprintf('%s:%s:%s', $context->packageSlug, $context->domainSlug, $filename);

            if ($this->isMigrationExecuted($connection, $tableName, $migrationKey)) {
                continue;
            }

            try {
                $this->runMigrationFile($connection, $file, 'up');

                $connection->table($tableName)->insert([
                    'migration' => $migrationKey,
                    'batch' => $executedBatch,
                ]);

                $executed[] = $filename;
            } catch (\Throwable $e) {
                $failed[] = $filename;
                $errorMessage = $e->getMessage();

                throw MigrationFailedException::withDetails(
                    $context->domainSlug,
                    $context->packageSlug,
                    $e->getMessage(),
                    $e
                );
            }
        }

        $duration = microtime(true) - $startTime;

        return new MigrationReport(
            domainSlug: $context->domainSlug,
            packageSlug: $context->packageSlug,
            connectionName: $context->connectionName,
            tablePrefix: $context->tablePrefix,
            executedMigrations: $executed,
            failedMigrations: $failed,
            errorMessage: $errorMessage,
            durationSeconds: round($duration, 4)
        );
    }

    public function migrateDomain(string $domainSlug): MigrationReport
    {
        $domain = $this->registry->resolve($domainSlug);
        $migrationPath = $domain->extraConfig['migration_path'] ?? '';
        $databasePath = $domain->extraConfig['database_path'] ?? null;

        $context = new MigrationContext(
            domainSlug: $domain->domainSlug,
            packageSlug: $domain->packageSlug,
            connectionName: $domain->connectionName,
            tablePrefix: $domain->tablePrefix,
            migrationPath: $migrationPath,
            databasePath: $databasePath,
            autoCreateDatabase: $domain->autoCreateSqliteDatabase
        );

        return $this->migrate($context);
    }

    public function rollback(MigrationContext $context, int $steps = 1): MigrationReport
    {
        $startTime = microtime(true);
        $this->ensureDatabaseConnection($context);

        $connection = $this->db->connection($context->connectionName);
        $tableName = $this->getMigrationsTableName($context);

        if (!$connection->getSchemaBuilder()->hasTable($tableName)) {
            return new MigrationReport(
                domainSlug: $context->domainSlug,
                packageSlug: $context->packageSlug,
                connectionName: $context->connectionName,
                tablePrefix: $context->tablePrefix,
                executedMigrations: []
            );
        }

        $prefixPattern = sprintf('%s:%s:%%', $context->packageSlug, $context->domainSlug);
        $rows = $connection->table($tableName)
            ->where('migration', 'like', $prefixPattern)
            ->orderBy('batch', 'desc')
            ->orderBy('migration', 'desc')
            ->limit($steps)
            ->get();

        $rolledBack = [];

        foreach ($rows as $row) {
            $keyParts = explode(':', (string) $row->migration);
            $filename = end($keyParts);
            $filePath = rtrim($context->migrationPath, '/\\') . DIRECTORY_SEPARATOR . $filename;

            if (file_exists($filePath)) {
                $this->runMigrationFile($connection, $filePath, 'down');
            }

            $connection->table($tableName)
                ->where('migration', $row->migration)
                ->delete();

            $rolledBack[] = $filename;
        }

        $duration = microtime(true) - $startTime;

        return new MigrationReport(
            domainSlug: $context->domainSlug,
            packageSlug: $context->packageSlug,
            connectionName: $context->connectionName,
            tablePrefix: $context->tablePrefix,
            executedMigrations: $rolledBack,
            durationSeconds: round($duration, 4)
        );
    }

    public function status(MigrationContext $context): array
    {
        $this->ensureDatabaseConnection($context);

        $connection = $this->db->connection($context->connectionName);
        $tableName = $this->getMigrationsTableName($context);

        $executedKeys = [];

        if ($connection->getSchemaBuilder()->hasTable($tableName)) {
            $prefixPattern = sprintf('%s:%s:%%', $context->packageSlug, $context->domainSlug);
            $executedKeys = $connection->table($tableName)
                ->where('migration', 'like', $prefixPattern)
                ->pluck('migration')
                ->all();
        }

        $migrationFiles = $this->findMigrationFiles($context->migrationPath);
        $reports = [];

        foreach ($migrationFiles as $file) {
            $filename = basename($file);
            $key = sprintf('%s:%s:%s', $context->packageSlug, $context->domainSlug, $filename);
            $isRun = in_array($key, $executedKeys, true);

            $reports[] = new MigrationReport(
                domainSlug: $context->domainSlug,
                packageSlug: $context->packageSlug,
                connectionName: $context->connectionName,
                tablePrefix: $context->tablePrefix,
                executedMigrations: $isRun ? [$filename] : [],
                failedMigrations: $isRun ? [] : [$filename]
            );
        }

        return $reports;
    }

    private function ensureDatabaseConnection(MigrationContext $context): void
    {
        $config = config("database.connections.{$context->connectionName}");
        $driver = $config['driver'] ?? '';

        if ($driver === 'sqlite') {
            $database = $config['database'] ?? '';

            if ($database !== ':memory:' && !file_exists($database)) {
                $searchedPaths = [];

                $localPath = $context->databasePath
                    ?? (rtrim($context->migrationPath, '/\\') . '/../database/' . $context->connectionName . '.sqlite');
                $searchedPaths[] = $localPath;

                $centralPath = ($this->appDatabasePath !== '' ? $this->appDatabasePath : (function_exists('database_path') ? database_path() : '')) . '/' . $context->connectionName . '.sqlite';
                $searchedPaths[] = $centralPath;

                if (file_exists($localPath)) {
                    config(["database.connections.{$context->connectionName}.database" => $localPath]);
                    return;
                }

                if ($centralPath !== '' && file_exists($centralPath)) {
                    config(["database.connections.{$context->connectionName}.database" => $centralPath]);
                    return;
                }

                if ($context->autoCreateDatabase) {
                    $targetDir = dirname($localPath);
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    touch($localPath);
                    config(["database.connections.{$context->connectionName}.database" => $localPath]);
                    return;
                }

                throw DomainConnectionNotFoundException::forConnection($context->connectionName, implode(', ', $searchedPaths));
            }
        }
    }

    private function getMigrationsTableName(MigrationContext $context): string
    {
        $prefix = rtrim($context->tablePrefix, '_');

        return $prefix !== '' ? $prefix . '_migrations' : 'migrations';
    }

    private function ensureMigrationTableExists(ConnectionInterface $connection, string $tableName): void
    {
        if (!$connection->getSchemaBuilder()->hasTable($tableName)) {
            $connection->getSchemaBuilder()->create($tableName, static function (Blueprint $table): void {
                $table->string('migration', 255)->primary();
                $table->integer('batch');
            });
        }
    }

    private function findMigrationFiles(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $files = glob(rtrim($path, '/\\') . '/*.php') ?: [];
        sort($files);

        return $files;
    }

    private function getNextBatchNumber(ConnectionInterface $connection, string $tableName): int
    {
        return ((int) $connection->table($tableName)->max('batch')) + 1;
    }

    private function isMigrationExecuted(ConnectionInterface $connection, string $tableName, string $key): bool
    {
        return $connection->table($tableName)->where('migration', $key)->exists();
    }

    private function runMigrationFile(ConnectionInterface $connection, string $file, string $method): void
    {
        $instance = require $file;

        if (is_object($instance) && method_exists($instance, $method)) {
            $instance->{$method}();
        }
    }
}
