# Changelog

All notable changes to `alex-kassel/laravel-domain-core` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.0.0] - 2026-08-25

### Added
- **Polyglot Persistence Architecture**: Introduced `StorageContext` decoupling logical domain business contexts from physical storage media.
- **Dedicated Storage Implementations**:
  - `DatabaseStorage`: Relational connections, table prefixing, migration paths, and SQLite WAL auto-provisioning.
  - `FileStorage`: Laravel Filesystem disks and base paths.
  - `RedisStorage`: In-memory Redis connections and isolated key prefixes.
- **Multi-Database Migrations**: `MigrationManager` with prefix-isolated table drops and filtering of database-backed contexts.
- **Fail-Fast Typing & Diagnostic Events**: Structured diagnostic exceptions (`StorageConnectionMissing`, `IncompatibleStorageException`, `LockAcquisitionFailed`) and `StorageDriverType` enum.
- **Artisan Console Catalog**: `domain:status`, `domain:migrate`, `domain:cache`, `domain:clear`, and `domain:make-domain`.
- **POSIX Signal Handling**: Graceful lock release on `SIGTERM` / `SIGINT` with safe fallback on Windows environments.

### Changed
- Refactored `DomainRegistry` to support polyglot storage registration via `registerStorageContext()`.
- Updated base models to use `ContextAwareModel` with explicit domain priority.

---

## [1.0.0] - 2026-01-15

### Added
- Initial release with Domain Context Registry and SQLite auto-provisioning.
