<?php

declare(strict_types=1);

namespace AlexKassel\DomainCore\Database\Models;

use AlexKassel\DomainCore\Database\Traits\HasDomainContextTrait;
use Illuminate\Database\Eloquent\Model;

abstract class ContextAwareModel extends Model
{
    use HasDomainContextTrait;
}
