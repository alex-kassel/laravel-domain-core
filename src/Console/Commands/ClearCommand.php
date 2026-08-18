<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class ClearCommand extends Command
{
    protected $signature = 'domain-core:clear';

    protected $description = 'Clear compiled domain context cache.';

    public function handle(Filesystem $files): int
    {
        $cachePath = storage_path('framework/cache/domain_core.php');

        if ($files->exists($cachePath)) {
            $files->delete($cachePath);
            $this->info('Domain core cache cleared successfully.');
        } else {
            $this->info('No domain core cache found.');
        }

        return self::SUCCESS;
    }
}
