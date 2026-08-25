<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class CacheCommand extends Command
{
    protected $signature = 'domain:cache';

    protected $description = 'Compile and cache registered domain profiles and storage contexts';

    public function handle(DomainRegistryInterface $registry, Filesystem $files): int
    {
        $this->info('Compiling Domain Registry cache...');

        $cachePath = $this->laravel->bootstrapPath('cache/domains.php');

        $compiled = $registry->compileCache();
        $export = '<?php return '.var_export($compiled, true).';'.PHP_EOL;

        $dir = dirname($cachePath);
        if (! $files->isDirectory($dir)) {
            $files->makeDirectory($dir, 0755, true, true);
        }

        $tmpPath = $cachePath.'.'.uniqid('domains_', true).'.tmp';
        $files->put($tmpPath, $export);
        $files->move($tmpPath, $cachePath);

        $this->info("Domain contexts cached successfully to [{$cachePath}].");

        return self::SUCCESS;
    }
}
