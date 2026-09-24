<?php

declare(strict_types=1);

namespace Modules\AI\Enums;

enum RecommendedAction: string
{
    case ContactCustomer = 'contact_customer';
    case WriteOff = 'write_off';
    case Refund = 'refund';
    case PostMissing = 'post_missing';
    case CorrectPosting = 'correct_posting';
    case ReverseDuplicatePosting = 'reverse_duplicate_posting';
    case MoveToSuspense = 'move_to_suspense';
    case MatchManually = 'match_manually';
    case WaitForPayment = 'wait_for_payment';
    case Investigate = 'investigate';
    case NoAction = 'no_action';

    public function label(): string
    {
        return match ($this) {
            self::ContactCustomer => 'Contact the customer',
            self::WriteOff => 'Propose a write-off',
            self::Refund => 'Propose a refund',
            self::PostMissing => 'Post the missing sale to the ERP',
            self::CorrectPosting => 'Correct the ERP posting',
            self::ReverseDuplicatePosting => 'Reverse the duplicate ERP posting',
            self::MoveToSuspense => 'Move to suspense',
            self::MatchManually => 'Match manually to a payment',
            self::WaitForPayment => 'Wait for the payment',
            self::Investigate => 'Investigate further',
            self::NoAction => 'No action needed',
        };
    }
}
