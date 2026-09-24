<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

enum RunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case BlockedData = 'blocked_data';
}
