<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Contracts\Storage;

use AlexKassel\DomainCore\Enums\StorageDriverType;

interface StorageInterface
{
    /**
     * Get the strongly-typed driver type.
     */
    public function getDriverType(): StorageDriverType;

    /**
     * Get unique identity key for cross-domain collision detection.
     */
    public function getIdentityKey(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
