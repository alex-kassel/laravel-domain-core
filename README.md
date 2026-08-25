# Laravel Domain Core

[![Audited by Laravel Package Audit](https://img.shields.io/badge/Audited%20by-Laravel%20Package%20Audit-10b981?style=for-the-badge&logo=shield)](RELEASE-GATE.md)
[![Latest Version](https://img.shields.io/packagist/v/alex-kassel/laravel-domain-core?style=for-the-badge&logo=packagist&logoColor=white&color=f59e0b)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%208-8b5cf6?style=for-the-badge&logo=php&logoColor=white)](RELEASE-GATE.md)
[![PHP](https://img.shields.io/packagist/dependency-v/alex-kassel/laravel-domain-core/php?style=for-the-badge&logo=php&logoColor=white&color=777bb4)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![Downloads](https://img.shields.io/packagist/dt/alex-kassel/laravel-domain-core?style=for-the-badge&logo=packagist&logoColor=white&color=0284c7)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![License](https://img.shields.io/github/license/alex-kassel/laravel-domain-core?style=for-the-badge&color=0d9488)](LICENSE)

A high-cohesion, enterprise platform foundation for Laravel applications. `alex-kassel/laravel-domain-core` unifies **Polyglot Domain & Storage Context Registration**, **Dynamic Multi-Database & S3/Filesystem/Redis Provisioning**, **Context-Aware Base Eloquent Models**, **Standardized Operator CLI Execution**, and **Distributed Lock Management**.

It introduces a clean architectural separation between **Logical Business Contexts** (`StorageContext`) and **Physical Storage Mediums** (`StorageInterface`):

```text
Domain Profile (e.g., 'automotive-leasing')
└── StorageContext[] (e.g., 'primary', 'raw-html', 'transient-cache')
    ├── DatabaseStorage   (connection: 'sqlite_leasing_primary', prefix: 'leasing_primary_', migrations: [...])
    ├── FileStorage       (disk: 's3', basePath: 'leasing/raw-html/')
    └── RedisStorage      (connection: 'default', keyPrefix: 'leasing:cache:')
```

---

## Features

- **Polyglot Persistence Support:** Register and manage relational databases (MySQL, PostgreSQL, SQLite), object storage/filesystems (Local, S3, MinIO), and in-memory caches (Redis) under a unified domain model.
- **First-Class IDE Autocomplete & Type Safety:** Strongly-typed Enum `StorageDriverType`, dedicated named constructors (`StorageContext::database()`, `::filesystem()`, `::redis()`), and downcasting helpers (`$context->asDatabase()`, `$context->asFilesystem()`).
- **Context-Aware Eloquent Models & Hijacking Protection:** Automatically route database connections and table prefixes at runtime; statically bound models cannot be hijacked by outer ambient scopes.
- **Isolated Multi-Context Migrations:** Filter database-backed contexts automatically and safely drop only domain-prefixed tables on shared connections via `domain:migrate --fresh`.
- **Standardized Operator CLI DX & Distributed Locks:** Uniform Artisan command flags (`--all`, `--domains`, `--context`, `--force`, `--dry-run`, `--lock-ttl`) with robust lock management and POSIX signal handling (`SIGTERM`, `SIGINT`).
- **Actionable Diagnostics:** Structured `[PROBLEM]`, `[CAUSE]`, and `[RESOLUTION]` exceptions paired with diagnostic events (`StorageConnectionMissing`, `CommandExecutionFailed`, `LockAcquisitionFailed`).
- **Domain Package Scaffolding:** CLI generator (`domain:make-domain`) for standardized domain package skeletons.

---

## Requirements

- PHP 8.2+
- Laravel 11.x, 12.x, or 13.x

---

## Installation

Install the package via Composer:

```bash
composer require alex-kassel/laravel-domain-core
```

The Service Provider `AlexKassel\DomainCore\Providers\DomainCoreServiceProvider` is automatically registered via Laravel Package Discovery.

---

## Usage

### 1. Registering Domain Storage Contexts

Use dedicated named constructors for full IDE autocomplete:

```php
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;

$registry = app(DomainRegistryInterface::class);

// Register domain profile
$registry->registerDomain(
    slug: 'automotive-leasing',
    name: 'Automotive Leasing',
    metadata: ['category' => 'vehicles']
);

// Register Relational Database Storage Context
$registry->registerStorageContext(StorageContext::database(
    domainSlug: 'automotive-leasing',
    contextSlug: 'primary',
    connectionName: 'sqlite_leasing_primary',
    tablePrefix: 'leasing_primary_',
    migrationPaths: [__DIR__ . '/../database/migrations'],
    autoCreateSqliteDatabase: true
));

// Register S3 / Filesystem Storage Context
$registry->registerStorageContext(StorageContext::filesystem(
    domainSlug: 'automotive-leasing',
    contextSlug: 'raw-html',
    disk: 's3',
    basePath: 'leasing/raw-html/'
));

// Register Redis Storage Context
$registry->registerStorageContext(StorageContext::redis(
    domainSlug: 'automotive-leasing',
    contextSlug: 'transient-cache',
    connection: 'default',
    keyPrefix: 'leasing:cache:'
));
```

---

### 2. Ambient Execution Scopes

Execute business logic within an isolated domain and storage context:

```php
use AlexKassel\DomainCore\Facades\DomainContext;
use AlexKassel\DomainCore\DTOs\StorageContext;

// Database Scope:
DomainContext::using('automotive-leasing', 'primary', function (StorageContext $context) {
    // Eloquent models automatically resolve connection and prefix
    $item = new App\Models\LeasingOffer();
    $item->title = 'Audi A4 Lease';
    $item->save();
});

// Filesystem Scope:
DomainContext::using('automotive-leasing', 'raw-html', function (StorageContext $context) {
    $disk = DomainContext::disk(); // Returns Laravel Filesystem disk ('s3')
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
```

#### Static Domain Binding & Hijacking Protection
For models permanently bound to a specific domain that must ignore outer ambient scopes:

```php
class ArchiveOffer extends ContextAwareModel
{
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

$reports = $migrationManager->migrate(
    domainSlug: 'automotive-leasing',
    contextSlug: 'primary',
    force: true
);
```

---

### 5. CLI Execution & Distributed Lock Management

Execute batch jobs across domains safely with distributed locking and signal traps:

```php
use AlexKassel\DomainCore\Contracts\CommandRunnerInterface;
use AlexKassel\DomainCore\DTOs\DomainProfile;

$runner = app(CommandRunnerInterface::class);

$options = $runner->parseCliOptions([
    'all' => true,
    'domains' => 'domain-one,domain-two',
    'context' => 'primary',
    'lock-ttl' => 300,
]);

$targetDomains = $runner->resolveTargetDomains($options);

foreach ($targetDomains as $domain) {
    $report = $runner->executeDomain(
        domain: $domain,
        componentKey: 'scraper-job',
        callback: function (DomainProfile $profile) {
            // Execution protected by distributed lock
            return 42; // Items processed count
        },
        options: $options
    );
}
```

---

## Commands

All package commands are grouped under the `domain:` namespace:

| Command | Description |
|---|---|
| `domain:status` | Display registration, connection, driver, and prefix/path across domains |
| `domain:migrate` | Execute database migrations across registered domain storage contexts |
| `domain:cache` | Compile and atomically cache registered domain contexts for production |
| `domain:clear` | Clear compiled domain context cache |
| `domain:make-domain` | Scaffold a new domain package skeleton |

---

## Testing

Run the full package test suite:

```bash
composer test
# or
vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
