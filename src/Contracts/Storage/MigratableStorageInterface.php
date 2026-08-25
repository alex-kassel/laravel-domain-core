<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Contracts\Storage;

interface MigratableStorageInterface extends StorageInterface
{
    public function getConnectionName(): string;

    public function getTablePrefix(): string;

    /**
     * @return array<int, string>
     */
    public function getMigrationPaths(): array;

    public function shouldAutoCreateSqliteDatabase(): bool;
}
