<?php

declare(strict_types=1);

$autoloader = null;

$candidates = [
    __DIR__.'/../vendor/autoload.php',
    __DIR__.'/../../../../vendor/autoload.php',
];

foreach ($candidates as $candidate) {
    if (file_exists($candidate)) {
        $autoloader = require $candidate;
        break;
    }
}

if ($autoloader === null) {
    throw new RuntimeException('Composer autoloader not found. Run "composer install" in the package or root repository.');
}

$autoloader->addPsr4('AlexKassel\\DomainCore\\Tests\\', __DIR__);
