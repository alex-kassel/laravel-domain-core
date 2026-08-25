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
    ) {}

    public function addContext(StorageContext $context): self
    {
        $this->contexts[$context->contextSlug] = $context;
        return $this;
    }

    public function getContext(string $contextSlug): ?StorageContext
    {
        return $this->contexts[$contextSlug] ?? null;
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
        foreach ((array) ($data['contexts'] ?? []) as $contextSlug => $ctxData) {
            $contexts[$contextSlug] = $ctxData instanceof StorageContext ? $ctxData : StorageContext::fromArray($ctxData);
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
