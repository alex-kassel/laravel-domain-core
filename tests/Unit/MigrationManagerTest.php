<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use AlexKassel\DomainCore\Contracts\MigrationManagerInterface;
use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class MigrationManagerTest extends TestCase
{
    private string $tempMigrationDir;

    protected function setUp(): void
    {
        parent::setUp();

        $files = new Filesystem();
        $this->tempMigrationDir = __DIR__ . '/../fixtures/migrations';
        $files->makeDirectory($this->tempMigrationDir, 0755, true, true);

        $migrationContent = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('test_items', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('test_items');
    }
};
PHP;
        $files->put("{$this->tempMigrationDir}/2026_01_01_000001_create_test_items_table.php", $migrationContent);

        // Configure memory database for testing
        config([
            'database.connections.sqlite_domain_one_primary' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'one_primary_',
            ],
            'database.connections.sqlite_domain_one_archive' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'one_archive_',
            ],
        ]);

        $registry = $this->app->make(DomainRegistryInterface::class);

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'primary',
            connectionName: 'sqlite_domain_one_primary',
            tablePrefix: 'one_primary_',
            migrationPaths: [$this->tempMigrationDir],
        ));

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'domain-one',
            contextSlug: 'archive',
            connectionName: 'sqlite_domain_one_archive',
            tablePrefix: 'one_archive_',
            migrationPaths: [$this->tempMigrationDir],
        ));
    }

    protected function tearDown(): void
    {
        $files = new Filesystem();
        $files->deleteDirectory(__DIR__ . '/../fixtures');
        parent::tearDown();
    }

    public function testMigrateRunsAcrossTargetContexts(): void
    {
        $manager = $this->app->make(MigrationManagerInterface::class);

        // 1. Migrate only primary context
        $reports = $manager->migrate('domain-one', 'primary', true);

        self::assertCount(1, $reports);
        self::assertTrue($reports[0]->isSuccess());
        self::assertSame('primary', $reports[0]->contextSlug);
        self::assertCount(1, $reports[0]->executedMigrations);

        // 2. Migrate all remaining
        $allReports = $manager->migrate('domain-one', null, true);
        self::assertCount(2, $allReports);
    }

    public function testRollbackAcrossTargetContexts(): void
    {
        $manager = $this->app->make(MigrationManagerInterface::class);

        // Migrate first
        $manager->migrate('domain-one', 'primary', true);

        // Rollback
        $reports = $manager->rollback('domain-one', 'primary', 1, true);

        self::assertCount(1, $reports);
        self::assertTrue($reports[0]->isSuccess());
        self::assertCount(1, $reports[0]->executedMigrations);
    }
}
