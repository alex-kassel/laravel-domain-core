<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Database\Traits;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;

trait HasDomainContextTrait
{
    public function getConnectionName(): ?string
    {
        if ($this->connection) {
            return $this->connection;
        }

        if (property_exists($this, 'domainSlug') && $this->domainSlug !== null) {
            $registry = app(DomainRegistryInterface::class);
            return $registry->resolve($this->domainSlug)->connectionName;
        }

        return parent::getConnectionName();
    }

    public function getTable(): string
    {
        $table = parent::getTable();

        if (property_exists($this, 'domainSlug') && $this->domainSlug !== null) {
            $registry = app(DomainRegistryInterface::class);
            $rawPrefix = $registry->resolve($this->domainSlug)->tablePrefix;

            if ($rawPrefix !== '') {
                $normalizedPrefix = rtrim($rawPrefix, '_') . '_';

                if (!str_starts_with($table, $normalizedPrefix)) {
                    return $normalizedPrefix . $table;
                }
            }
        }

        return $table;
    }
}
