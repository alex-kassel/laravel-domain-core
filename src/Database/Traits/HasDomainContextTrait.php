<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Database\Traits;

use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Facades\DomainContext;

trait HasDomainContextTrait
{
    /**
     * Optional explicit capability name (e.g. 'scraping', 'normalization') for this model.
     * If null, uses the active ambient capability.
     */
    protected ?string $explicitCapability = null;

    /**
     * Optional base table name without dynamic prefix.
     */
    protected ?string $baseTable = null;

    public function getConnectionName(): ?string
    {
        $context = $this->resolveStorageContextForModel();

        if ($context !== null) {
            return $context->connectionName;
        }

        return parent::getConnectionName();
    }

    public function getTable(): string
    {
        $base = $this->baseTable ?? parent::getTable();
        $context = $this->resolveStorageContextForModel();

        if ($context !== null && $context->tablePrefix !== '') {
            if (!str_starts_with($base, $context->tablePrefix)) {
                return $context->tablePrefix . $base;
            }
        }

        return $base;
    }

    protected function resolveStorageContextForModel(): ?StorageContext
    {
        return DomainContext::currentOrNull();
    }
}
