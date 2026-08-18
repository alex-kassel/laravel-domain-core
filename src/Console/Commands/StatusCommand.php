<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'domain-core:status';

    protected $description = 'Display database connection, prefix, and migration status across domains.';

    public function handle(DomainRegistryInterface $registry): int
    {
        $domains = $registry->all();

        if (empty($domains)) {
            $this->warn('No domain contexts currently registered.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($domains as $domain) {
            $rows[] = [
                $domain->domainSlug,
                $domain->packageSlug,
                $domain->connectionName,
                $domain->tablePrefix ?: '(none)',
                $domain->isEnabled ? 'Yes' : 'No',
            ];
        }

        $this->table(['Domain Slug', 'Package Slug', 'Connection', 'Prefix', 'Enabled'], $rows);

        return self::SUCCESS;
    }
}
