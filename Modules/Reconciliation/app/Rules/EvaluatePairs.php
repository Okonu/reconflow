<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Rules;

use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\MatchState;
use Modules\Reconciliation\Engine\Pair;
use Modules\Reconciliation\Engine\PostingLine;
use Modules\Reconciliation\Engine\ResultItem;
use Modules\Reconciliation\Engine\SaleInput;
use Modules\Reconciliation\Enums\ReconStatus;
use Modules\Reconciliation\Enums\ResultSection;

final class EvaluatePairs
{
    public function __invoke(MatchState $state, EngineInput $input): MatchState
    {
        $next = clone $state;
        foreach ($state->pairs as $pair) {
            $next->items[] = $this->evaluate($pair, $input, ResultSection::Current);
        }
        foreach ($state->priorPairs as $pair) {
            $next->items[] = $this->evaluate($pair, $input, ResultSection::PriorDay);
        }

        return $next;
    }

    private function evaluate(Pair $pair, EngineInput $input, ResultSection $section): ResultItem
    {
        $lines = $input->postingsByTransaction[$pair->sale->transactionId] ?? [];
        $posted = self::posted($lines);
        $difference = $pair->actualCents() - $pair->sale->expectedCents;
        $split = count($pair->payments) > 1;
        $flags = ['split' => $split];
        $tag = $section === ResultSection::PriorDay ? self::priorTag($pair->sale, $input->businessDate) : null;

        if (abs($difference) > $input->config->toleranceCents()) {
            return new ResultItem($section, ReconStatus::Variance, $split ? 'R2+R4' : 'R4', $pair->sale, $pair->payments, $posted['amount'], $posted['journal'], $pair->confidence, $tag, $flags);
        }

        [$status, $rule] = match (true) {
            $section === ResultSection::PriorDay => [ReconStatus::MatchedPriorDay, $split ? 'R2' : 'R1+R4+R6'],
            $pair->rule === 'R3' => [ReconStatus::MatchedFuzzy, 'R3'],
            $pair->rule === 'R2' => [ReconStatus::MatchedSplit, 'R2'],
            $difference === 0 => [ReconStatus::Matched, 'R1+R4+R6'],
            default => [ReconStatus::MatchedTolerance, 'R1+R4'],
        };

        $postingStatus = self::postingStatus($lines, $pair->sale->expectedCents);
        if ($postingStatus !== null) {
            [$status, $rule] = [$postingStatus, 'R6'];
        }

        return new ResultItem($section, $status, $rule, $pair->sale, $pair->payments, $posted['amount'], $posted['journal'], $pair->confidence, $tag, $flags);
    }

    public static function postingStatus(array $lines, int $expectedCents): ?ReconStatus
    {
        $journals = array_unique(array_map(fn (PostingLine $l): string => $l->journalId, $lines));

        return match (true) {
            $journals === [] => ReconStatus::MissingPosting,
            count($journals) > 1 => ReconStatus::DuplicatePosting,
            $lines[0]->amountCents !== $expectedCents => ReconStatus::PostingMismatch,
            default => null,
        };
    }

    public static function posted(array $lines): array
    {
        if ($lines === []) {
            return ['amount' => null, 'journal' => null];
        }

        return [
            'amount' => array_sum(array_map(fn (PostingLine $l): int => $l->amountCents, $lines)),
            'journal' => $lines[0]->journalId,
        ];
    }

    public static function priorTag(SaleInput $sale, string $businessDate): string
    {
        if ($sale->origin === SaleInput::CARRIED) {
            return 'Paid next day';
        }
        $days = intdiv(strtotime($businessDate.' 00:00:00 UTC') - strtotime($sale->priorDate.' 00:00:00 UTC'), 86400);

        return "Paid late (D+{$days})";
    }
}
