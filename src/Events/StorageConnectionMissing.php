<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Events;

final class StorageConnectionMissing
{
    public function __construct(
        public readonly string $domainSlug,
        public readonly string $contextSlug,
        public readonly string $connectionName,
        public readonly bool $autoCreateSqliteDatabase,
        public readonly string $suggestedAction
    ) {}
}
