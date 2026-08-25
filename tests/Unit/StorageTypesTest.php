<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\DTOs\StorageContext;
use AlexKassel\DomainCore\Enums\StorageDriverType;
use AlexKassel\DomainCore\Exceptions\IncompatibleStorageException;
use AlexKassel\DomainCore\Storage\DatabaseStorage;
use AlexKassel\DomainCore\Storage\FileStorage;
use AlexKassel\DomainCore\Storage\RedisStorage;
use AlexKassel\DomainCore\Storage\StorageFactory;
use AlexKassel\DomainCore\Tests\TestCase;
use InvalidArgumentException;

final class StorageTypesTest extends TestCase
{
    public function testDatabaseStoragePropertiesAndSerialization(): void
    {
        $db = new DatabaseStorage(
            connectionName: 'mysql_custom',
            tablePrefix: 'prefix_',
            migrationPaths: ['/path/one'],
            autoCreateSqliteDatabase: true,
            extraOptions: ['charset' => 'utf8mb4']
        );

        self::assertSame(StorageDriverType::DATABASE, $db->getDriverType());
        self::assertSame('mysql_custom', $db->getConnectionName());
        self::assertSame('prefix_', $db->getTablePrefix());
        self::assertSame(['/path/one'], $db->getMigrationPaths());
        self::assertTrue($db->shouldAutoCreateSqliteDatabase());
        self::assertSame('database:mysql_custom:prefix_', $db->getIdentityKey());

        $array = $db->toArray();
        self::assertSame('database', $array['driver']);
        self::assertSame('mysql_custom', $array['connectionName']);

        $restored = DatabaseStorage::fromArray($array);
        self::assertSame('mysql_custom', $restored->connectionName);
        self::assertSame('prefix_', $restored->tablePrefix);
    }

    public function testFileStoragePropertiesAndSerialization(): void
    {
        $file = new FileStorage(
            disk: 's3',
            basePath: 'leasing/raw/',
            extraOptions: ['visibility' => 'private']
        );

        self::assertSame(StorageDriverType::FILESYSTEM, $file->getDriverType());
        self::assertSame('s3', $file->disk);
        self::assertSame('leasing/raw/', $file->basePath);
        self::assertSame('filesystem:s3:leasing/raw', $file->getIdentityKey());

        $array = $file->toArray();
        self::assertSame('filesystem', $array['driver']);
        self::assertSame('s3', $array['disk']);

        $restored = FileStorage::fromArray($array);
        self::assertSame('s3', $restored->disk);
        self::assertSame('leasing/raw/', $restored->basePath);
    }

    public function testRedisStoragePropertiesAndSerialization(): void
    {
        $redis = new RedisStorage(
            connection: 'cache_redis',
            keyPrefix: 'leasing:cache:',
            extraOptions: ['timeout' => 2.5]
        );

        self::assertSame(StorageDriverType::REDIS, $redis->getDriverType());
        self::assertSame('cache_redis', $redis->connection);
        self::assertSame('leasing:cache:', $redis->keyPrefix);
        self::assertSame('redis:cache_redis:leasing:cache:', $redis->getIdentityKey());

        $array = $redis->toArray();
        self::assertSame('redis', $array['driver']);
        self::assertSame('cache_redis', $array['connection']);

        $restored = RedisStorage::fromArray($array);
        self::assertSame('cache_redis', $restored->connection);
        self::assertSame('leasing:cache:', $restored->keyPrefix);
    }

    public function testStorageFactoryInstantiatesCorrectTypes(): void
    {
        $db = StorageFactory::fromArray([
            'driver' => 'database',
            'connectionName' => 'mysql_db',
        ]);
        self::assertInstanceOf(DatabaseStorage::class, $db);

        $file = StorageFactory::fromArray([
            'driver' => 'filesystem',
            'disk' => 's3',
        ]);
        self::assertInstanceOf(FileStorage::class, $file);

        $redis = StorageFactory::fromArray([
            'driver' => 'redis',
            'connection' => 'default',
        ]);
        self::assertInstanceOf(RedisStorage::class, $redis);
    }

    public function testStorageContextDowncastingThrowsIncompatibleStorageException(): void
    {
        $fileContext = StorageContext::filesystem('domain-one', 'assets', 's3');

        self::assertTrue($fileContext->isFilesystem());
        self::assertFalse($fileContext->isDatabase());
        self::assertFalse($fileContext->isRedis());

        $this->expectException(IncompatibleStorageException::class);
        $fileContext->asDatabase();
    }
}
