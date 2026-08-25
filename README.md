# Laravel Domain Core

[![Latest Version on Packagist](https://img.shields.io/packagist/v/alex-kassel/laravel-domain-core.svg?style=flat-square)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![License](https://img.shields.io/packagist/l/alex-kassel/laravel-domain-core.svg?style=flat-square)](LICENSE)

A high-cohesion platform foundation package for Laravel 11.x, 12.x, and 13.x applications. `alex-kassel/laravel-domain-core` provides **Domain & Storage Context Registration**, **Dynamic Multi-Database Provisioning & Migrations**, **Context-Aware Base Eloquent Models**, **Standardized Operator CLI Execution**, **Distributed Lock Management**, **Actionable Diagnostic Exceptions**, and **Child Package Scaffolding**.

---

## Key Architectural Principles (Fail-Fast & Strict Discipline)

1. **Explicit over Implicit (No Silent Fallbacks):**  
   - `autoCreateSqliteDatabase` is disabled (`false`) by default. Missing connections trigger actionable `StorageConnectionNotFoundException` immediately instead of silently creating local SQLite databases in production.
   - Context slugs are mandatory (`$registry->getStorageContext('domain', 'primary')`) to avoid unintentional assumptions about default naming.
2. **Context Isolation & Hijacking Protection:**  
   - Models statically bound via `$explicitDomain` have absolute priority and can never be hijacked by ambient scopes (`DomainContext::using(...)`).
   - Strict mode prevents domain models from querying the root Laravel database when invoked outside of an active domain context (`NoActiveStorageContextException`).
3. **Shared Database & Migration Safety:**  
   - Destructive operations (`domain:migrate --fresh`) in shared database setups only drop tables matching the domain's `$tablePrefix`, preserving other domain tables and host application tables.
   - Migration history repositories are isolated dynamically per prefix/context (`{$tablePrefix}migrations`), preventing batch collisions.
4. **Concurrency & High-Availability:**  
   - Automated SQLite WAL mode (`journal_mode=WAL`) and `busy_timeout` (5000ms) configuration.
   - Connection pool purging (`DB::purge`) on runtime dynamic reconfiguration.
   - Atomic cache compilation for `domain:cache` to eliminate race conditions during deployment.
   - Configurable lock TTLs with POSIX signal handlers (`SIGTERM`, `SIGINT`) to release execution locks upon worker shutdown.

---

## Subsystems & Architecture

1. **Domain & Storage Context Registration (`DomainRegistryInterface`):** Strict registration, collision detection on mismatched configurations, deduplication, and atomic compile caching.
2. **Unified Database Provisioning (`DatabaseProvisioner`):** Multi-database provisioning supporting verified connections for MySQL, PostgreSQL, SQLite (with WAL & busy timeout), and `DB::purge()` execution.
3. **Dynamic Multi-Database Migrations (`MigrationManagerInterface`):** Multi-context migration manager with prefix-isolated `fresh` drops, scoped migration tables, and fail-fast validation for missing migration directories and unregistered domains.
4. **Context-Aware Base Eloquent Models (`ContextAwareModel` & `HasDomainContextTrait`):** Base Eloquent models and traits that dynamically route database connections and table prefixes at runtime with explicit domain priority and strict context enforcement.
5. **Standardized Operator CLI DX (`CommandRunnerInterface`):** Uniform Artisan command options (`--all`, `--domains`, `--except-domains`, `--context`, `--force`, `--dry-run`) with fail-fast domain validation.
6. **Distributed Lock Management (`ExecutionLockManagerInterface`):** Reliable lock state checking across all cache/lock drivers (Redis, Database, etc.) with customizable TTL and POSIX signal trapping.
7. **Actionable 3-Part Exceptions & Diagnostic Events:** Structured `[PROBLEM]`, `[CAUSE]`, and `[RESOLUTION]` messages paired with rich diagnostic events (`StorageConnectionMissing`, `CommandExecutionFailed`, `LockAcquisitionFailed`, `CommandRunSkippedDueToOverlap`).
8. **Child Package Generator (`domain:make-domain`):** Scaffolds standardized domain package skeletons with clean directory structure, configuration, providers, and test setups.

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

Domains and storage contexts define database connection names, table prefixes, and migration paths:

```php
use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;

$registry = app(DomainRegistryInterface::class);

// 1. Register domain profile
$registry->registerDomain(
    slug: 'domain-one',
    name: 'Domain One',
    metadata: ['category' => 'leasing']
);

// 2. Register storage context (explicit context slug is required)
$registry->registerStorageContext(new StorageContext(
    domainSlug: 'domain-one',
    contextSlug: 'primary',
    connectionName: 'sqlite_domain_one_primary',
    tablePrefix: 'one_primary_',
    migrationPaths: [__DIR__ . '/../database/migrations'],
    autoCreateSqliteDatabase: true // Explicitly enable SQLite auto-creation for local tests/dev
));
```

---

### 2. Ambient Execution Scopes (`DomainContext`)

Execute business logic within an isolated domain and storage context:

```php
use AlexKassel\DomainCore\Facades\DomainContext;

DomainContext::using('domain-one', 'primary', function (StorageContext $context) {
    // Models automatically resolve connection and prefix for 'domain-one' -> 'primary'
    $item = new App\Models\DomainItem();
    $item->title = 'Item One';
    $item->save();
});
```

---

### 3. Context-Aware Base Eloquent Models

Extend `ContextAwareModel` or use `HasDomainContextTrait`:

```php
use AlexKassel\DomainCore\Database\Models\ContextAwareModel;

class DomainItem extends ContextAwareModel
{
    protected $table = 'items';
    protected $fillable = ['title', 'sku', 'price'];
}

// Inside DomainContext::using('domain-one', 'primary') -> table resolves with domain prefix
```

For models needing explicit contexts or static domain binding:

```php
class ArchiveItem extends ContextAwareModel
{
    // Explicit domain binding cannot be hijacked by ambient scopes
    protected ?string $explicitDomain = 'domain-one';
    protected ?string $explicitContext = 'archive';
    protected $table = 'archives';
}
```

---

### 4. Running Multi-Database Migrations

```php
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;

$migrationManager = app(MigrationManagerInterface::class);

// Run migrations for domain-one's primary context
$reports = $migrationManager->migrate(
    domainSlug: 'domain-one',
    contextSlug: 'primary',
    force: true
);
```

---

### 5. CLI Execution & Lock Protection (`CommandRunnerInterface`)

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
        componentKey: 'runner-one',
        callback: function (DomainProfile $profile) {
            // Process domain logic
            return 42; // Processed items count
        },
        options: $options
    );
}
```

---

### 6. Artisan Console Commands Catalog

All package commands are grouped under the `domain:` namespace:

```bash
# Display registration, connection, prefix, and status across domains
php artisan domain:status
php artisan domain:status --domains=domain-one,domain-two

# Execute database migrations across registered domain storage contexts
php artisan domain:migrate
php artisan domain:migrate --domain=domain-one --context=primary
php artisan domain:migrate --domains=domain-one,domain-two

# Rollback migrations
php artisan domain:migrate --domain=domain-one --rollback --step=1

# Reset or Fresh database (Safely drops only domain-prefixed tables on shared connections)
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

Run the package test suite:

```bash
vendor/bin/phpunit
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
