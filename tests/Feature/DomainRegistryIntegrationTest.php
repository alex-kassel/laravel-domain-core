<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Feature;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\DomainContext;
use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DomainRegistryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_domain_context_inserts_record_into_domains_table(): void
    {
        /** @var DomainRegistryInterface $registry */
        $registry = $this->app->make(DomainRegistryInterface::class);

        $context = new DomainContext(
            domainSlug: 'domain-one',
            packageSlug: 'alex-kassel/package-one',
            connectionName: (string) config('database.default'),
            tablePrefix: 'd1_',
            className: self::class,
            autoCreateSqliteDatabase: false
        );

        $registry->register($context);

        $this->assertDatabaseHas('domains', [
            'class' => self::class,
            'slug' => 'domain-one',
        ]);
    }

    public function test_auto_creates_sqlite_database_and_config_for_unconfigured_connection(): void
    {
        $tempDir = sys_get_temp_dir() . '/domain_core_tests_' . uniqid();
        $tempDb = $tempDir . '/sqlite_unconfigured.sqlite';

        /** @var DomainRegistryInterface $registry */
        $registry = $this->app->make(DomainRegistryInterface::class);

        $context = new DomainContext(
            domainSlug: 'unconfigured-domain',
            packageSlug: 'alex-kassel/unconfigured-package',
            connectionName: 'sqlite_unconfigured',
            tablePrefix: 'unc_',
            className: 'AlexKassel\Unconfigured\DomainContext',
            autoCreateSqliteDatabase: true,
            extraConfig: [
                'database_path' => $tempDb,
            ]
        );

        $registry->register($context);

        $this->assertFileExists($tempDb);
        $this->assertSame($tempDb, config('database.connections.sqlite_unconfigured.database'));
        $this->assertSame('sqlite', config('database.connections.sqlite_unconfigured.driver'));

        if (file_exists($tempDb)) {
            @unlink($tempDb);
        }
        if (is_dir($tempDir)) {
            @rmdir($tempDir);
        }
    }
}
