<?php

declare(strict_types=1);

namespace Modules\Ingestion\Enums;

enum StagingState: string
{
    case Staged = 'staged';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
