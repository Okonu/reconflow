<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum PostingStatus: string
{
    case Posted = 'POSTED';
    case Pending = 'PENDING';
    case Reversed = 'REVERSED';
}
