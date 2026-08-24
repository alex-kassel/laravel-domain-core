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
                            {--rollback : Rollback the last batch of database migrations}
                            {--reset : Rollback all database migrations}
                            {--refresh : Reset and re-run all migrations}
                            {--fresh : Drop all tables and re-run all migrations}';

    protected $description = 'Run, rollback, reset, refresh, or freshly create database migrations across domain storage contexts';

    public function handle(MigrationManagerInterface $migrationManager): int
    {
        $domain = $this->argument('domain');
        $capability = $this->option('capability');
        $force = (bool) $this->option('force');
        $pretend = (bool) $this->option('pretend');
        $isRollback = (bool) $this->option('rollback');
        $isReset = (bool) $this->option('reset');
        $isRefresh = (bool) $this->option('refresh');
        $isFresh = (bool) $this->option('fresh');
        $step = (int) ($this->option('step') ?? 1);

        $domainSlug = is_string($domain) && trim($domain) !== '' ? trim($domain) : null;
        $capabilitySlug = is_string($capability) && trim($capability) !== '' ? trim($capability) : null;

        if ($isFresh) {
            $this->info('Dropping all tables and re-migrating domain storage contexts...');
            $reports = $migrationManager->fresh($domainSlug, $capabilitySlug, $force);
        } elseif ($isRefresh) {
            $this->info('Resetting and re-migrating domain storage contexts...');
            $migrationManager->reset($domainSlug, $capabilitySlug, $force);
            $reports = $migrationManager->migrate($domainSlug, $capabilitySlug, $force, $pretend);
        } elseif ($isReset) {
            $this->info('Resetting all domain storage context migrations...');
            $reports = $migrationManager->reset($domainSlug, $capabilitySlug, $force);
        } elseif ($isRollback) {
            $this->info('Rolling back domain storage contexts...');
            $reports = $migrationManager->rollback($domainSlug, $capabilitySlug, $step, $force);
        } else {
            $this->info('Migrating domain storage contexts...');
            $reports = $migrationManager->migrate($domainSlug, $capabilitySlug, $force, $pretend);
        }

        if (empty($reports)) {
            $this->warn('No matching storage contexts found to process.');
            return self::SUCCESS;
        }

        $hasFailure = false;
        $tableRows = [];

        foreach ($reports as $report) {
            if (!$report->isSuccess() && $report->status !== 'NO_OP') {
                $hasFailure = true;
            }

            $statusFormatted = match ($report->status) {
                'SUCCESS' => '<info>SUCCESS</info>',
                'NO_OP' => '<comment>NO_OP</comment>',
                default => '<error>FAILED</error>',
            };

            $tableRows[] = [
                $report->domainSlug,
                $report->capabilitySlug,
                $report->connectionName,
                count($report->executedMigrations),
                $statusFormatted,
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
