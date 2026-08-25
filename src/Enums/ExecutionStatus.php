<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Enums;

enum ExecutionStatus: string
{
    case SUCCESS = 'SUCCESS';
    case FAILED = 'FAILED';
    case SKIPPED = 'SKIPPED';
}
