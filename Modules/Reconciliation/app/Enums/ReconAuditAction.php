<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

enum ReconAuditAction: string
{
    case RunQueued = 'run.queued';
    case RunCompleted = 'run.completed';
    case RunBlocked = 'run.blocked';
    case RunFailed = 'run.failed';
    case RunMarkedStale = 'run.marked_stale';
    case RuleConfigCreated = 'config.created';
    case ItemResolved = 'item.resolved';
    case ItemEscalated = 'item.escalated';
    case ItemReclassified = 'item.reclassified';
    case ItemReopened = 'item.reopened';
    case ManualMatchConfirmed = 'match.manual_confirmed';
    case FuzzyMatchConfirmed = 'match.fuzzy_confirmed';
    case FuzzyMatchRejected = 'match.fuzzy_rejected';
}
