<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Providers;

use AlexKassel\DomainCore\Console\Commands\CacheCommand;
use AlexKassel\DomainCore\Console\Commands\ClearCommand;
use AlexKassel\DomainCore\Console\Commands\MakeDomainCommand;
use AlexKassel\DomainCore\Console\Commands\MigrateCommand;
use AlexKassel\DomainCore\Console\Commands\StatusCommand;
use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use AlexKassel\DomainCore\Services\CommandRunner;
use AlexKassel\DomainCore\Services\DomainRegistry;
use AlexKassel\DomainCore\Services\ExecutionLockManager;
use AlexKassel\DomainCore\Services\MigrationManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

class DomainCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DomainRegistryInterface::class, static function ($app): DomainRegistryInterface {
            $db = $app->bound(DatabaseManager::class) ? $app->make(DatabaseManager::class) : null;

            return new DomainRegistry($db);
        });

        $this->app->singleton(MigrationManagerInterface::class, static function ($app): MigrationManagerInterface {
            $db = $app->make(DatabaseManager::class);
            $registry = $app->make(DomainRegistryInterface::class);

            return new MigrationManager($db, $registry);
        });

        $this->app->singleton(ExecutionLockManagerInterface::class, ExecutionLockManager::class);
        $this->app->singleton(CommandRunnerInterface::class, CommandRunner::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheCommand::class,
                ClearCommand::class,
                MigrateCommand::class,
                StatusCommand::class,
                MakeDomainCommand::class,
            ]);
        }
    }
}
