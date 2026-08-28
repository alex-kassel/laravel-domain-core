<h1 align="center">🏛️ Laravel Domain Core</h1>

<p align="center">
  <strong>Polyglot domain storage contexts, dynamic multi-database provisioning, and ambient execution scoping</strong>
</p>

<p align="center">
  <a href="#installation">Installation</a> •
  <a href="#1-registering-domain-storage-contexts">Storage Contexts</a> •
  <a href="#2-ambient-execution-scopes">Ambient Scopes</a> •
  <a href="#commands">Commands</a> •
  <a href="RELEASE-GATE.md">Release Gate</a> •
  <a href="CHANGELOG.md">Changelog</a>
</p>

<p align="center">
  <a href="RELEASE-GATE.md"><img src="https://img.shields.io/badge/Audit-Verified-10b981?logo=shield" alt="Audit Verified"></a>
  <a href="https://packagist.org/packages/alex-kassel/laravel-domain-core"><img src="https://img.shields.io/packagist/v/alex-kassel/laravel-domain-core?color=f59e0b&logo=packagist&logoColor=white" alt="Latest Version"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-ff2d20?logo=laravel&logoColor=white" alt="Laravel Support"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.2+-777bb4?logo=php&logoColor=white" alt="PHP Support"></a>
  <a href="RELEASE-GATE.md"><img src="https://img.shields.io/badge/PHPStan-Level%20Max-8b5cf6?logo=php&logoColor=white" alt="PHPStan Level Max"></a>
</p>

---

**Laravel Domain Core** is a high-cohesion platform foundation for modular Laravel applications. It unifies **Polyglot Domain & Storage Context Registration**, **Dynamic Multi-Database & S3/Filesystem/Redis Provisioning**, **Context-Aware Base Eloquent Models**, **Standardized Operator CLI Execution**, and **Distributed Lock Management**.

It introduces a clean architectural separation between **Logical Business Contexts** (`StorageContext`) and **Physical Storage Mediums** (`StorageInterface`):

```text
Domain Profile (e.g., 'automotive-leasing')
└── StorageContext[] (e.g., 'primary', 'raw-html', 'transient-cache')
    ├── DatabaseStorage   (connection: 'sqlite_leasing_primary', prefix: 'leasing_primary_', migrations: [...])
    ├── FileStorage       (disk: 's3', basePath: 'leasing/raw-html/')
    └── RedisStorage      (connection: 'default', keyPrefix: 'leasing:cache:')
```

---

## Key Features

* **Polyglot Persistence Support:** Register and manage relational databases (MySQL, PostgreSQL, SQLite), object storage/filesystems (Local, S3, MinIO), and in-memory caches (Redis) under a unified domain model.
* **First-Class IDE Autocomplete & Type Safety:** Strongly-typed Enum `StorageDriverType`, dedicated named constructors (`StorageContext::database()`, `::filesystem()`, `::redis()`), and downcasting helpers (`$context->asDatabase()`, `$context->asFilesystem()`).
* **Context-Aware Eloquent Models & Hijacking Protection:** Automatically route database connections and table prefixes at runtime; statically bound models cannot be hijacked by outer ambient scopes.
* **Isolated Multi-Context Migrations:** Filter database-backed contexts automatically and safely drop only domain-prefixed tables on shared connections via `domain:migrate --fresh`.
* **Standardized Operator CLI DX & Distributed Locks:** Uniform Artisan command flags (`--all`, `--domains`, `--context`, `--force`, `--dry-run`, `--lock-ttl`) with robust lock management and POSIX signal handling (`SIGTERM`, `SIGINT`).
* **Actionable Diagnostics:** Structured `[PROBLEM]`, `[CAUSE]`, and `[RESOLUTION]` exceptions paired with diagnostic events (`StorageConnectionMissing`, `CommandExecutionFailed`, `LockAcquisitionFailed`).
* **Domain Package Scaffolding:** CLI generator (`domain:make-domain`) for standardized domain package skeletons.

---

## Requirements

* **PHP:** 8.2+ (tested on 8.2, 8.3, 8.4, 8.5)
* **Laravel Framework:** 11.x | 12.x | 13.x

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

From the monorepo root, run the complete package verification pipeline:

```bash
composer pkg:check alex-kassel/laravel-domain-core --json
```

---

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on what has changed recently.

---

## Security Vulnerabilities

Please review the [security policy](https://github.com/alex-kassel/laravel-domain-core/security/policy) to report vulnerabilities.

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
