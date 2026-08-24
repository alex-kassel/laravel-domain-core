<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Contracts;

use AlexKassel\DomainCore\DTOs\MigrationReport;
use AlexKassel\DomainCore\DTOs\StorageContext;

interface MigrationManagerInterface
{
    /**
     * Run migrations across registered storage contexts with optional domain/capability filtering.
     *
     * @param string|null $domainSlug
     * @param string|null $capabilitySlug
     * @param bool $force
     * @param bool $pretend
     * @return array<int, MigrationReport>
     */
    public function migrate(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        bool $force = false,
        bool $pretend = false
    ): array;

    /**
     * Rollback migrations across registered storage contexts.
     *
     * @param string|null $domainSlug
     * @param string|null $capabilitySlug
     * @param int $step
     * @param bool $force
     * @return array<int, MigrationReport>
     */
    public function rollback(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        int $step = 1,
        bool $force = false
    ): array;

    /**
     * Rollback all migrations across registered storage contexts.
     *
     * @return array<int, MigrationReport>
     */
    public function reset(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        bool $force = false
    ): array;

    /**
     * Drop all tables and re-run all migrations across registered storage contexts.
     *
     * @return array<int, MigrationReport>
     */
    public function fresh(
        ?string $domainSlug = null,
        ?string $capabilitySlug = null,
        bool $force = false
    ): array;

    /**
     * Ensure database file / database schema exists for a specific storage context.
     */
    public function ensureDatabaseExists(StorageContext $context): void;
}
