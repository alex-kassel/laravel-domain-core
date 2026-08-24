<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

final class DomainProfile
{
    /**
     * @param string $slug Unique domain slug (e.g., 'car-subscription')
     * @param string $name Human-readable domain name (e.g., 'Car Subscription')
     * @param array<string, StorageContext> $contexts Storage contexts keyed by capability slug
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
        $this->contexts[$context->capabilitySlug] = $context;
        return $this;
    }

    public function getContext(string $capabilitySlug): ?StorageContext
    {
        return $this->contexts[$capabilitySlug] ?? null;
    }

    public function hasContext(string $capabilitySlug): bool
    {
        return isset($this->contexts[$capabilitySlug]);
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
        foreach ((array) ($data['contexts'] ?? []) as $cap => $ctxData) {
            $contexts[$cap] = $ctxData instanceof StorageContext ? $ctxData : StorageContext::fromArray($ctxData);
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
        foreach ($this->contexts as $cap => $ctx) {
            $contexts[$cap] = $ctx->toArray();
        }

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'contexts' => $contexts,
            'metadata' => $this->metadata,
        ];
    }
}
