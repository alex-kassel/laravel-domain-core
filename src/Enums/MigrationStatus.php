<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Enums;

enum MigrationStatus: string
{
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
    case NO_OP = 'NO_OP';
}
