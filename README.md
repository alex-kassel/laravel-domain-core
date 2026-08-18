# Laravel Domain Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alex-kassel/laravel-domain-core.svg?style=flat-square)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/alex-kassel/laravel-domain-core/packagist.yml?branch=main&label=tests&style=flat-square)](https://github.com/alex-kassel/laravel-domain-core/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/alex-kassel/laravel-domain-core.svg?style=flat-square)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![License](https://img.shields.io/packagist/l/alex-kassel/laravel-domain-core.svg?style=flat-square)](LICENSE)

A high-cohesion platform foundation package for Laravel 11.x, 12.x, and 13.x applications. `alex-kassel/laravel-domain-core` consolidates **Domain Context Registration**, **Dynamic Multi-Database Migrations**, **Context-Aware Base Eloquent Models**, **Standardized Operator CLI Execution**, **Distributed Lock Management**, **Graceful Overlap Recovery**, and **Child Package Scaffolding**.

---

## Subsystems & Architecture

1. **Domain Context Registration (`DomainRegistryInterface`):** Runtime registration, discovery, resolution, enablement management, and caching of domain contexts, database connection names, and table prefixes.
2. **Central `domains` Table Schema & Slug Immutability:** Owns the platform-wide central `domains` database table (`id`, `class`, `slug`, `created_at`) with automatic slug resolution and mismatch/collision enforcement (`DomainSlugCollisionException`, `DomainSlugMismatchException`).
3. **Dynamic Multi-Database Migrations (`MigrationManagerInterface`):** Independent migration manager supporting SQLite, MySQL, and PostgreSQL with composite migration identity tracking (`{$packageSlug}:{$domainSlug}:{$filename}`) and 2-stage SQLite database auto-provisioning.
4. **Context-Aware Base Eloquent Models (`ContextAwareModel` & `HasDomainContextTrait`):** Base Eloquent models and traits that dynamically route database connections and table prefixes at runtime based on the active `DomainContext`.
5. **Standardized Operator CLI DX (`CommandRunnerInterface`):** Uniform Artisan command options (`--all`, `--domains`, `--except-domains`, `--force`, `--dry-run`) across platform packages.
6. **Distributed Lock Management (`ExecutionLockManagerInterface`):** Cache/Redis locks per domain and component to prevent overlapping execution across hosts.
7. **Graceful Overlap Recovery (`SKIPPED`):** Automatically handles active execution locks by returning `CommandExecutionReport(status: 'SKIPPED')`, printing operator warnings, and emitting `CommandRunSkippedDueToOverlap` diagnostic events.
8. **Child Package Generator (`domain-core:make-domain`):** Scaffolds publication-ready child domain package skeletons with complete directory structure, configuration, providers, and test setups.

---

## Installation

Install the package via Composer:

```bash
composer require alex-kassel/laravel-domain-core
```

The Service Provider `AlexKassel\DomainCore\Providers\DomainCoreServiceProvider` is automatically registered via Laravel Package Discovery.

---

## Developer Quick Start Guide

### 1. Registering Domain Contexts (`DomainRegistryInterface`)

Domain contexts define database connection names, table prefixes, and domain identifiers:

```php
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\DomainContext;

$registry = app(DomainRegistryInterface::class);

$registry->register(new DomainContext(
    domainSlug: 'domain-one',
    packageSlug: 'alex-kassel/package-one',
    connectionName: 'sqlite_domain_one',
    tablePrefix: 'd1_',
    className: App\Domains\DomainOneContext::class,
    autoCreateSqliteDatabase: true
));

// Resolve context by slug
$context = $registry->resolve('domain-one');
echo $context->connectionName; // 'sqlite_domain_one'
echo $context->tablePrefix;     // 'd1_'
```

---

### 2. Context-Aware Base Eloquent Models

Extend `ContextAwareModel` or use `HasDomainContextTrait` to dynamically route Eloquent queries to the correct database connection and prefix at runtime based on `$domainSlug`:

```php
use AlexKassel\DomainCore\Database\Models\ContextAwareModel;

class DomainItem extends ContextAwareModel
{
    /**
     * Bind this model dynamically to the registered domain context.
     */
    protected ?string $domainSlug = 'domain-one';

    protected $fillable = ['title', 'sku', 'price'];
}

// Queries automatically use connection 'sqlite_domain_one' and table 'd1_domain_items'
$items = DomainItem::where('price', '>', 100)->get();
```

---

### 3. Running Deterministic Multi-Database Migrations

```php
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;

$migrationManager = app(MigrationManagerInterface::class);

// Run migrations for a registered domain slug directly
$report = $migrationManager->migrateDomain('domain-one');

echo "Executed " . count($report->executedMigrations) . " migration(s) in " . $report->durationSeconds . "s\n";
```

Each migration is recorded in the migrations table using composite key `{$packageSlug}:{$domainSlug}:{$filename}` to prevent filename collisions across packages sharing database connections.

---

### 4. CLI Execution & Overlap Protection (`CommandRunnerInterface`)

Use `CommandRunnerInterface` in your custom Artisan console commands to parse standard flags and safely execute domain operations under lock protection:

```php
use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainContext;

$runner = app(CommandRunnerInterface::class);

// 1. Parse raw options from Artisan input
$options = $runner->parseCliOptions([
    'all' => true,
    'domains' => 'domain-one,domain-two',
    'except-domains' => 'domain-two',
    'force' => false,
    'dry-run' => false,
]);

// 2. Resolve target domain contexts matching flags
$targetDomains = $runner->resolveTargetDomains($options);

// 3. Execute domain callback with automatic lock acquisition and SKIPPED recovery
foreach ($targetDomains as $domain) {
    $report = $runner->executeDomain(
        domain: $domain,
        componentKey: 'runner',
        callback: function (DomainContext $context, CommandOptionsDTO $opts) {
            // Process domain logic
            return 42; // Returns executed items count
        },
        options: $options
    );

    if ($report->status === 'SKIPPED') {
        // Automatically logged; event CommandRunSkippedDueToOverlap dispatched
    }
}
```

---

### 5. Artisan Console Commands Catalog

All package commands are grouped under the `domain-core:` namespace:

```bash
# Display registration, connection, prefix, and status across domains
php artisan domain-core:status

# Execute pending database migrations across all or specified registered domains
php artisan domain-core:migrate
php artisan domain-core:migrate --domain=domain-one

# Compile and cache registered domain contexts for production
php artisan domain-core:cache

# Clear compiled domain context cache
php artisan domain-core:clear

# Scaffold a new child domain package directory
php artisan domain-core:make-domain domain-one --vendor=alex-kassel
```

---

### 6. Scaffolding Child Packages (`domain-core:make-domain`)

Scaffold a standardized, publication-ready child domain package under `packages/{vendor}/{slug}`:

```bash
php artisan domain-core:make-domain domain-one --vendor=alex-kassel
```

Scaffolded directory layout:
```text
packages/alex-kassel/domain-one/
├── composer.json
├── config/domain.php
├── src/
│   ├── Console/
│   ├── DTOs/
│   ├── Models/
│   └── Providers/
│       └── DomainOneServiceProvider.php
└── tests/
    └── Unit/
```

---

## Exception Taxonomy & Handling

All package exceptions implement `AlexKassel\DomainCore\Exceptions\DomainCoreExceptionInterface`:

```text
DomainCoreExceptionInterface (Marker Interface)
 ├── DomainNotFoundException (thrown when resolving an unregistered/disabled domain slug)
 ├── DomainConnectionNotFoundException (thrown when database file/connection resolution fails)
 ├── DomainSlugCollisionException (thrown when a requested slug collides with another domain class)
 ├── DomainSlugMismatchException (thrown when a registered domain class changes its slug)
 ├── MigrationFailedException (thrown when migration execution or rollback fails)
 ├── DomainResolutionException (thrown when target CLI domain filtering fails)
 ├── LockAcquisitionException (thrown when lock backend connection fails critically)
 └── ScaffoldingException (thrown when package scaffolding fails due to directory collision)
```

---

## Testing

Run the PHPUnit test suite:

```bash
composer test
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
