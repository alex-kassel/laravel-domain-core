# Laravel Domain Core (v2.0)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alex-kassel/laravel-domain-core.svg?style=flat-square)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![License](https://img.shields.io/packagist/l/alex-kassel/laravel-domain-core.svg?style=flat-square)](LICENSE)

A high-cohesion, enterprise platform foundation for Laravel 11.x, 12.x, and 13.x applications. `alex-kassel/laravel-domain-core` provides **Polyglot Domain & Storage Context Registration**, **Dynamic Multi-Database & S3/Filesystem/Redis Provisioning**, **Context-Aware Base Eloquent Models**, **Standardized Operator CLI Execution**, **Distributed Lock Management**, **Actionable Diagnostic Exceptions**, and **Domain Package Scaffolding**.

---

## What's New in Architecture 2.0

`laravel-domain-core` v2.0 introduces a clean separation between **Logical Business Contexts** (`StorageContext`) and **Physical Storage Mediums** (`StorageInterface`):

```
Domain Profile (e.g., 'automotive-leasing')
└── StorageContext[] (e.g., 'scraping', 'normalizing', 'frontend', 'queue')
    ├── DatabaseStorage   (connection: 'mysql_scraping', prefix: 'leasing_', migrations: [...])
    ├── FileStorage       (disk: 's3', basePath: 'automotive-leasing/raw/')
    └── RedisStorage      (connection: 'cache', keyPrefix: 'leasing:queue:')
```

### Key Highlights
1. **Polyglot Persistence Support:** Register and manage relational databases (MySQL, PostgreSQL, SQLite), object storage/filesystems (Local, S3, MinIO), and in-memory caches (Redis) under a unified domain model.
2. **First-Class IDE Autocomplete & Type Safety:** Strongly-typed Enum `StorageDriverType`, dedicated named constructors (`StorageContext::database()`, `StorageContext::filesystem()`, `StorageContext::redis()`), and downcasting helpers (`$context->asDatabase()`, `$context->asFilesystem()`).
3. **Fail-Fast & Context Hijacking Protection:** Models statically bound to a domain cannot be hijacked by ambient scopes. Accessing mismatched storage drivers (e.g. running an Eloquent model inside a filesystem context) throws structured `IncompatibleStorageException`.
4. **Isolated Shared-Database Migrations:** `php artisan domain:migrate --fresh` drops only domain-prefixed tables on shared connections, safeguarding neighboring tables.

---

## Subsystems & Architecture

1. **Domain & Storage Context Registry (`DomainRegistryInterface`):** Registration, cross-domain collision detection, config deduplication, and atomic compile caching.
2. **Physical Storage Abstractions (`AlexKassel\DomainCore\Storage`):**
   - `DatabaseStorage`: Relational connections, table prefixes, migration paths, and SQLite WAL auto-provisioning.
   - `FileStorage`: Laravel Filesystem disks and isolated base paths.
   - `RedisStorage`: Redis connections and isolated key prefixes.
3. **Unified Database Provisioning (`DatabaseProvisioner`):** Multi-database provisioning for MySQL, PostgreSQL, and SQLite (with automatic WAL mode, `busy_timeout=5000`, and `DB::purge()` on reconfiguration).
4. **Dynamic Migrations (`MigrationManagerInterface`):** Multi-context migration manager with prefix-isolated table drops and automatic filtering of database-backed contexts.
5. **Context-Aware Base Eloquent Models (`ContextAwareModel` & `HasDomainContextTrait`):** Dynamically route database connections and table prefixes at runtime with explicit domain priority.
6. **Standardized Operator CLI DX (`CommandRunnerInterface`):** Uniform Artisan command flags (`--all`, `--domains`, `--except-domains`, `--context`, `--force`, `--dry-run`, `--lock-ttl`).
7. **Distributed Lock Management (`ExecutionLockManagerInterface`):** Reliable lock state checking across all cache/lock drivers (Redis, Database, etc.) with customizable TTL and POSIX signal trapping (`SIGTERM`, `SIGINT`).
8. **Actionable Diagnostic Exceptions:** Structured `[PROBLEM]`, `[CAUSE]`, and `[RESOLUTION]` messages paired with rich diagnostic events (`StorageConnectionMissing`, `CommandExecutionFailed`, `LockAcquisitionFailed`).
9. **Child Package Generator (`domain:make-domain`):** Scaffolds standardized domain package skeletons.

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

Use dedicated named constructors for maximum PhpStorm autocomplete:

```php
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;

$registry = app(DomainRegistryInterface::class);

// 1. Register domain profile
$registry->registerDomain(
    slug: 'automotive-leasing',
    name: 'Automotive Leasing',
    metadata: ['category' => 'vehicles']
);

// 2. Register Relational Database Storage Context
$registry->registerStorageContext(StorageContext::database(
    domainSlug: 'automotive-leasing',
    contextSlug: 'primary',
    connectionName: 'sqlite_leasing_primary',
    tablePrefix: 'leasing_primary_',
    migrationPaths: [__DIR__ . '/../database/migrations'],
    autoCreateSqliteDatabase: true // Enable SQLite auto-creation for tests/dev
));

// 3. Register S3 / Filesystem Storage Context
$registry->registerStorageContext(StorageContext::filesystem(
    domainSlug: 'automotive-leasing',
    contextSlug: 'raw-html',
    disk: 's3',
    basePath: 'leasing/raw-html/'
));

// 4. Register Redis Storage Context
$registry->registerStorageContext(StorageContext::redis(
    domainSlug: 'automotive-leasing',
    contextSlug: 'transient-cache',
    connection: 'default',
    keyPrefix: 'leasing:cache:'
));
```

---

### 2. Ambient Execution Scopes (`DomainContext`)

Execute business logic within an isolated domain and storage context:

```php
use AlexKassel\DomainCore\Facades\DomainContext;
use AlexKassel\DomainCore\DTOs\StorageContext;

// Database Scope:
DomainContext::using('automotive-leasing', 'primary', function (StorageContext $context) {
    // Models automatically resolve connection and prefix for 'automotive-leasing' -> 'primary'
    $item = new App\Models\LeasingOffer();
    $item->title = 'Audi A4 Lease';
    $item->save();
});

// Filesystem / S3 Scope:
DomainContext::using('automotive-leasing', 'raw-html', function (StorageContext $context) {
    $disk = DomainContext::disk(); // Returns Laravel Filesystem disk instance ('s3')
    $disk->put('payload_123.html', $htmlContent);
});
```

---

### 3. Context-Aware Base Eloquent Models

Extend `ContextAwareModel` or use `HasDomainContextTrait`:

```php
use AlexKassel\DomainCore\Database\Models\ContextAwareModel;

class LeasingOffer extends ContextAwareModel
{
    protected $table = 'offers';
    protected $fillable = ['title', 'price', 'vin'];
}

// Inside DomainContext::using('automotive-leasing', 'primary') -> queries 'leasing_primary_offers'
```

#### Static Domain Binding & Hijacking Protection
For models permanently bound to a domain that must ignore outer ambient scopes:

```php
class ArchiveOffer extends ContextAwareModel
{
    // Statically bound to 'automotive-leasing'
    protected ?string $explicitDomain = 'automotive-leasing';
    protected ?string $explicitContext = 'archive';
    protected $table = 'archives';
}
```

---

### 4. Running Multi-Database Migrations

`MigrationManager` automatically filters relational database contexts and skips non-relational storage:

```php
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;

$migrationManager = app(MigrationManagerInterface::class);

// Run migrations for automotive-leasing primary database
$reports = $migrationManager->migrate(
    domainSlug: 'automotive-leasing',
    contextSlug: 'primary',
    force: true
);
```

---

### 5. CLI Execution & Distributed Lock Management

```php
use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\DTOs\CommandOptionsDTO;
use AlexKassel\DomainCore\DTOs\DomainProfile;

$runner = app(CommandRunnerInterface::class);

$options = $runner->parseCliOptions([
    'all' => true,
    'domains' => 'domain-one,domain-two',
    'context' => 'primary',
    'force' => false,
    'dry-run' => false,
    'lock-ttl' => 300,
]);

$targetDomains = $runner->resolveTargetDomains($options);

foreach ($targetDomains as $domain) {
    $report = $runner->executeDomain(
        domain: $domain,
        componentKey: 'scraper-job',
        callback: function (DomainProfile $profile) {
            // Business logic execution protected by distributed lock
            return 42; // Items processed count
        },
        options: $options
    );
}
```

---

### 6. Artisan Console Commands Catalog

All package commands are grouped under the `domain:` namespace:

```bash
# Display registration, connection, driver, and prefix/path across domains
php artisan domain:status
php artisan domain:status --domains=domain-one,domain-two

# Execute database migrations across registered domain storage contexts
php artisan domain:migrate
php artisan domain:migrate --domain=domain-one --context=primary
php artisan domain:migrate --domains=domain-one,domain-two

# Rollback migrations
php artisan domain:migrate --domain=domain-one --rollback --step=1

# Reset or Fresh database (Safely drops ONLY domain-prefixed tables on shared connections)
php artisan domain:migrate --domain=domain-one --fresh

# Compile and atomically cache registered domain contexts for production
php artisan domain:cache

# Clear compiled domain context cache
php artisan domain:clear

# Scaffold a new domain package skeleton
php artisan domain:make-domain domain-one --vendor=alex-kassel
```

---

## Testing

Run the full package test suite:

```bash
vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
