<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\DTOs;

final class CommandOptionsDTO
{
    /**
     * @param  array<int, string>  $domains
     * @param  array<int, string>  $exceptDomains
     * @param  int  $lockTtl  Lock TTL in seconds (default: 300)
     * @param  array<string, mixed>  $extraOptions
     */
    public function __construct(
        public readonly bool $all = false,
        public readonly array $domains = [],
        public readonly array $exceptDomains = [],
        public readonly ?string $context = null,
        public readonly bool $force = false,
        public readonly bool $dryRun = false,
        public readonly int $lockTtl = 300,
        public readonly array $extraOptions = [],
    ) {
        if ($this->lockTtl <= 0) {
            throw new \InvalidArgumentException('Lock TTL must be greater than zero seconds.');
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $parseList = static function (mixed $value): array {
            if (is_array($value)) {
                $strings = array_map(static fn ($v) => trim((string) $v), $value);

                return array_values(array_filter($strings, static fn ($v) => $v !== ''));
            }
            if (is_string($value) && trim($value) !== '') {
                return array_values(array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== ''));
            }

            return [];
        };

        $rawLockTtl = $input['lock-ttl'] ?? $input['lock_ttl'] ?? $input['lockTtl'] ?? 300;

        return new self(
            all: (bool) ($input['all'] ?? false),
            domains: $parseList($input['domains'] ?? $input['domain'] ?? []),
            exceptDomains: $parseList($input['except-domains'] ?? $input['except_domains'] ?? []),
            context: isset($input['context']) && trim((string) $input['context']) !== '' ? trim((string) $input['context']) : null,
            force: (bool) ($input['force'] ?? false),
            dryRun: (bool) ($input['dry-run'] ?? $input['dry_run'] ?? false),
            lockTtl: is_numeric($rawLockTtl) ? max(1, (int) $rawLockTtl) : 300,
            extraOptions: (array) ($input['extraOptions'] ?? $input['extra_options'] ?? []),
        );
    }
}
