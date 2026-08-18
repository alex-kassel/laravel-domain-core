<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use Illuminate\Console\Command;

class MigrateCommand extends Command
{
    protected $signature = 'domain-core:migrate {--domain= : Specific domain slug to migrate}';

    protected $description = 'Execute database migrations across all or specified registered domains.';

    public function handle(DomainRegistryInterface $registry, MigrationManagerInterface $manager): int
    {
        $domainSlug = $this->option('domain');

        if ($domainSlug) {
            $this->info("Migrating domain [{$domainSlug}]...");
            $report = $manager->migrateDomain($domainSlug);
            $this->info(sprintf('Domain [%s]: %d migration(s) executed in %.2fs.', $domainSlug, count($report->executedMigrations), $report->durationSeconds));
            return self::SUCCESS;
        }

        $domains = $registry->all();
        $this->info(sprintf('Migrating %d domain(s)...', count($domains)));

        foreach ($domains as $domain) {
            $report = $manager->migrateDomain($domain->domainSlug);
            $this->info(sprintf('  - [%s]: %d migration(s) executed.', $domain->domainSlug, count($report->executedMigrations)));
        }

        $this->info('All domain migrations completed.');

        return self::SUCCESS;
    }
}
