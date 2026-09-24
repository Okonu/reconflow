<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Services;

use Modules\DataProtection\Support\PersonalData;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Models\PaymentRecord;
use Modules\Ingestion\Models\PostingRecord;
use Modules\Ingestion\Models\SalesRecord;

final class SourceRecords
{
    public function for(ReconException $exception): array
    {
        $result = $exception->resultRecord();
        if ($result === null) {
            return ['sale' => null, 'payments' => [], 'postings' => []];
        }
        $sale = $result->sale_record_id === null ? null : SalesRecord::query()->find($result->sale_record_id);
        $payments = PaymentRecord::query()->whereKey((array) $result->payment_record_ids)->orderBy('paid_at')->get();
        $postingBatch = $result->runRecord()?->batches['postings']['id'] ?? null;
        $postings = $result->transaction_id === null || $postingBatch === null
            ? collect()
            : PostingRecord::query()->where('batch_id', $postingBatch)->where('transaction_id', $result->transaction_id)->orderBy('id')->get();

        return [
            'sale' => $sale === null ? null : PersonalData::maskRecord('sales', [
                'transaction_id' => $sale->transaction_id,
                'timestamp' => $sale->sold_at?->toIso8601String(),
                'agent_id' => $sale->agent_id,
                'customer_phone' => $sale->customer_phone,
                'region' => $sale->region,
                'product_sku' => $sale->product_sku,
                'expected_amount' => (string) $sale->expected_amount,
                'payment_reference' => $sale->payment_reference,
            ]),
            'payments' => $payments->map(fn (PaymentRecord $p) => PersonalData::maskRecord('payments', [
                'payment_id' => $p->payment_id,
                'timestamp' => $p->paid_at?->toIso8601String(),
                'channel' => $p->channel,
                'payer_phone' => $p->payer_phone,
                'amount' => (string) $p->amount,
                'reference' => $p->reference,
            ]))->all(),
            'postings' => $postings->map(fn (PostingRecord $p) => [
                'journal_id' => $p->journal_id,
                'posting_date' => $p->posting_date?->toDateString(),
                'account' => $p->account,
                'amount' => (string) $p->amount,
                'status' => $p->status,
            ])->all(),
        ];
    }
}
