<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Enums;

enum ReconStatus: string
{
    case Matched = 'MATCHED';
    case MatchedTolerance = 'MATCHED_TOLERANCE';
    case MatchedSplit = 'MATCHED_SPLIT';
    case MatchedFuzzy = 'MATCHED_FUZZY';
    case MatchedPriorDay = 'MATCHED_PRIOR_DAY';
    case Variance = 'VARIANCE';
    case PostingMismatch = 'POSTING_MISMATCH';
    case MissingPayment = 'MISSING_PAYMENT';
    case PendingTiming = 'PENDING_TIMING';
    case UnmatchedPayment = 'UNMATCHED_PAYMENT';
    case MissingPosting = 'MISSING_POSTING';
    case DuplicatePayment = 'DUPLICATE_PAYMENT';
    case DuplicatePosting = 'DUPLICATE_POSTING';

    public function rollUp(): RollUp
    {
        return match ($this) {
            self::Matched, self::MatchedTolerance, self::MatchedSplit => RollUp::Match,
            self::MatchedFuzzy => RollUp::MatchFlagged,
            self::MatchedPriorDay => RollUp::MatchPriorDay,
            self::Variance, self::PostingMismatch => RollUp::Variance,
            self::PendingTiming => RollUp::ExceptionSoft,
            self::MissingPayment, self::UnmatchedPayment, self::MissingPosting, self::DuplicatePayment, self::DuplicatePosting => RollUp::Exception,
        };
    }

    public function label(): string
    {
        return ucfirst(strtolower(str_replace('_', ' ', $this->value)));
    }

    public function needsPostingCheck(): bool
    {
        return in_array($this, [self::Matched, self::MatchedTolerance, self::MatchedSplit, self::MatchedFuzzy, self::MatchedPriorDay], true);
    }
}
