<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use Modules\Reconciliation\Engine\ResultItem;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;
use Modules\Reconciliation\Enums\RollUp;
use Modules\Reconciliation\Support\Cents;

final class RunSummary
{
    public function build(array $items, array $escalations): array
    {
        $byStatus = array_fill_keys(array_column(ReconStatus::cases(), 'value'), 0);
        $byRollUp = array_fill_keys(array_column(RollUp::cases(), 'value'), 0);
        $saleItems = 0;
        $matchedSales = 0;
        $expectedTotal = 0;
        $reconciled = 0;
        $atVariance = 0;
        $unmatchedPaymentValue = 0;
        $exceptions = 0;
        $priorCount = 0;
        $priorValue = 0;

        foreach ($items as $item) {
            assert($item instanceof ResultItem);
            if ($item->section === ResultSection::PriorDay) {
                $priorCount++;
                $priorValue += $item->actualCents() ?? 0;

                continue;
            }
            $byStatus[$item->status->value]++;
            $rollUp = $item->status->rollUp();
            $byRollUp[$rollUp->value]++;
            if (in_array($rollUp, [RollUp::Exception, RollUp::ExceptionSoft], true)) {
                $exceptions++;
            }
            if ($item->sale !== null) {
                $saleItems++;
                $expectedTotal += $item->sale->expectedCents;
                if ($rollUp->isMatch()) {
                    $matchedSales++;
                    $reconciled += $item->sale->expectedCents;
                }
            }
            $atVariance += match ($item->status) {
                ReconStatus::Variance => abs($item->varianceCents() ?? 0),
                ReconStatus::PostingMismatch => abs(($item->postedCents ?? 0) - ($item->expectedCents() ?? 0)),
                default => 0,
            };
            if ($item->status === ReconStatus::UnmatchedPayment) {
                $unmatchedPaymentValue += $item->actualCents() ?? 0;
            }
        }

        return [
            'items' => array_sum($byStatus),
            'sale_items' => $saleItems,
            'by_status' => $byStatus,
            'by_roll_up' => $byRollUp,
            'match_rate' => Cents::percent($matchedSales, $saleItems) ?? '0.00',
            'value_expected' => Cents::toDecimal($expectedTotal),
            'value_reconciled' => Cents::toDecimal($reconciled),
            'value_at_variance' => Cents::toDecimal($atVariance),
            'value_unmatched_payments' => Cents::toDecimal($unmatchedPaymentValue),
            'exceptions' => $exceptions,
            'prior_day_cleared' => ['count' => $priorCount, 'value' => Cents::toDecimal($priorValue)],
            'escalated_from_prior_day' => count($escalations),
        ];
    }
}
