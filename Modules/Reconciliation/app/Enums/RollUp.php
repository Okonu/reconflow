<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

enum RollUp: string
{
    case Match = 'Match';
    case MatchFlagged = 'Match (flagged)';
    case MatchPriorDay = 'Match (prior day)';
    case Variance = 'Variance';
    case Exception = 'Exception';
    case ExceptionSoft = 'Exception (soft)';

    public function isMatch(): bool
    {
        return in_array($this, [self::Match, self::MatchFlagged, self::MatchPriorDay], true);
    }
}
