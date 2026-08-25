<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Storage;

use AlexKassel\DomainCore\Contracts\Storage\StorageInterface;
use AlexKassel\DomainCore\Enums\StorageDriverType;
use InvalidArgumentException;

final class FileStorage implements StorageInterface
{
    /**
     * @param string $disk Laravel filesystem disk name (e.g. 'local', 'public', 's3')
     * @param string $basePath Base relative path/prefix for domain context files (e.g. 'leasing/raw/')
     * @param array<string, mixed> $extraOptions Custom filesystem metadata
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $basePath = '',
        public readonly array $extraOptions = [],
    ) {
        if (trim($this->disk) === '') {
            throw new InvalidArgumentException('Filesystem disk name cannot be empty.');
        }
    }

    public function getDriverType(): StorageDriverType
    {
        return StorageDriverType::FILESYSTEM;
    }

    public function getIdentityKey(): string
    {
        $normalizedPath = trim($this->basePath, '/');
        return "filesystem:{$this->disk}:{$normalizedPath}";
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            disk: (string) ($data['disk'] ?? $data['diskName'] ?? $data['disk_name'] ?? 'local'),
            basePath: (string) ($data['basePath'] ?? $data['base_path'] ?? $data['path'] ?? ''),
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
            'disk' => $this->disk,
            'basePath' => $this->basePath,
            'extraOptions' => $this->extraOptions,
        ];
    }
}
