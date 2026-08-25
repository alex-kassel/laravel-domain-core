<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

final class CommandOptionsDTO
{
    /**
     * @param bool $all
     * @param array<int, string> $domains
     * @param array<int, string> $exceptDomains
     * @param string|null $context
     * @param bool $force
     * @param bool $dryRun
     * @param array<string, mixed> $extraOptions
     */
    public function __construct(
        public readonly bool $all = false,
        public readonly array $domains = [],
        public readonly array $exceptDomains = [],
        public readonly ?string $context = null,
        public readonly bool $force = false,
        public readonly bool $dryRun = false,
        public readonly array $extraOptions = [],
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $parseList = static function (mixed $value): array {
            if (is_array($value)) {
                return array_values(array_filter(array_map('trim', $value)));
            }
            if (is_string($value) && trim($value) !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $value))));
            }
            return [];
        };

        return new self(
            all: (bool) ($input['all'] ?? false),
            domains: $parseList($input['domains'] ?? $input['domain'] ?? []),
            exceptDomains: $parseList($input['except-domains'] ?? $input['except_domains'] ?? []),
            context: isset($input['context']) && trim((string) $input['context']) !== '' ? trim((string) $input['context']) : null,
            force: (bool) ($input['force'] ?? false),
            dryRun: (bool) ($input['dry-run'] ?? $input['dry_run'] ?? false),
            extraOptions: (array) ($input['extraOptions'] ?? $input['extra_options'] ?? []),
        );
    }
}
