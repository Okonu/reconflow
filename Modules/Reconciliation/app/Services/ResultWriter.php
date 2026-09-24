<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use Illuminate\Support\Facades\DB;
use Modules\Reconciliation\Engine\PaymentInput;
use Modules\Reconciliation\Engine\ResultItem;
use Modules\Reconciliation\Enums\ResultSection;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Support\Cents;

final class ResultWriter
{
    private const CHUNK = 1000;

    public function write(ReconRun $run, array $items): array
    {
        $date = $run->business_date->toDateString();
        $priorResults = [];

        $current = [];
        foreach ($items as $item) {
            if ($item->section === ResultSection::PriorDay) {
                $priorResults[] = [$item, (int) DB::table('recon_results')->insertGetId($this->row($run->id, $date, $item))];
            } else {
                $current[] = $item;
            }
        }
        foreach (array_chunk($current, self::CHUNK) as $chunk) {
            DB::table('recon_results')->insert(array_map(fn (ResultItem $item): array => $this->row($run->id, $date, $item), $chunk));
        }

        return $priorResults;
    }

    private function row(int $runId, string $date, ResultItem $item): array
    {
        $expected = $item->expectedCents();
        $variance = $item->varianceCents();

        return [
            'run_id' => $runId,
            'business_date' => $date,
            'section' => $item->section->value,
            'transaction_id' => $item->transactionId(),
            'payment_ids' => json_encode($item->paymentIds(), JSON_THROW_ON_ERROR),
            'journal_id' => $item->journalId,
            'expected_amount' => Cents::toDecimal($expected),
            'actual_amount' => Cents::toDecimal($item->actualCents()),
            'posted_amount' => Cents::toDecimal($item->postedCents),
            'variance' => Cents::toDecimal($variance),
            'variance_pct' => $variance === null || $expected === null ? null : Cents::percent($variance, $expected),
            'status' => $item->status->value,
            'roll_up' => $item->status->rollUp()->value,
            'rule_id' => $item->ruleId,
            'match_confidence' => $item->confidence,
            'tag' => $item->tag,
            'prior_result_id' => $item->sale?->priorResultId,
            'prior_date' => $item->sale?->priorDate,
            'sale_record_id' => $item->sale?->recordId,
            'payment_record_ids' => json_encode(array_map(fn (PaymentInput $p) => $p->recordId, $item->payments), JSON_THROW_ON_ERROR),
            'payment_identities' => json_encode(array_map(fn (PaymentInput $p) => $p->identity(), $item->payments), JSON_THROW_ON_ERROR),
            'flags' => json_encode($item->flags, JSON_THROW_ON_ERROR),
        ];
    }
}
