<?php

namespace AlexKassel\DomainCore\Contracts;

use AlexKassel\DomainCore\DTOs\MigrationContext;
use AlexKassel\DomainCore\DTOs\MigrationReport;

interface MigrationManagerInterface
{
    /**
     * Run pending migrations for a specific migration context.
     */
    public function migrate(MigrationContext $context): MigrationReport;

    /**
     * Run pending migrations for a registered domain slug directly.
     */
    public function migrateDomain(string $domainSlug): MigrationReport;

    /**
     * Rollback migrations for a specific migration context.
     */
    public function rollback(MigrationContext $context, int $steps = 1): MigrationReport;

    /**
     * Get current status of migrations for the specified context.
     *
     * @return array<int, MigrationReport>
     */
    public function status(MigrationContext $context): array;
}
