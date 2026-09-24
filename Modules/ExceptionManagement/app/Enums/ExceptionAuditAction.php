<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Enums;

enum ExceptionAuditAction: string
{
    case Opened = 'exception.opened';
    case Transitioned = 'exception.transitioned';
    case Assigned = 'exception.assigned';
    case Commented = 'exception.commented';
    case Relinked = 'exception.relinked';
    case Reclassified = 'exception.reclassified';
    case AutoResolved = 'exception.auto_resolved';
    case NeedsReview = 'exception.needs_review';
    case SignedOff = 'run.signed_off';
    case Reopened = 'run.reopened';
}
