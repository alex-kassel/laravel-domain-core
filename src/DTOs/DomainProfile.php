<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

final class DomainProfile
{
    /**
     * @param string $slug Unique domain slug (e.g., 'domain-one')
     * @param string $name Human-readable domain name (e.g., 'Domain One')
     * @param array<string, StorageContext> $contexts Storage contexts keyed by context slug
     * @param array<string, mixed> $metadata Arbitrary domain metadata
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public array $contexts = [],
        public array $metadata = [],
    ) {
        if (trim($this->slug) === '' || !preg_match('/^[a-z0-9\-_]+$/i', $this->slug)) {
            throw new \InvalidArgumentException(
                "Invalid domain slug '{$this->slug}'. Slug must be a non-empty string containing only alphanumeric characters, dashes, and underscores."
            );
        }

        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Domain name cannot be empty.');
        }

        $validatedContexts = [];
        foreach ($this->contexts as $context) {
            if (!$context instanceof StorageContext) {
                throw new \InvalidArgumentException('Contexts array must contain only StorageContext instances.');
            }
            if ($context->domainSlug !== $this->slug) {
                throw new \InvalidArgumentException(
                    "Cannot assign storage context for domain '{$context->domainSlug}' to domain profile '{$this->slug}'."
                );
            }
            $validatedContexts[$context->contextSlug] = $context;
        }
        $this->contexts = $validatedContexts;
    }

    public function addContext(StorageContext $context): self
    {
        if ($context->domainSlug !== $this->slug) {
            throw new \InvalidArgumentException(
                "Cannot add storage context for domain '{$context->domainSlug}' to domain profile '{$this->slug}'."
            );
        }

        $this->contexts[$context->contextSlug] = $context;
        return $this;
    }

    public function getContext(string $contextSlug): ?StorageContext
    {
        return $this->contexts[$contextSlug] ?? null;
    }

    public function getDatabaseContext(string $contextSlug): ?StorageContext
    {
        $context = $this->getContext($contextSlug);
        return ($context !== null && $context->isDatabase()) ? $context : null;
    }

    public function getFilesystemContext(string $contextSlug): ?StorageContext
    {
        $context = $this->getContext($contextSlug);
        return ($context !== null && $context->isFilesystem()) ? $context : null;
    }

    public function getRedisContext(string $contextSlug): ?StorageContext
    {
        $context = $this->getContext($contextSlug);
        return ($context !== null && $context->isRedis()) ? $context : null;
    }

    public function hasContext(string $contextSlug): bool
    {
        return isset($this->contexts[$contextSlug]);
    }

    /**
     * @return array<string, StorageContext>
     */
    public function allContexts(): array
    {
        return $this->contexts;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $contexts = [];
        foreach ((array) ($data['contexts'] ?? []) as $ctxData) {
            $ctx = $ctxData instanceof StorageContext ? $ctxData : StorageContext::fromArray((array) $ctxData);
            $contexts[$ctx->contextSlug] = $ctx;
        }

        return new self(
            slug: (string) ($data['slug'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            contexts: $contexts,
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $contexts = [];
        foreach ($this->contexts as $contextSlug => $ctx) {
            $contexts[$contextSlug] = $ctx->toArray();
        }

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'contexts' => $contexts,
            'metadata' => $this->metadata,
        ];
    }
}
