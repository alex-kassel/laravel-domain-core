<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Exceptions\ScaffoldingException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakeDomainCommand extends Command
{
    protected $signature = 'domain:make-domain
                            {domain : The kebab-case domain name (e.g. domain-one)}
                            {--vendor=alex-kassel : The package vendor prefix}';

    protected $description = 'Scaffold a standardized domain package under packages/{vendor}/{domain}';

    public function handle(Filesystem $files): int
    {
        $domain = Str::kebab((string) $this->argument('domain'));
        $vendor = Str::kebab((string) $this->option('vendor'));

        $studlyDomain = Str::studly($domain);
        $studlyVendor = Str::studly($vendor);

        $targetDir = base_path("packages/{$vendor}/{$domain}");

        if ($files->isDirectory($targetDir)) {
            throw new ScaffoldingException("Target package directory [{$targetDir}] already exists.");
        }

        $this->info("Scaffolding domain package [{$vendor}/{$domain}] at {$targetDir}...");

        $files->makeDirectory("{$targetDir}/src/Providers", 0755, true, true);
        $files->makeDirectory("{$targetDir}/src/Models", 0755, true, true);
        $files->makeDirectory("{$targetDir}/src/DTOs", 0755, true, true);
        $files->makeDirectory("{$targetDir}/config", 0755, true, true);
        $files->makeDirectory("{$targetDir}/database/migrations", 0755, true, true);
        $files->makeDirectory("{$targetDir}/tests/Unit", 0755, true, true);
        $files->makeDirectory("{$targetDir}/tests/Feature", 0755, true, true);

        // composer.json
        $composerJson = <<<JSON
{
    "name": "{$vendor}/{$domain}",
    "description": "Domain package for {$domain}",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "alex-kassel/laravel-domain-core": "@dev"
    },
    "autoload": {
        "psr-4": {
            "{$studlyVendor}\\\\{$studlyDomain}\\\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "{$studlyVendor}\\\\{$studlyDomain}\\\\Tests\\\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "{$studlyVendor}\\\\{$studlyDomain}\\\\Providers\\\\{$studlyDomain}ServiceProvider"
            ]
        }
    }
}
JSON;
        $files->put("{$targetDir}/composer.json", $composerJson . PHP_EOL);

        // config/domain.php
        $configFile = <<<PHP
<?php

declare(strict_types=1);

return [
    'domain' => '{$domain}',
    'name' => '{$studlyDomain}',
    'contexts' => [
        'default' => [
            'connection' => 'sqlite_{$domain}_default',
            'table_prefix' => '{$domain}_',
            'migrations' => [
                __DIR__ . '/../database/migrations',
            ],
        ],
    ],
];
PHP;
        $files->put("{$targetDir}/config/domain.php", $configFile . PHP_EOL);

        // ServiceProvider
        $serviceProvider = <<<PHP
<?php

declare(strict_types=1);

namespace {$studlyVendor}\\{$studlyDomain}\\Providers;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use Illuminate\Support\ServiceProvider;

final class {$studlyDomain}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        \$this->mergeConfigFrom(__DIR__ . '/../../config/domain.php', 'domain.{$domain}');
    }

    public function boot(DomainRegistryInterface \$registry): void
    {
        \$config = config('domain.{$domain}');

        \$registry->registerDomain(
            slug: \$config['domain'] ?? '{$domain}',
            name: \$config['name'] ?? '{$studlyDomain}'
        );

        foreach (\$config['contexts'] ?? [] as \$contextSlug => \$settings) {
            \$registry->registerStorageContext(new StorageContext(
                domainSlug: '{$domain}',
                contextSlug: \$contextSlug,
                connectionName: \$settings['connection'] ?? 'sqlite_{$domain}_{\$contextSlug}',
                tablePrefix: \$settings['table_prefix'] ?? '{$domain}_{\$contextSlug}_',
                migrationPaths: \$settings['migrations'] ?? [],
                autoCreateSqliteDatabase: (bool) (\$settings['auto_create_sqlite_database'] ?? true),
            ));
        }
    }
}
PHP;
        $files->put("{$targetDir}/src/Providers/{$studlyDomain}ServiceProvider.php", $serviceProvider . PHP_EOL);

        // tests/bootstrap.php
        $testBootstrap = <<<PHP
<?php

declare(strict_types=1);

\$candidates = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

\$autoloader = null;
foreach (\$candidates as \$candidate) {
    if (file_exists(\$candidate)) {
        \$autoloader = require \$candidate;
        break;
    }
}

if (\$autoloader === null) {
    throw new RuntimeException('Composer autoloader not found.');
}

\$autoloader->addPsr4('{$studlyVendor}\\\\{$studlyDomain}\\\\Tests\\\\', __DIR__);
PHP;
        $files->put("{$targetDir}/tests/bootstrap.php", $testBootstrap . PHP_EOL);

        // phpunit.xml
        $phpunitXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML;
        $files->put("{$targetDir}/phpunit.xml", $phpunitXml . PHP_EOL);

        $this->info("Domain package [{$vendor}/{$domain}] scaffolded successfully.");

        return self::SUCCESS;
    }
}
