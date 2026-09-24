<?php

declare(strict_types=1);

namespace Modules\AI\Services;

use Modules\DataProtection\Services\Pseudonymiser;
use Modules\DataProtection\Support\PersonalData;
use Modules\ExceptionManagement\Models\ReconException;
use Modules\Ingestion\Models\PaymentRecord;
use Modules\Ingestion\Models\PostingRecord;
use Modules\Ingestion\Models\SalesRecord;
use Modules\Reconciliation\Support\RuleExplanation;

final class Redactor
{
    public function __construct(private readonly Pseudonymiser $pseudonymiser) {}

    public function forException(ReconException $exception): array
    {
        $result = $exception->resultRecord();
        $sale = $result?->sale_record_id === null ? null : SalesRecord::query()->find($result->sale_record_id);
        $payments = $result === null ? collect() : PaymentRecord::query()->whereKey((array) $result->payment_record_ids)->orderBy('paid_at')->get();
        $postingBatch = $result?->runRecord()?->batches['postings']['id'] ?? null;
        $postings = $result?->transaction_id === null || $postingBatch === null
            ? collect()
            : PostingRecord::query()->where('batch_id', $postingBatch)->where('transaction_id', $result->transaction_id)->orderBy('id')->get();

        return $this->redact([
            'exception' => [
                'business_date' => $exception->business_date->toDateString(),
                'status' => $exception->status->value,
                'category' => $exception->category,
                'severity' => $exception->severity->value,
                'amount_at_risk' => (string) $exception->amount_at_risk,
                'carried_forward' => $exception->soft,
            ],
            'result' => $result === null ? null : [
                'rule_id' => $result->rule_id,
                'rule_explanation' => RuleExplanation::explain($result->status, $result->rule_id, (array) $result->flags, $result->tag),
                'expected_amount' => $result->expected_amount === null ? null : (string) $result->expected_amount,
                'received_amount' => $result->actual_amount === null ? null : (string) $result->actual_amount,
                'posted_amount' => $result->posted_amount === null ? null : (string) $result->posted_amount,
                'variance' => $result->variance === null ? null : (string) $result->variance,
                'flags' => array_values(array_keys(array_filter((array) $result->flags))),
                'tag' => $result->tag,
            ],
            'sale' => $sale === null ? null : [
                'transaction_id' => $sale->transaction_id,
                'sold_at' => $sale->sold_at?->toIso8601String(),
                'region' => $sale->region,
                'product_sku' => $sale->product_sku,
                'expected_amount' => (string) $sale->expected_amount,
                'payment_reference' => $sale->payment_reference,
                'customer' => $this->token($sale->customer_phone),
                'agent' => $this->token($sale->agent_id, 'AGENT'),
            ],
            'payments' => $payments->map(fn (PaymentRecord $p): array => [
                'payment_id' => $p->payment_id,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'channel' => $p->channel,
                'amount' => (string) $p->amount,
                'reference' => $p->reference,
                'payer' => $this->token($p->payer_phone),
            ])->all(),
            'erp_postings' => $postings->map(fn (PostingRecord $p): array => [
                'journal_id' => $p->journal_id,
                'posting_date' => $p->posting_date?->toDateString(),
                'account' => $p->account,
                'amount' => (string) $p->amount,
                'status' => $p->status,
            ])->all(),
        ]);
    }

    public function redact(array $context): array
    {
        $scrubbed = PersonalData::scrub($context);

        return is_array($scrubbed) ? $scrubbed : [];
    }

    private function token(?string $value, string $prefix = 'CUST'): ?string
    {
        return $value === null || $value === '' ? null : $this->pseudonymiser->token($value, $prefix);
    }
}
