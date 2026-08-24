<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class ClearCommand extends Command
{
    protected $signature = 'domain:clear';

    protected $description = 'Clear compiled domain registry cache';

    public function handle(Filesystem $files): int
    {
        $cachePath = $this->laravel->bootstrapPath('cache/domains.php');

        if ($files->exists($cachePath)) {
            $files->delete($cachePath);
            $this->info('Domain registry cache cleared successfully.');
        } else {
            $this->info('No compiled domain registry cache found.');
        }

        return self::SUCCESS;
    }
}
