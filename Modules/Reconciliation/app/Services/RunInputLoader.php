<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Ingestion\Enums\PostingStatus;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\ActiveDataset;
use Modules\Reconciliation\DTOs\RuleConfig;
use Modules\Reconciliation\Engine\EngineInput;
use Modules\Reconciliation\Engine\PaymentInput;
use Modules\Reconciliation\Engine\PostingLine;
use Modules\Reconciliation\Engine\SaleInput;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Support\Cents;

final class RunInputLoader
{
    public function __construct(
        private readonly ActiveDataset $dataset,
        private readonly ItemStateLedger $ledger,
    ) {}

    public function load(string $businessDate, RuleConfig $config, array $batchIds): EngineInput
    {
        $date = CarbonImmutable::parse($businessDate, $config->timezone)->startOfDay();
        $priorItems = $this->ledger->openPriorItems($businessDate, $config);
        $manualMatches = $this->manualMatches($businessDate);

        return new EngineInput(
            businessDate: $businessDate,
            sales: $this->sales($batchIds[SourceType::Sales->value] ?? null),
            priorItems: $priorItems,
            payments: $this->payments($batchIds[SourceType::Payments->value] ?? null, $this->claimedByPreviousDay($date), $config->timezone),
            postingsByTransaction: $this->postings($batchIds[SourceType::Postings->value] ?? null, [...$priorItems, ...array_values($manualMatches)], $config->revenueAccountPrefix),
            rejectedPairs: $this->rejectedPairs($businessDate),
            timingCutoffAt: $date->setTimeFromTimeString($config->timingCutoff)->getTimestamp(),
            dayEndsAt: $date->addDay()->getTimestamp(),
            config: $config,
            manualMatches: $manualMatches,
        );
    }

    private function sales(?int $batchId): array
    {
        if ($batchId === null) {
            return [];
        }
        $sales = [];
        foreach (DB::table('sales_records')->where('batch_id', $batchId)->orderBy('id')->cursor() as $row) {
            $sales[] = new SaleInput(
                key: 's:'.$row->id,
                transactionId: (string) $row->transaction_id,
                soldAt: (int) strtotime((string) $row->sold_at),
                phone: (string) $row->customer_phone,
                expectedCents: Cents::fromDecimal((string) $row->expected_amount),
                reference: $row->payment_reference,
                recordId: (int) $row->id,
            );
        }

        return $sales;
    }

    private function payments(?int $batchId, array $claimed, string $timezone): array
    {
        if ($batchId === null) {
            return [];
        }
        $payments = [];
        foreach (DB::table('payment_records')->where('batch_id', $batchId)->orderBy('id')->cursor() as $row) {
            $payment = new PaymentInput(
                key: 'p:'.$row->id,
                paymentId: (string) $row->payment_id,
                paidAt: (int) strtotime((string) $row->paid_at),
                phone: $row->payer_phone,
                amountCents: Cents::fromDecimal((string) $row->amount),
                reference: $row->reference,
                ownDate: (string) $row->payment_date,
                recordId: (int) $row->id,
            );
            if (! isset($claimed[$payment->identity()])) {
                $payments[] = $payment;
            }
        }

        return $payments;
    }

    private function postings(?int $batchId, array $priorItems, string $revenuePrefix): array
    {
        $batchIds = $batchId === null ? [] : [$batchId];
        foreach (array_unique(array_map(fn (SaleInput $s) => $s->priorDate, $priorItems)) as $date) {
            $prior = $this->dataset->activeBatch(SourceType::Postings, (string) $date);
            if ($prior !== null) {
                $batchIds[] = $prior->id;
            }
        }
        $byTransaction = [];
        foreach (DB::table('posting_records')->whereIn('batch_id', $batchIds)->where('status', PostingStatus::Posted->value)->where('account', 'like', $revenuePrefix.'%')->orderBy('id')->cursor() as $row) {
            $byTransaction[(string) $row->transaction_id][] = new PostingLine((string) $row->journal_id, (string) $row->transaction_id, Cents::fromDecimal((string) $row->amount));
        }

        return $byTransaction;
    }

    private function rejectedPairs(string $businessDate): array
    {
        return DB::table('recon_match_reviews')->where('decision', 'rejected')
            ->whereBetween('business_date', [CarbonImmutable::parse($businessDate)->subDays(7)->toDateString(), $businessDate])
            ->get(['transaction_id', 'payment_identity'])
            ->mapWithKeys(fn ($r) => [$r->transaction_id.'|'.$r->payment_identity => true])
            ->all();
    }

    private function manualMatches(string $businessDate): array
    {
        $rows = DB::table('recon_manual_matches as mm')
            ->join('sales_records as s', 's.id', '=', 'mm.sale_record_id')
            ->whereDate('mm.payment_date', $businessDate)
            ->get(['mm.id', 'mm.transaction_id', 'mm.sale_date', 'mm.sale_result_id', 'mm.payment_identity', 's.id as sale_id', 's.sold_at', 's.customer_phone', 's.expected_amount', 's.payment_reference']);
        $matches = [];
        foreach ($rows as $row) {
            $matches[(string) $row->payment_identity] = new SaleInput(
                key: 'manual:'.$row->id,
                transactionId: (string) $row->transaction_id,
                soldAt: (int) strtotime((string) $row->sold_at),
                phone: (string) $row->customer_phone,
                expectedCents: Cents::fromDecimal((string) $row->expected_amount),
                reference: $row->payment_reference,
                origin: SaleInput::LOOKBACK,
                recordId: (int) $row->sale_id,
                priorResultId: $row->sale_result_id === null ? null : (int) $row->sale_result_id,
                priorDate: CarbonImmutable::parse((string) $row->sale_date)->toDateString(),
            );
        }

        return $matches;
    }

    private function claimedByPreviousDay(CarbonImmutable $date): array
    {
        $run = ReconRun::query()->forDate($date->subDay()->toDateString())->latestCompleted()->first();
        if ($run === null) {
            return [];
        }
        $claimed = [];
        foreach (DB::table('recon_results')->where('run_id', $run->id)->pluck('payment_identities') as $identities) {
            foreach ((array) json_decode((string) $identities, true) as $identity) {
                $claimed[$identity] = true;
            }
        }

        return $claimed;
    }
}
