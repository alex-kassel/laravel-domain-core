<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

final class MigrateCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'domain:migrate
                            {domain? : The specific domain slug to migrate}
                            {--domain= : Filter by domain slug}
                            {--domains= : Filter by comma-separated domain slugs}
                            {--capability= : Filter by capability slug (e.g. scraping, normalization)}
                            {--force : Force operation to run in production or bypass confirmation}
                            {--pretend : Dump the SQL queries that would be run}
                            {--step= : The number of migrations to rollback if rollback mode}
                            {--rollback : Rollback the last batch of database migrations}
                            {--reset : Rollback all database migrations}
                            {--refresh : Reset and re-run all migrations}
                            {--fresh : Drop all tables and re-run all migrations}';

    protected $description = 'Run, rollback, reset, refresh, or freshly create database migrations across domain storage contexts';

    public function handle(MigrationManagerInterface $migrationManager): int
    {
        $domainArg = $this->argument('domain');
        $domainOpt = $this->option('domain');
        $domainsOpt = $this->option('domains');

        $domain = $domainArg ?: ($domainOpt ?: $domainsOpt);
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

        $isDestructive = $isFresh || $isRefresh || $isReset || $isRollback;

        // Production Guard
        if ($isDestructive && !$this->confirmToProceed()) {
            return self::FAILURE;
        }

        // Global Destruction Guard: If running destructive action across all domains without explicit target
        if ($isDestructive && $domainSlug === null && !$force) {
            $actionName = match (true) {
                $isFresh => 'DROP ALL TABLES and re-run migrations',
                $isRefresh => 'RESET and re-run migrations',
                $isReset => 'RESET all migrations',
                default => 'ROLLBACK migrations',
            };

            $this->warn("⚠️  WARNING: You are about to {$actionName} across ALL registered domain databases!");
            if (!$this->confirm('Are you sure you want to proceed with this global operation?', false)) {
                $this->info('Operation cancelled by operator.');
                return self::SUCCESS;
            }
        }

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
