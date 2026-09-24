<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Ingestion\Enums\SourceType;
use RuntimeException;

final class MockErp
{
    public const FAILURE_FLAG = 'mock_erp.simulate_failure';

    public function __construct(private readonly MockSourceStore $store) {}

    public function simulatingFailure(): bool
    {
        return (bool) Cache::get(self::FAILURE_FLAG, false);
    }

    public function setFailureSimulation(bool $on): void
    {
        Cache::forever(self::FAILURE_FLAG, $on);
    }

    public function post(array $request): array
    {
        return DB::transaction(function () use ($request): array {
            DB::select('select pg_advisory_xact_lock(?)', [crc32('mock-erp')]);
            $existing = DB::table('mock_erp_journals')->where('idempotency_key', $request['idempotency_key'])->first();
            if ($existing !== null) {
                return [...(array) json_decode((string) $existing->response, true), 'replayed' => true];
            }

            $reversal = $request['reverse_journal_id'] ?? null;
            $lines = (array) ($request['lines'] ?? []);
            if ($reversal === null || $lines !== []) {
                $this->assertBalanced($lines);
            }
            $sequence = (int) DB::table('mock_erp_journals')->count() + 1;
            $journalId = sprintf('ADJ-%s-%06d', substr((string) $request['posting_date'], 0, 4), $sequence);

            if ($reversal !== null && $this->store->reverseJournal((string) $reversal) === 0) {
                throw new RuntimeException("Journal {$reversal} was not found in the ERP.");
            }
            if ($lines !== []) {
                $this->store->appendRows(SourceType::Postings, (string) $request['posting_date'], $this->postingRows($journalId, $request));
            }

            $response = [
                'journal_id' => $journalId,
                'status' => 'POSTED',
                'posting_date' => $request['posting_date'],
                'reversal_of' => $reversal,
                'posted_at' => now()->toIso8601String(),
                'simulated' => true,
            ];
            DB::table('mock_erp_journals')->insert([
                'idempotency_key' => $request['idempotency_key'],
                'journal_id' => $journalId,
                'posting_date' => $request['posting_date'],
                'reversal_of' => $reversal,
                'request' => json_encode($request, JSON_THROW_ON_ERROR),
                'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);

            return [...$response, 'replayed' => false];
        });
    }

    private function assertBalanced(array $lines): void
    {
        $debit = BigDecimal::zero();
        $credit = BigDecimal::zero();
        foreach ($lines as $line) {
            $debit = $debit->plus(BigDecimal::of((string) ($line['debit'] ?? '0')));
            $credit = $credit->plus(BigDecimal::of((string) ($line['credit'] ?? '0')));
        }
        if ($lines === [] || ! $debit->isEqualTo($credit) || $debit->isZero()) {
            throw new RuntimeException("Journal is not balanced (debit {$debit}, credit {$credit}).");
        }
    }

    private function postingRows(string $journalId, array $request): array
    {
        $rows = [];
        foreach ($request['lines'] as $line) {
            if (empty($line['transaction_id'])) {
                continue;
            }
            $amount = BigDecimal::of((string) ($line['credit'] ?? '0'))->isPositive() ? (string) $line['credit'] : (string) $line['debit'];
            $rows[] = [
                'journal_id' => $journalId,
                'posting_date' => $request['posting_date'],
                'transaction_id' => $line['transaction_id'],
                'account' => $line['account'],
                'amount' => (string) BigDecimal::of($amount)->toScale(2),
                'currency' => 'USD',
                'status' => 'POSTED',
            ];
        }

        return $rows;
    }
}
