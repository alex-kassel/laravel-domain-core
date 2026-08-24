<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use Illuminate\Console\Command;

final class MigrateCommand extends Command
{
    protected $signature = 'domain:migrate
                            {domain? : The specific domain slug to migrate}
                            {--capability= : Filter by capability slug (e.g. scraping, normalization)}
                            {--force : Force operation to run in production}
                            {--pretend : Dump the SQL queries that would be run}
                            {--step= : The number of migrations to rollback if rollback mode}
                            {--rollback : Rollback migrations instead of running them}';

    protected $description = 'Run or rollback database migrations across registered domain storage contexts';

    public function handle(MigrationManagerInterface $migrationManager): int
    {
        $domain = $this->argument('domain');
        $capability = $this->option('capability');
        $force = (bool) $this->option('force');
        $pretend = (bool) $this->option('pretend');
        $isRollback = (bool) $this->option('rollback');
        $step = (int) ($this->option('step') ?? 1);

        $domainSlug = is_string($domain) && trim($domain) !== '' ? trim($domain) : null;
        $capabilitySlug = is_string($capability) && trim($capability) !== '' ? trim($capability) : null;

        $action = $isRollback ? 'Rolling back' : 'Migrating';
        $this->info("{$action} domain storage contexts...");

        $reports = $isRollback
            ? $migrationManager->rollback($domainSlug, $capabilitySlug, $step, $force)
            : $migrationManager->migrate($domainSlug, $capabilitySlug, $force, $pretend);

        if (empty($reports)) {
            $this->warn('No matching storage contexts found to process.');
            return self::SUCCESS;
        }

        $hasFailure = false;
        $tableRows = [];

        foreach ($reports as $report) {
            if (!$report->isSuccess()) {
                $hasFailure = true;
            }

            $tableRows[] = [
                $report->domainSlug,
                $report->capabilitySlug,
                $report->connectionName,
                count($report->executedMigrations),
                $report->status === 'SUCCESS' ? '<info>SUCCESS</info>' : '<error>FAILED</error>',
                $report->durationSeconds . 's',
                $report->errorMessage ?? '-',
            ];
        }

        $this->table(
            ['Domain', 'Capability', 'Connection', 'Migrations', 'Status', 'Duration', 'Error'],
            $tableRows
        );

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }
}
