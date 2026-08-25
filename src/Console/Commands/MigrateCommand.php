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
                            {--domains= : Filter by comma-separated domain slugs (e.g. domain-one,domain-two)}
                            {--context= : Filter by context slug (e.g. primary, archive, analytics)}
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

        $domainsList = [];
        if (is_string($domainsOpt) && trim($domainsOpt) !== '') {
            $domainsList = array_values(array_filter(array_map('trim', explode(',', $domainsOpt))));
        } elseif (is_string($domainOpt) && trim($domainOpt) !== '') {
            $domainsList = [trim($domainOpt)];
        } elseif (is_string($domainArg) && trim($domainArg) !== '') {
            $domainsList = [trim($domainArg)];
        }

        $context = $this->option('context');
        $force = (bool) $this->option('force');
        $pretend = (bool) $this->option('pretend');
        $isRollback = (bool) $this->option('rollback');
        $isReset = (bool) $this->option('reset');
        $isRefresh = (bool) $this->option('refresh');
        $isFresh = (bool) $this->option('fresh');
        $step = (int) ($this->option('step') ?? 1);

        $contextSlug = is_string($context) && trim($context) !== '' ? trim($context) : null;
        $isDestructive = $isFresh || $isRefresh || $isReset || $isRollback;

        // Production Guard
        if ($isDestructive && !$this->confirmToProceed()) {
            return self::FAILURE;
        }

        // Global Destruction Guard: If running destructive action across all domains without explicit target
        if ($isDestructive && empty($domainsList) && !$force) {
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

        $targetSlugs = !empty($domainsList) ? $domainsList : [null];
        $allReports = [];

        foreach ($targetSlugs as $targetSlug) {
            if ($isFresh) {
                $this->info('Dropping all tables and re-migrating domain storage contexts' . ($targetSlug ? " for [{$targetSlug}]..." : '...'));
                $reports = $migrationManager->fresh($targetSlug, $contextSlug, $force);
            } elseif ($isRefresh) {
                $this->info('Resetting and re-migrating domain storage contexts' . ($targetSlug ? " for [{$targetSlug}]..." : '...'));
                $migrationManager->reset($targetSlug, $contextSlug, $force);
                $reports = $migrationManager->migrate($targetSlug, $contextSlug, $force, $pretend);
            } elseif ($isReset) {
                $this->info('Resetting all domain storage context migrations' . ($targetSlug ? " for [{$targetSlug}]..." : '...'));
                $reports = $migrationManager->reset($targetSlug, $contextSlug, $force);
            } elseif ($isRollback) {
                $this->info('Rolling back domain storage contexts' . ($targetSlug ? " for [{$targetSlug}]..." : '...'));
                $reports = $migrationManager->rollback($targetSlug, $contextSlug, $step, $force);
            } else {
                $this->info('Migrating domain storage contexts' . ($targetSlug ? " for [{$targetSlug}]..." : '...'));
                $reports = $migrationManager->migrate($targetSlug, $contextSlug, $force, $pretend);
            }

            foreach ($reports as $r) {
                $allReports[] = $r;
            }
        }

        if (empty($allReports)) {
            $this->warn('No matching storage contexts found to process.');
            return self::SUCCESS;
        }

        $hasFailure = false;
        $tableRows = [];

        foreach ($allReports as $report) {
            if (!$report->isSuccess() && $report->status->value !== 'NO_OP') {
                $hasFailure = true;
            }

            $statusFormatted = match ($report->status->value) {
                'SUCCESS' => '<info>SUCCESS</info>',
                'NO_OP' => '<comment>NO_OP</comment>',
                default => '<error>FAILED</error>',
            };

            $tableRows[] = [
                $report->domainSlug,
                $report->contextSlug,
                $report->connectionName,
                count($report->executedMigrations),
                $statusFormatted,
                $report->durationSeconds . 's',
                $report->errorMessage ?? '-',
            ];
        }

        $this->table(
            ['Domain', 'Context', 'Connection', 'Migrations', 'Status', 'Duration', 'Error'],
            $tableRows
        );

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }
}
