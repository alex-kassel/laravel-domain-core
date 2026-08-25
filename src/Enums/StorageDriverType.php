<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Enums;

enum StorageDriverType: string
{
    case DATABASE = 'database';
    case FILESYSTEM = 'filesystem';
    case REDIS = 'redis';
}
