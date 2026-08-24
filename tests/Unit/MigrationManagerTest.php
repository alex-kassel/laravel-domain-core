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
            'database.connections.sqlite_test_raw' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'raw_',
            ],
            'database.connections.sqlite_test_norm' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => 'norm_',
            ],
        ]);

        $registry = $this->app->make(DomainRegistryInterface::class);

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'test-domain',
            capabilitySlug: 'scraping',
            connectionName: 'sqlite_test_raw',
            tablePrefix: 'raw_',
            migrationPaths: [$this->tempMigrationDir],
        ));

        $registry->registerStorageContext(new StorageContext(
            domainSlug: 'test-domain',
            capabilitySlug: 'normalization',
            connectionName: 'sqlite_test_norm',
            tablePrefix: 'norm_',
            migrationPaths: [$this->tempMigrationDir],
        ));
    }

    protected function tearDown(): void
    {
        $files = new Filesystem();
        $files->deleteDirectory(__DIR__ . '/../fixtures');
        parent::tearDown();
    }

    public function testMigrateRunsAcrossTargetCapabilityContexts(): void
    {
        $manager = $this->app->make(MigrationManagerInterface::class);

        // 1. Migrate only scraping capability
        $reports = $manager->migrate('test-domain', 'scraping');

        self::assertCount(1, $reports);
        self::assertTrue($reports[0]->isSuccess());
        self::assertSame('scraping', $reports[0]->capabilitySlug);
        self::assertCount(1, $reports[0]->executedMigrations);

        // 2. Migrate all remaining
        $allReports = $manager->migrate('test-domain');
        self::assertCount(2, $allReports);
    }

    public function testRollbackAcrossTargetContexts(): void
    {
        $manager = $this->app->make(MigrationManagerInterface::class);

        // Migrate first
        $manager->migrate('test-domain', 'scraping');

        // Rollback
        $reports = $manager->rollback('test-domain', 'scraping');

        self::assertCount(1, $reports);
        self::assertTrue($reports[0]->isSuccess());
        self::assertCount(1, $reports[0]->executedMigrations);
    }
}
