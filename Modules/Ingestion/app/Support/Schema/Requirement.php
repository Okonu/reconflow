<?php

declare(strict_types=1);

namespace Modules\Ingestion\Support\Schema;

enum Requirement: string
{
    case Required = 'Yes';
    case Optional = 'No';
    case Conditional = 'Yes*';
}
