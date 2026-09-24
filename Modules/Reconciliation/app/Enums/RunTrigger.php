<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

enum RunTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case Retry = 'retry';
    case Demo = 'demo';
}
