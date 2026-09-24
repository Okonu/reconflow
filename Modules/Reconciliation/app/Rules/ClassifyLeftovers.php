<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Rules;

use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\MatchState;
use Modules\Reconciliation\Engine\ResultItem;
use Modules\Reconciliation\Engine\SaleInput;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;

final class ClassifyLeftovers
{
    public const TIE_RULE = 'R3 tie → R7';

    public function __invoke(MatchState $state, EngineInput $input): MatchState
    {
        $next = clone $state;

        foreach ($state->sales as $key => $sale) {
            $posted = EvaluatePairs::posted($input->postingsByTransaction[$sale->transactionId] ?? []);
            $next->items[] = new ResultItem(
                section: ResultSection::Current,
                status: $sale->soldAt >= $input->timingCutoffAt ? ReconStatus::PendingTiming : ReconStatus::MissingPayment,
                ruleId: isset($state->tiedSales[$key]) ? self::TIE_RULE : 'R7',
                sale: $sale,
                postedCents: $posted['amount'],
                journalId: $posted['journal'],
                flags: ['tie' => isset($state->tiedSales[$key])],
            );
        }

        foreach ($state->payments as $key => $payment) {
            if ($payment->paidAt >= $input->dayEndsAt) {
                continue;
            }
            $next->items[] = new ResultItem(
                section: ResultSection::Current,
                status: ReconStatus::UnmatchedPayment,
                ruleId: isset($state->tiedPayments[$key]) ? self::TIE_RULE : 'R7',
                payments: [$payment],
                flags: ['tie' => isset($state->tiedPayments[$key])],
            );
        }

        $next->sales = [];
        $next->payments = [];

        return $next;
    }

    public static function escalations(MatchState $state, EngineInput $input): array
    {
        $escalations = [];
        foreach ($state->priorItems as $item) {
            if ($item->origin !== SaleInput::CARRIED || $item->priorResultId === null) {
                continue;
            }
            $days = intdiv(strtotime($input->businessDate.' 00:00:00 UTC') - strtotime($item->priorDate.' 00:00:00 UTC'), 86400);
            if ($days >= $input->config->timingCarryDays) {
                $escalations[] = $item->priorResultId;
            }
        }

        return $escalations;
    }
}
