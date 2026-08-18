<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use AlexKassel\DomainCore\Contracts\DomainRegistryInterface;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CacheCommand extends Command
{
    protected $signature = 'domain-core:cache';

    protected $description = 'Compile and cache registered domain contexts for production.';

    public function handle(DomainRegistryInterface $registry, Filesystem $files): int
    {
        $this->info('Compiling domain core cache...');

        $domains = $registry->all();
        $cachePath = storage_path('framework/cache/domain_core.php');

        $exported = "<?php\n\nreturn " . var_export($domains, true) . ";\n";
        $files->ensureDirectoryExists(dirname($cachePath));
        $files->put($cachePath, $exported);

        $this->info(sprintf('Cached %d domain context(s) to [%s].', count($domains), $cachePath));

        return self::SUCCESS;
    }
}
