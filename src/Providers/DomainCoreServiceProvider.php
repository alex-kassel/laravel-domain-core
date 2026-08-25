<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Providers;

use AlexKassel\DomainCore\Console\Commands\CacheCommand;
use AlexKassel\DomainCore\Console\Commands\ClearCommand;
use AlexKassel\DomainCore\Console\Commands\MakeDomainCommand;
use AlexKassel\DomainCore\Console\Commands\MigrateCommand;
use AlexKassel\DomainCore\Console\Commands\StatusCommand;
use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\Contracts\DomainContextManagerInterface;
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\ExecutionLockManagerInterface;
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use AlexKassel\DomainCore\Services\CommandRunner;
use AlexKassel\DomainCore\Services\DatabaseProvisioner;
use AlexKassel\DomainCore\Services\DomainContextManager;
use AlexKassel\DomainCore\Services\DomainRegistry;
use AlexKassel\DomainCore\Services\ExecutionLockManager;
use AlexKassel\DomainCore\Services\MigrationManager;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

final class DomainCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DatabaseProvisioner::class, function ($app) {
            return new DatabaseProvisioner(
                files: $app->make('files'),
                events: $app->make('events')
            );
        });

        $this->app->singleton(DomainRegistryInterface::class, function ($app) {
            $registry = new DomainRegistry();

            $cachePath = $app->bootstrapPath('cache/domains.php');
            if (file_exists($cachePath)) {
                $cached = require $cachePath;
                if (is_array($cached)) {
                    $registry->loadFromCache($cached);
                }
            }

            return $registry;
        });

        $this->app->alias(DomainRegistryInterface::class, 'domain.registry');

        $this->app->singleton(DomainContextManagerInterface::class, function ($app) {
            return new DomainContextManager(
                registry: $app->make(DomainRegistryInterface::class),
                provisioner: $app->make(DatabaseProvisioner::class)
            );
        });

        $this->app->alias(DomainContextManagerInterface::class, 'domain.context');

        $this->app->singleton(MigrationManagerInterface::class, function ($app) {
            return new MigrationManager(
                app: $app,
                registry: $app->make(DomainRegistryInterface::class),
                contextManager: $app->make(DomainContextManagerInterface::class),
                provisioner: $app->make(DatabaseProvisioner::class),
                files: $app->make('files')
            );
        });

        $this->app->singleton(ExecutionLockManagerInterface::class, function ($app) {
            return new ExecutionLockManager(
                cache: $app->make('cache.store'),
                events: $app->make('events')
            );
        });

        $this->app->singleton(CommandRunnerInterface::class, function ($app) {
            return new CommandRunner(
                registry: $app->make(DomainRegistryInterface::class),
                lockManager: $app->make(ExecutionLockManagerInterface::class),
                events: $app->make('events')
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateCommand::class,
                StatusCommand::class,
                CacheCommand::class,
                ClearCommand::class,
                MakeDomainCommand::class,
            ]);
        }
    }
}
