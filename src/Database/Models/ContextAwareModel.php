<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Database\Models;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use Illuminate\Database\Eloquent\Model;

abstract class ContextAwareModel extends Model
{
    protected ?string $domainSlug = null;

    public function getConnectionName(): ?string
    {
        if ($this->connection) {
            return $this->connection;
        }

        if ($this->domainSlug !== null) {
            $registry = app(DomainRegistryInterface::class);
            return $registry->resolve($this->domainSlug)->connectionName;
        }

        return parent::getConnectionName();
    }

    public function getTable(): string
    {
        $table = parent::getTable();

        if ($this->domainSlug !== null) {
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
