<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

enum ItemState: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Escalated = 'escalated';
    case Reclassified = 'reclassified';
}
