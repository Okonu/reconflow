<?php

declare(strict_types=1);

namespace Modules\Adjustments\Enums;

use Modules\Reconciliation\Enums\ReconStatus;

enum AdjustmentType: string
{
    case WriteOff = 'write_off';
    case Refund = 'refund';
    case PostMissing = 'post_missing';
    case CorrectPosting = 'correct_posting';
    case ReverseDuplicatePosting = 'reverse_duplicate_posting';
    case Suspense = 'suspense';

    public function label(): string
    {
        return match ($this) {
            self::WriteOff => 'Write off the shortfall',
            self::Refund => 'Refund the customer',
            self::PostMissing => 'Post the missing sale to the ERP',
            self::CorrectPosting => 'Correct the ERP posting',
            self::ReverseDuplicatePosting => 'Reverse the duplicate ERP journal',
            self::Suspense => 'Move the payment to suspense',
        };
    }

    public static function for(ReconStatus $status, bool $overPaid = false): array
    {
        return match ($status) {
            ReconStatus::Variance => $overPaid ? [self::Refund] : [self::WriteOff],
            ReconStatus::MissingPayment => [self::WriteOff],
            ReconStatus::DuplicatePayment => [self::Refund],
            ReconStatus::MissingPosting => [self::PostMissing],
            ReconStatus::PostingMismatch => [self::CorrectPosting],
            ReconStatus::DuplicatePosting => [self::ReverseDuplicatePosting],
            ReconStatus::UnmatchedPayment => [self::Suspense, self::Refund],
            default => [],
        };
    }
}
