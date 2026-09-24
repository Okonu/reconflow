<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Enums;

use Modules\Reconciliation\Enums\ReconStatus;

enum StatusFamily: string
{
    case Payment = 'payment';
    case AmountVariance = 'amount_variance';
    case Posting = 'posting';
    case Unmatched = 'unmatched';
    case Duplicate = 'duplicate';

    public static function of(ReconStatus $status): ?self
    {
        return match ($status) {
            ReconStatus::MissingPayment, ReconStatus::PendingTiming => self::Payment,
            ReconStatus::Variance => self::AmountVariance,
            ReconStatus::PostingMismatch, ReconStatus::MissingPosting, ReconStatus::DuplicatePosting => self::Posting,
            ReconStatus::UnmatchedPayment => self::Unmatched,
            ReconStatus::DuplicatePayment => self::Duplicate,
            default => null,
        };
    }
}
