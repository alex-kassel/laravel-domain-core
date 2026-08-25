<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Database\Traits;

use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Exceptions\NoActiveStorageContextException;
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
     * Static domain binding has absolute priority over ambient execution scopes.
     */
    protected ?string $explicitDomain = null;

    /**
     * Optional base table name without dynamic prefix.
     */
    protected ?string $baseTable = null;

    /**
     * Whether to require an active domain context and forbid silent fallback to root connection.
     */
    protected bool $strictDomainContext = true;

    public function getConnectionName(): ?string
    {
        $context = $this->resolveStorageContextForModel();

        if ($context !== null) {
            return $context->asDatabase()->connectionName;
        }

        if ($this->strictDomainContext) {
            throw NoActiveStorageContextException::create();
        }

        return parent::getConnectionName();
    }

    public function getTable(): string
    {
        $base = $this->baseTable ?? parent::getTable();
        $context = $this->resolveStorageContextForModel();

        if ($context !== null) {
            $db = $context->asDatabase();
            if ($db->tablePrefix !== '') {
                $connectionPrefix = (string) config("database.connections.{$db->connectionName}.prefix", '');
                if ($connectionPrefix === '') {
                    return $db->tablePrefix.$base;
                }
            }
        }

        return $base;
    }

    public function setTable($table): static
    {
        return parent::setTable($table);
    }

    protected function resolveStorageContextForModel(): ?StorageContext
    {
        // 1. Explicit domain static binding has absolute priority over ambient scopes
        if ($this->explicitDomain !== null) {
            if ($this->explicitContext !== null) {
                return DomainRegistry::getStorageContext($this->explicitDomain, $this->explicitContext);
            }

            $ambient = DomainContext::currentOrNull();
            if ($ambient !== null && $ambient->domainSlug === $this->explicitDomain) {
                return $ambient;
            }

            return null;
        }

        // 2. Ambient execution scope resolution
        $ambient = DomainContext::currentOrNull();
        if ($ambient !== null) {
            if ($this->explicitContext !== null && $this->explicitContext !== $ambient->contextSlug) {
                return DomainRegistry::getStorageContext($ambient->domainSlug, $this->explicitContext);
            }

            return $ambient;
        }

        return null;
    }
}
