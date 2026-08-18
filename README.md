# Laravel Domain Core

[![Latest Stable Version](https://poser.pugx.org/alex-kassel/laravel-domain-core/v)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![Total Downloads](https://poser.pugx.org/alex-kassel/laravel-domain-core/downloads)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![License](https://poser.pugx.org/alex-kassel/laravel-domain-core/license)](https://packagist.org/packages/alex-kassel/laravel-domain-core)
[![PHP Version Require](https://poser.pugx.org/alex-kassel/laravel-domain-core/require/php)](https://packagist.org/packages/alex-kassel/laravel-domain-core)

High-cohesion Laravel platform foundation unifying Domain Context Registration, Dynamic Multi-Database Migrations, Context-Aware Base Eloquent Models, Standardized CLI Command Execution, Overlap Lock Management, Diagnostic Events, and Child Domain Package Scaffolding.

---

## Features

- **Domain Context Registration (`DomainRegistry`):** Runtime registration, discovery, and caching of domain configurations, database connections, and table prefixes.
- **Dynamic Multi-Database Migrations (`MigrationManager`):** Deterministic execution of migrations across SQLite, MySQL, and PostgreSQL with composite migration identity (`{$packageSlug}:{$domainSlug}:{$filename}`) and automatic SQLite database provisioning.
- **Central `domains` Table Schema:** Central table tracking registered domain classes and slugs with strict collision and mismatch protection.
- **Context-Aware Eloquent Models (`ContextAwareModel` & `HasDomainContextTrait`):** Dynamically binds database connections and table prefixes at runtime based on active domain context.
- **Standardized Operator CLI DX (`CommandRunner`):** Uniform Artisan command options (`--all`, `--domains`, `--except-domains`, `--force`, `--dry-run`) across platform packages.
- **Distributed Execution Lock Management (`ExecutionLockManager`):** Host and Redis cache locks per domain and component to prevent concurrent execution.
- **Graceful Overlap Recovery (`SKIPPED`):** Returns status `SKIPPED`, displays human-readable console notice, and emits `CommandRunSkippedDueToOverlap` diagnostic events.
- **Child Package Generator (`domain-core:make-domain`):** Rapidly scaffolds publication-ready child domain package skeletons.

---

## Installation

Install the package via Composer:

```bash
composer require alex-kassel/laravel-domain-core
```

---

## Documentation

For full architecture details, refer to the platform architecture specifications.

---

## Testing

Run the PHPUnit test suite:

```bash
composer test
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
