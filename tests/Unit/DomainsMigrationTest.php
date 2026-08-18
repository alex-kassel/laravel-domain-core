<?php

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class DomainsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_domains_table_is_created_by_package_migration(): void
    {
        $this->assertTrue(Schema::hasTable('domains'));
        $this->assertTrue(Schema::hasColumn('domains', 'id'));
        $this->assertTrue(Schema::hasColumn('domains', 'class'));
        $this->assertTrue(Schema::hasColumn('domains', 'slug'));
        $this->assertTrue(Schema::hasColumn('domains', 'created_at'));
    }
}
