<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum BatchOrigin: string
{
    case SourceSystem = 'source_system';
    case Upload = 'upload';
}
