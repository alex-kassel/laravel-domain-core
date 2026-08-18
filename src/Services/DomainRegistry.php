<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\DomainContext;
use AlexKassel\DomainCore\Exceptions\DomainConnectionNotFoundException;
use AlexKassel\DomainCore\Exceptions\DomainNotFoundException;
use AlexKassel\DomainCore\Exceptions\DomainSlugCollisionException;
use AlexKassel\DomainCore\Exceptions\DomainSlugMismatchException;
use Illuminate\Database\DatabaseManager;

class DomainRegistry implements DomainRegistryInterface
{
    /**
     * @var array<string, DomainContext> Registered domain contexts keyed by slug
     */
    private array $contexts = [];

    /**
     * @var array<string, string> Map of class names to domain slugs
     */
    private array $classToSlugMap = [];

    public function __construct(
        private readonly ?DatabaseManager $db = null,
        private readonly string $appDatabasePath = ''
    ) {}

    public function register(DomainContext $context): void
    {
        $resolvedSlug = $this->resolveSlug($context);
        $finalContext = new DomainContext(
            domainSlug: $resolvedSlug,
            packageSlug: $context->packageSlug,
            connectionName: $context->connectionName,
            tablePrefix: $context->tablePrefix,
            className: $context->className,
            isEnabled: $context->isEnabled,
            autoCreateSqliteDatabase: $context->autoCreateSqliteDatabase,
            extraConfig: $context->extraConfig
        );

        $this->ensureDatabaseConnection($finalContext);

        if ($finalContext->className !== null) {
            $this->classToSlugMap[$finalContext->className] = $resolvedSlug;
            $this->validateDatabaseState($finalContext);
        }

        $this->contexts[$resolvedSlug] = $finalContext;
    }

    public function resolve(string $identifier): DomainContext
    {
        $slug = $this->classToSlugMap[$identifier] ?? $identifier;

        if (!isset($this->contexts[$slug])) {
            throw DomainNotFoundException::forSlug($identifier);
        }

        $context = $this->contexts[$slug];

        if (!$context->isEnabled) {
            throw DomainNotFoundException::forSlug($identifier);
        }

        return $context;
    }

    public function has(string $identifier): bool
    {
        $slug = $this->classToSlugMap[$identifier] ?? $identifier;

        return isset($this->contexts[$slug]) && $this->contexts[$slug]->isEnabled;
    }

    /**
     * @return array<string, DomainContext>
     */
    public function all(): array
    {
        return array_filter($this->contexts, static fn (DomainContext $c) => $c->isEnabled);
    }

    public function syncToDatabase(): void
    {
        foreach ($this->all() as $context) {
            if ($context->className !== null) {
                $this->validateDatabaseState($context);
            }
        }
    }

    public function ensureDatabaseConnection(DomainContext $context): void
    {
        if (!function_exists('config') || !app()->bound('config')) {
            return;
        }

        $config = config("database.connections.{$context->connectionName}");

        $migrationPath = (string) ($context->extraConfig['migration_path'] ?? '');
        $databasePath = isset($context->extraConfig['database_path']) ? (string) $context->extraConfig['database_path'] : null;

        $localPath = $databasePath;
        if ($localPath === null) {
            if ($migrationPath !== '') {
                $localPath = rtrim($migrationPath, '/\\') . '/../database/' . $context->connectionName . '.sqlite';
            } else {
                $localPath = (function_exists('database_path') ? database_path() : 'database') . '/' . $context->connectionName . '.sqlite';
            }
        }

        $centralPath = ($this->appDatabasePath !== '' ? $this->appDatabasePath : (function_exists('database_path') ? database_path() : 'database')) . '/' . $context->connectionName . '.sqlite';

        if ($config === null) {
            if ($context->autoCreateSqliteDatabase || str_contains($context->connectionName, 'sqlite')) {
                $targetFile = $localPath;

                if (file_exists($localPath)) {
                    $targetFile = $localPath;
                } elseif ($centralPath !== '' && file_exists($centralPath)) {
                    $targetFile = $centralPath;
                } elseif ($context->autoCreateSqliteDatabase) {
                    $targetDir = dirname($localPath);
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    touch($localPath);
                    $targetFile = $localPath;
                } else {
                    throw DomainConnectionNotFoundException::forConnection($context->connectionName, "{$localPath}, {$centralPath}");
                }

                config([
                    "database.connections.{$context->connectionName}" => [
                        'driver' => 'sqlite',
                        'database' => $targetFile,
                        'prefix' => '',
                        'foreign_key_constraints' => true,
                    ],
                ]);
            }
        } else {
            $driver = $config['driver'] ?? '';

            if ($driver === 'sqlite') {
                $database = $config['database'] ?? '';

                if ($database !== ':memory:' && !file_exists($database)) {
                    if (file_exists($localPath)) {
                        config(["database.connections.{$context->connectionName}.database" => $localPath]);
                        return;
                    }

                    if ($centralPath !== '' && file_exists($centralPath)) {
                        config(["database.connections.{$context->connectionName}.database" => $centralPath]);
                        return;
                    }

                    if ($context->autoCreateSqliteDatabase) {
                        $targetDir = dirname($localPath);
                        if (!is_dir($targetDir)) {
                            mkdir($targetDir, 0755, true);
                        }
                        touch($localPath);
                        config(["database.connections.{$context->connectionName}.database" => $localPath]);
                        return;
                    }

                    throw DomainConnectionNotFoundException::forConnection($context->connectionName, "{$localPath}, {$centralPath}");
                }
            }
        }
    }

    private function resolveSlug(DomainContext $context): string
    {
        if ($context->domainSlug !== '') {
            return $context->domainSlug;
        }

        $parts = explode('/', $context->packageSlug);

        return end($parts) ?: $context->packageSlug;
    }

    private function validateDatabaseState(DomainContext $context): void
    {
        if ($this->db === null) {
            return;
        }

        try {
            $connection = $this->db->connection($context->connectionName);

            if (!$connection->getSchemaBuilder()->hasTable('domains')) {
                return;
            }

            $existingByClass = $connection->table('domains')
                ->where('class', $context->className)
                ->first();

            if ($existingByClass !== null) {
                if ($existingByClass->slug !== $context->domainSlug) {
                    throw DomainSlugMismatchException::forMismatch(
                        (string) $context->className,
                        (string) $existingByClass->slug,
                        $context->domainSlug
                    );
                }
            } else {
                $existingBySlug = $connection->table('domains')
                    ->where('slug', $context->domainSlug)
                    ->first();

                if ($existingBySlug !== null) {
                    throw DomainSlugCollisionException::forCollision(
                        $context->domainSlug,
                        (string) $existingBySlug->class,
                        (string) $context->className
                    );
                }

                $connection->table('domains')->insert([
                    'class' => $context->className,
                    'slug' => $context->domainSlug,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (\Throwable $e) {
            if ($e instanceof DomainSlugMismatchException || $e instanceof DomainSlugCollisionException) {
                throw $e;
            }
        }
    }
}
