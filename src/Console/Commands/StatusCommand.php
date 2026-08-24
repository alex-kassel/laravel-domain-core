<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use Illuminate\Console\Command;

final class StatusCommand extends Command
{
    protected $signature = 'domain:status
                            {domain? : Filter status by domain slug}
                            {--domain= : Filter status by domain slug}
                            {--domains= : Filter by comma-separated domain slugs}
                            {--capability= : Filter status by capability slug}';

    protected $description = 'Display registration status, capabilities, and storage contexts of all domains';

    public function handle(DomainRegistryInterface $registry): int
    {
        $domainArg = $this->argument('domain');
        $domainOpt = $this->option('domain');
        $domainsOpt = $this->option('domains');

        $domainFilter = $domainArg ?: ($domainOpt ?: $domainsOpt);
        $capabilityFilter = $this->option('capability');

        $domains = $registry->allDomains();

        if (empty($domains)) {
            $this->info('No domains currently registered in DomainRegistry.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($domains as $domain) {
            if ($domainFilter && $domain->slug !== $domainFilter) {
                continue;
            }

            if (empty($domain->contexts)) {
                $rows[] = [
                    $domain->slug,
                    $domain->name,
                    '<comment>(none)</comment>',
                    '-',
                    '-',
                    0,
                ];
                continue;
            }

            foreach ($domain->contexts as $capability => $context) {
                if ($capabilityFilter && $capability !== $capabilityFilter) {
                    continue;
                }

                $rows[] = [
                    $domain->slug,
                    $domain->name,
                    "<info>{$capability}</info>",
                    $context->connectionName,
                    $context->tablePrefix !== '' ? $context->tablePrefix : '<comment>(none)</comment>',
                    count($context->migrationPaths),
                ];
            }
        }

        if (empty($rows)) {
            $this->warn('No matching domains or capabilities found.');
            return self::SUCCESS;
        }

        $this->table(
            ['Domain Slug', 'Domain Name', 'Capability', 'Connection', 'Table Prefix', 'Migration Paths'],
            $rows
        );

        return self::SUCCESS;
    }
}
