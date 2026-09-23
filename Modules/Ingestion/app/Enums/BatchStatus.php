<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum BatchStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
}
