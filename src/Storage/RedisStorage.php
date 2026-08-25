<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Storage;

use AlexKassel\DomainCore\Contracts\Storage\StorageInterface;
use AlexKassel\DomainCore\Enums\StorageDriverType;
use InvalidArgumentException;

final class RedisStorage implements StorageInterface
{
    /**
     * @param string $connection Redis connection name from config/database.php (e.g. 'default', 'cache')
     * @param string $keyPrefix Key prefix for domain isolation (e.g. 'leasing:queue:')
     * @param array<string, mixed> $extraOptions Custom redis metadata
     */
    public function __construct(
        public readonly string $connection = 'default',
        public readonly string $keyPrefix = '',
        public readonly array $extraOptions = [],
    ) {
        if (trim($this->connection) === '') {
            throw new InvalidArgumentException('Redis connection name cannot be empty.');
        }
    }

    public function getDriverType(): StorageDriverType
    {
        return StorageDriverType::REDIS;
    }

    public function getIdentityKey(): string
    {
        return "redis:{$this->connection}:{$this->keyPrefix}";
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            connection: (string) ($data['connection'] ?? $data['connectionName'] ?? $data['connection_name'] ?? 'default'),
            keyPrefix: (string) ($data['keyPrefix'] ?? $data['key_prefix'] ?? $data['prefix'] ?? ''),
            extraOptions: (array) ($data['extraOptions'] ?? $data['extra_options'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->getDriverType()->value,
            'connection' => $this->connection,
            'keyPrefix' => $this->keyPrefix,
            'extraOptions' => $this->extraOptions,
        ];
    }
}
