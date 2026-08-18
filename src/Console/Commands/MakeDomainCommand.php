<?php

namespace AlexKassel\DomainCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeDomainCommand extends Command
{
    protected $signature = 'domain-core:make-domain 
                            {slug : The kebab-case slug of the target domain package}
                            {--vendor=alex-kassel : Package vendor prefix}
                            {--path= : Custom target root path}';

    protected $description = 'Scaffold a new child domain package directory with standardized configuration and skeletons';

    public function handle(Filesystem $files): int
    {
        $slug = Str::slug($this->argument('slug'));
        $vendor = Str::slug($this->option('vendor'));
        $customPath = $this->option('path');

        $basePath = $customPath
            ? rtrim($customPath, '/\\') . '/' . $slug
            : base_path("packages/{$vendor}/{$slug}");

        if ($files->isDirectory($basePath)) {
            $this->error("Target package directory [{$basePath}] already exists.");
            return self::FAILURE;
        }

        $studlySlug = Str::studly($slug);

        $files->makeDirectory("{$basePath}/src/Providers", 0755, true);
        $files->makeDirectory("{$basePath}/src/DTOs", 0755, true);
        $files->makeDirectory("{$basePath}/src/Models", 0755, true);
        $files->makeDirectory("{$basePath}/src/Console", 0755, true);
        $files->makeDirectory("{$basePath}/config", 0755, true);
        $files->makeDirectory("{$basePath}/tests/Unit", 0755, true);

        // Scaffold composer.json
        $composerJson = json_encode([
            'name' => "{$vendor}/{$slug}",
            'description' => "Child domain package for {$slug}",
            'type' => 'library',
            'license' => 'MIT',
            'autoload' => [
                'psr-4' => [
                    "AlexKassel\\" . $studlySlug . "\\" => "src/",
                ],
            ],
            'require' => [
                'php' => '^8.2',
                'alex-kassel/laravel-domain-core' => '^1.0',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $files->put("{$basePath}/composer.json", $composerJson);

        // Scaffold config/domain.php
        $configContent = "<?php\n\nreturn [\n    'domain_slug' => '{$slug}',\n    'package_slug' => '{$vendor}/{$slug}',\n    'connection_name' => '{$slug}',\n    'table_prefix' => '{$slug}_',\n    'is_enabled' => true,\n];\n";
        $files->put("{$basePath}/config/domain.php", $configContent);

        // Scaffold ServiceProvider
        $providerContent = "<?php\n\nnamespace AlexKassel\\{$studlySlug}\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\nclass {$studlySlug}ServiceProvider extends ServiceProvider\n{\n    public function register(): void\n    {\n        // Register domain context\n    }\n}\n";
        $files->put("{$basePath}/src/Providers/{$studlySlug}ServiceProvider.php", $providerContent);

        $this->info("Domain package [{$vendor}/{$slug}] successfully scaffolded at [{$basePath}].");

        return self::SUCCESS;
    }
}
