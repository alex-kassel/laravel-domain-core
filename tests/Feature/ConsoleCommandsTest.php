<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Feature;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class ConsoleCommandsTest extends TestCase
{
    public function testDomainStatusCommand(): void
    {
        $registry = $this->app->make(DomainRegistryInterface::class);

        $registry->registerDomain('car-sub', 'Car Subscription');
        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'car-sub',
            capabilitySlug: 'scraping',
            connectionName: 'sqlite_raw',
            tablePrefix: 'cs_raw_',
        ));

        $this->artisan('domain:status')
            ->assertSuccessful()
            ->expectsTable(
                ['Domain Slug', 'Domain Name', 'Capability', 'Connection', 'Table Prefix', 'Migration Paths'],
                [
                    ['car-sub', 'Car Subscription', '<info>scraping</info>', 'sqlite_raw', 'cs_raw_', 0],
                ]
            );
    }

    public function testDomainCacheAndClearCommands(): void
    {
        $registry = $this->app->make(DomainRegistryInterface::class);
        $registry->registerDomain('car-sub', 'Car Subscription');

        $this->artisan('domain:cache')->assertSuccessful();

        $cacheFile = $this->app->bootstrapPath('cache/domains.php');
        self::assertFileExists($cacheFile);

        $this->artisan('domain:clear')->assertSuccessful();
        self::assertFileDoesNotExist($cacheFile);
    }

    public function testDomainMakeDomainCommand(): void
    {
        $files = new Filesystem();
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
