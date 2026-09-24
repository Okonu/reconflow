<?php

declare(strict_types=1);

namespace Modules\Adjustments\Services;

use App\Exceptions\DomainException;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Modules\Adjustments\Enums\AdjustmentType;
use Modules\ExceptionManagement\Models\ReconException;

final class JournalBuilder
{
    public function build(AdjustmentType $type, ReconException $exception, BigDecimal $amount): array
    {
        $accounts = (array) config('adjustments.accounts');
        $txn = $exception->transaction_id;
        $value = (string) $amount->toScale(2);
        $pair = fn (string $debit, string $credit, ?string $debitTxn = null, ?string $creditTxn = null): array => [
            ['account' => $accounts[$debit], 'debit' => $value, 'credit' => '0.00', 'transaction_id' => $debitTxn],
            ['account' => $accounts[$credit], 'debit' => '0.00', 'credit' => $value, 'transaction_id' => $creditTxn],
        ];
        $reference = $txn ?? implode(',', (array) $exception->payment_ids);

        $journal = match ($type) {
            AdjustmentType::WriteOff => ['lines' => $pair('write_off', 'receivable')],
            AdjustmentType::Refund => ['lines' => $pair('receivable', 'refunds_payable')],
            AdjustmentType::Suspense => ['lines' => $pair('receivable', 'suspense')],
            AdjustmentType::PostMissing => ['lines' => $pair('receivable', 'revenue', null, $txn)],
            AdjustmentType::CorrectPosting => [
                'reverse_journal_id' => $this->journalsFor($exception)[0] ?? throw DomainException::invalid('No ERP journal found to correct.'),
                'lines' => [
                    ['account' => $accounts['receivable'], 'debit' => (string) $exception->resultRecord()?->expected_amount, 'credit' => '0.00', 'transaction_id' => null],
                    ['account' => $accounts['revenue'], 'debit' => '0.00', 'credit' => (string) $exception->resultRecord()?->expected_amount, 'transaction_id' => $txn],
                ],
            ],
            AdjustmentType::ReverseDuplicatePosting => [
                'reverse_journal_id' => array_slice($this->journalsFor($exception), -1)[0] ?? throw DomainException::invalid('No duplicate journal found to reverse.'),
                'lines' => [],
            ],
        };

        return [
            'posting_date' => $exception->business_date->toDateString(),
            'reference' => "ReconFlow exception #{$exception->id} ({$reference})",
            ...$journal,
        ];
    }

    private function journalsFor(ReconException $exception): array
    {
        $batch = $exception->resultRecord()?->runRecord()?->batches['postings']['id'] ?? null;
        if ($batch === null || $exception->transaction_id === null) {
            return [];
        }

        return DB::table('posting_records')->where('batch_id', $batch)->where('transaction_id', $exception->transaction_id)
            ->where('status', 'POSTED')->orderBy('journal_id')->pluck('journal_id')->unique()->values()->all();
    }
}
