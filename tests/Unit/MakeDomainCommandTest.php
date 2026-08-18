<?php

namespace AlexKassel\DomainCore\Tests\Unit;

use AlexKassel\DomainCore\Tests\TestCase;
use Illuminate\Support\Facades\File;

class MakeDomainCommandTest extends TestCase
{
    protected string $testPackagePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testPackagePath = base_path('packages/alex-kassel/test-scaffold-domain');
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testPackagePath)) {
            File::deleteDirectory($this->testPackagePath);
        }
        parent::tearDown();
    }

    public function test_it_scaffolds_child_domain_package(): void
    {
        $this->artisan('domain-core:make-domain', [
            'slug' => 'test-scaffold-domain',
            '--vendor' => 'alex-kassel',
        ])->assertExitCode(0);

        $this->assertTrue(File::isDirectory($this->testPackagePath));
        $this->assertTrue(File::exists("{$this->testPackagePath}/composer.json"));
        $this->assertTrue(File::exists("{$this->testPackagePath}/config/domain.php"));
        $this->assertTrue(File::exists("{$this->testPackagePath}/src/Providers/TestScaffoldDomainServiceProvider.php"));
    }
}
