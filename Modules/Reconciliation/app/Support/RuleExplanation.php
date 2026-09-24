<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Support;

use Modules\Reconciliation\Enums\ReconStatus;

final class RuleExplanation
{
    public static function explain(ReconStatus $status, string $rule, array $flags = [], ?string $tag = null): string
    {
        $text = match ($status) {
            ReconStatus::Matched => 'The payment reference equals the sale ID, the amount agrees exactly and the ERP posting matches.',
            ReconStatus::MatchedTolerance => 'The payment reference equals the sale ID and the amount is within the $0.50 rounding tolerance.',
            ReconStatus::MatchedSplit => 'Several payments reference this sale and together equal the expected amount (within tolerance).',
            ReconStatus::MatchedFuzzy => 'The payment had no usable reference. It was matched on the same phone number, an amount within tolerance and a time within 24 hours, with no other candidate. It needs confirmation.',
            ReconStatus::MatchedPriorDay => 'A payment received on this date cleared an item that was open from an earlier date.',
            ReconStatus::Variance => ($flags['split'] ?? false)
                ? 'Several payments reference this sale, but together they differ from the expected amount by more than the tolerance.'
                : 'The payment references this sale, but the amount differs from the expected amount by more than the $0.50 tolerance.',
            ReconStatus::PostingMismatch => 'The sale and payment agree, but the amount posted in the ERP differs from the expected amount.',
            ReconStatus::MissingPosting => 'The sale and payment agree, but there is no POSTED ERP journal line for this sale.',
            ReconStatus::DuplicatePosting => 'The ERP has two different POSTED journals for this sale.',
            ReconStatus::MissingPayment => $rule === 'R3 tie → R7'
                ? 'No payment references this sale. A payment could fit this sale and another one equally well, so it was not matched automatically.'
                : 'No payment was found for this sale by reference, by instalments or by phone, amount and time.',
            ReconStatus::PendingTiming => 'The sale was recorded at or after the 22:00 cut-off with no payment yet. It is carried into the next run and escalates if still unpaid.',
            ReconStatus::UnmatchedPayment => $rule === 'R3 tie → R7'
                ? 'This payment could fit more than one sale equally well, so it was not matched automatically.'
                : 'This payment matches no sale by reference, instalments, or phone, amount and time.',
            ReconStatus::DuplicatePayment => ($flags['same_receipt'] ?? false)
                ? 'The same receipt number appears twice; this is the extra copy.'
                : 'The same reference and amount were paid again within 5 minutes; this is the second payment.',
        };

        return $tag === null ? $text : "{$text} ({$tag})";
    }
}
