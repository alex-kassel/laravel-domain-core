<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Feature;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class ConsoleCommandsTest extends TestCase
{
    public function test_domain_status_command(): void
    {
        $registry = $this->app->make(DomainRegistryInterface::class);

        $registry->registerDomain('domain-one', 'Domain One');
        $registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_one_primary',
            tablePrefix: 'one_primary_',
        ));

        $this->artisan('domain:status')
            ->assertSuccessful()
            ->expectsTable(
                ['Domain Slug', 'Domain Name', 'Context', 'Driver', 'Connection / Disk', 'Table Prefix / Path', 'Migrations'],
                [
                    ['domain-one', 'Domain One', '<info>primary</info>', 'Database', 'sqlite_one_primary', 'one_primary_', '0'],
                ]
            );
    }

    public function test_domain_status_with_comma_separated_domains(): void
    {
        $registry = $this->app->make(DomainRegistryInterface::class);

        $registry->registerDomain('domain-one', 'Domain One');
        $registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_one_primary',
            tablePrefix: 'one_primary_',
        ));

        $registry->registerDomain('domain-two', 'Domain Two');
        $registry->registerStorageContext(StorageContext::database(
            domainSlug: 'domain-two',
            contextSlug: 'primary',
            connectionName: 'sqlite_two_primary',
            tablePrefix: 'two_primary_',
        ));

        $registry->registerDomain('domain-three', 'Domain Three');

        $this->artisan('domain:status --domains=domain-one,domain-two')
            ->assertSuccessful()
            ->expectsTable(
                ['Domain Slug', 'Domain Name', 'Context', 'Driver', 'Connection / Disk', 'Table Prefix / Path', 'Migrations'],
                [
                    ['domain-one', 'Domain One', '<info>primary</info>', 'Database', 'sqlite_one_primary', 'one_primary_', '0'],
                    ['domain-two', 'Domain Two', '<info>primary</info>', 'Database', 'sqlite_two_primary', 'two_primary_', '0'],
                ]
            );
    }

    public function test_domain_cache_and_clear_commands(): void
    {
        $registry = $this->app->make(DomainRegistryInterface::class);
        $registry->registerDomain('domain-one', 'Domain One');

        $this->artisan('domain:cache')->assertSuccessful();

        $cacheFile = $this->app->bootstrapPath('cache/domains.php');
        self::assertFileExists($cacheFile);

        $this->artisan('domain:clear')->assertSuccessful();
        self::assertFileDoesNotExist($cacheFile);
    }

    public function test_domain_make_domain_command(): void
    {
        $files = new Filesystem;
        $targetDir = base_path('packages/test-vendor/demo-domain');

        try {
            $this->artisan('domain:make-domain demo-domain --vendor=test-vendor')
                ->assertSuccessful();

            self::assertDirectoryExists($targetDir);
            self::assertFileExists("{$targetDir}/composer.json");
            self::assertFileExists("{$targetDir}/config/domain.php");
            self::assertFileExists("{$targetDir}/src/Providers/DemoDomainServiceProvider.php");
        } finally {
            $files->deleteDirectory(base_path('packages/test-vendor'));
        }
    }
}
