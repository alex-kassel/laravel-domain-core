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
                            {--domains= : Filter by comma-separated domain slugs (e.g. domain-one,domain-two)}
                            {--context= : Filter status by context slug}';

    protected $description = 'Display registration status and storage contexts of all domains';

    public function handle(DomainRegistryInterface $registry): int
    {
        $domainArg = $this->argument('domain');
        $domainOpt = $this->option('domain');
        $domainsOpt = $this->option('domains');

        $domainsList = [];
        if (is_string($domainsOpt) && trim($domainsOpt) !== '') {
            $domainsList = array_values(array_filter(array_map('trim', explode(',', $domainsOpt))));
        } elseif (is_string($domainOpt) && trim($domainOpt) !== '') {
            $domainsList = [trim($domainOpt)];
        } elseif (is_string($domainArg) && trim($domainArg) !== '') {
            $domainsList = [trim($domainArg)];
        }

        $contextFilter = $this->option('context');

        $domains = $registry->allDomains();

        if (empty($domains)) {
            $this->info('No domains currently registered in DomainRegistry.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($domains as $domain) {
            if (!empty($domainsList) && !in_array($domain->slug, $domainsList, true)) {
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

            foreach ($domain->contexts as $contextSlug => $context) {
                if ($contextFilter && $contextSlug !== $contextFilter) {
                    continue;
                }

                $rows[] = [
                    $domain->slug,
                    $domain->name,
                    "<info>{$contextSlug}</info>",
                    $context->connectionName,
                    $context->tablePrefix !== '' ? $context->tablePrefix : '<comment>(none)</comment>',
                    count($context->migrationPaths),
                ];
            }
        }

        if (empty($rows)) {
            $this->warn('No matching domains or contexts found.');
            return self::SUCCESS;
        }

        $this->table(
            ['Domain Slug', 'Domain Name', 'Context', 'Connection', 'Table Prefix', 'Migration Paths'],
            $rows
        );

        return self::SUCCESS;
    }
}
