<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Storage;

use AlexKassel\DomainCore\Contracts\Storage\StorageInterface;
use AlexKassel\DomainCore\Enums\StorageDriverType;
use InvalidArgumentException;

final class StorageFactory
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): StorageInterface
    {
        $rawDriver = $data['driver'] ?? $data['driverType'] ?? $data['driver_type'] ?? null;

        if ($rawDriver instanceof StorageDriverType) {
            $driverType = $rawDriver;
        } elseif (is_string($rawDriver)) {
            $driverType = StorageDriverType::tryFrom(strtolower(trim($rawDriver)));
        } else {
            // Heuristic fallback for legacy configs: if connectionName or connection or migrations exists -> Database
            if (isset($data['connectionName']) || isset($data['connection_name']) || isset($data['connection']) || isset($data['migrationPaths']) || isset($data['migrations'])) {
                $driverType = StorageDriverType::DATABASE;
            } elseif (isset($data['disk']) || isset($data['diskName'])) {
                $driverType = StorageDriverType::FILESYSTEM;
            } else {
                $driverType = StorageDriverType::DATABASE;
            }
        }

        return match ($driverType) {
            StorageDriverType::DATABASE => DatabaseStorage::fromArray($data),
            StorageDriverType::FILESYSTEM => FileStorage::fromArray($data),
            StorageDriverType::REDIS => RedisStorage::fromArray($data),
            null => throw new InvalidArgumentException("Unknown or unsupported storage driver '{$rawDriver}'."),
        };
    }
}
