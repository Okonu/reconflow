<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum ImportMode: string
{
    case Replace = 'replace';
    case Append = 'append';
}
