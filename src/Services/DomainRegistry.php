<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Services;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\DomainContext;
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
        private readonly ?DatabaseManager $db = null
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

        if ($finalContext->className !== null) {
            $this->validateDatabaseState($finalContext);
            $this->classToSlugMap[$finalContext->className] = $resolvedSlug;
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
            }
        } catch (\Throwable $e) {
            if ($e instanceof DomainSlugMismatchException || $e instanceof DomainSlugCollisionException) {
                throw $e;
            }
        }
    }
}
