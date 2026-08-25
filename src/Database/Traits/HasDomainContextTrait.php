<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Database\Traits;

use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Facades\DomainContext;
use AlexKassel\DomainCore\Facades\DomainRegistry;

trait HasDomainContextTrait
{
    /**
     * Optional explicit context name (e.g. 'primary', 'archive', 'analytics') for this model.
     * If null, uses the active ambient context.
     */
    protected ?string $explicitContext = null;

    /**
     * Optional explicit domain slug (e.g. 'domain-one') if this model is bound statically.
     */
    protected ?string $explicitDomain = null;

    /**
     * Optional base table name without dynamic prefix.
     */
    protected ?string $baseTable = null;

    /**
     * Cached resolved table name for this instance.
     */
    private ?string $resolvedTableCache = null;

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
        if ($this->resolvedTableCache !== null) {
            return $this->resolvedTableCache;
        }

        $base = $this->baseTable ?? parent::getTable();
        $context = $this->resolveStorageContextForModel();

        if ($context !== null && $context->tablePrefix !== '') {
            $connectionPrefix = (string) config("database.connections.{$context->connectionName}.prefix", '');
            if ($connectionPrefix === '' && !str_starts_with($base, $context->tablePrefix)) {
                $this->resolvedTableCache = $context->tablePrefix . $base;
                return $this->resolvedTableCache;
            }
        }

        $this->resolvedTableCache = $base;
        return $this->resolvedTableCache;
    }

    public function setTable($table): static
    {
        $this->resolvedTableCache = null;
        return parent::setTable($table);
    }

    protected function resolveStorageContextForModel(): ?StorageContext
    {
        $ambient = DomainContext::currentOrNull();

        if ($ambient !== null) {
            if ($this->explicitContext !== null && $this->explicitContext !== $ambient->contextSlug) {
                return DomainRegistry::getStorageContext($ambient->domainSlug, $this->explicitContext);
            }
            return $ambient;
        }

        if ($this->explicitDomain !== null && $this->explicitContext !== null) {
            return DomainRegistry::getStorageContext($this->explicitDomain, $this->explicitContext);
        }

        return null;
    }
}
