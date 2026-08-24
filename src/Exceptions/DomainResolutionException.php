<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Exceptions;

use RuntimeException;

final class DomainResolutionException extends RuntimeException implements DomainCoreExceptionInterface
{
}
