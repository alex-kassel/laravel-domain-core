<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class StorageConnectionNotFoundException extends RuntimeException implements DomainCoreExceptionInterface
{
    public static function forConnection(string $domainSlug, string $contextSlug, string $connectionName): self
    {
        return new self(
            "[PROBLEM] Database connection '{$connectionName}' requested by domain '{$domainSlug}' (context: '{$contextSlug}') is not configured. " .
            "[CAUSE] Connection '{$connectionName}' is missing from config/database.php and autoCreateSqliteDatabase is set to false. " .
            "[RESOLUTION] Either define connection '{$connectionName}' in config/database.php or set autoCreateSqliteDatabase: true in your StorageContext registration."
        );
    }
}
