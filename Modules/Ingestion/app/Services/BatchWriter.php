<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Ingestion\DTOs\BatchMeta;
use Modules\Ingestion\DTOs\ValidationOutcome;
use Modules\Ingestion\Enums\BatchStatus;
use Modules\Ingestion\Enums\ImportMode;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\SourceBatch;
use Modules\Ingestion\Support\Schema\RowResult;

final class BatchWriter
{
    private const CHUNK = 1000;

    public function write(BatchMeta $meta, ValidationOutcome $outcome): SourceBatch
    {
        $version = (int) SourceBatch::query()->for($meta->source, $meta->businessDate)->max('version') + 1;
        $valid = $outcome->valid();
        $invalid = $outcome->invalid();

        $batch = SourceBatch::query()->create([
            'source' => $meta->source,
            'business_date' => $meta->businessDate,
            'version' => $version,
            'origin' => $meta->origin,
            'status' => BatchStatus::Active,
            'mode' => $meta->mode,
            'filename' => $meta->filename,
            'checksum' => $meta->checksum,
            'rows_received' => count($outcome->rows),
            'rows_loaded' => count($valid),
            'rows_quarantined' => count($invalid),
            'dq_summary' => $this->summary($meta, $outcome),
            'extracted_at' => $meta->extractedAt,
            'created_by' => $meta->createdBy,
        ]);

        if ($meta->mode === ImportMode::Replace) {
            SourceBatch::query()->active()->for($meta->source, $meta->businessDate)
                ->whereKeyNot($batch->id)
                ->update(['status' => BatchStatus::Superseded->value, 'superseded_by_id' => $batch->id]);
        }

        $this->insertRecords($meta->source, $batch, $valid);
        $this->insertQuarantine($meta->source, $batch, $invalid);

        return $batch;
    }

    private function insertRecords(SourceType $source, SourceBatch $batch, array $rows): void
    {
        $date = $batch->business_date->toDateString();
        $timezone = (string) config('reconflow.display_timezone');
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($this->table($source))->insert(array_map(
                fn (RowResult $row): array => $this->record($source, $batch->id, $date, $timezone, $row),
                $chunk,
            ));
        }
    }

    private function record(SourceType $source, int $batchId, string $date, string $timezone, RowResult $row): array
    {
        $v = $row->values;
        $base = ['batch_id' => $batchId, 'business_date' => $date, 'row_number' => $row->rowNumber];

        return match ($source) {
            SourceType::Sales => $base + [
                'transaction_id' => $v['transaction_id'],
                'sold_at' => $v['timestamp'],
                'agent_id' => $v['agent_id'],
                'customer_phone' => $v['customer_phone'],
                'region' => $v['region'],
                'product_sku' => $v['product_sku'],
                'expected_amount' => $v['expected_amount'],
                'currency' => $v['currency'],
                'payment_reference' => $v['payment_reference'],
            ],
            SourceType::Payments => $base + [
                'payment_date' => CarbonImmutable::parse((string) $v['timestamp'])->setTimezone($timezone)->toDateString(),
                'payment_id' => $v['payment_id'],
                'paid_at' => $v['timestamp'],
                'channel' => $v['channel'],
                'payer_phone' => $v['payer_phone'],
                'amount' => $v['amount'],
                'currency' => $v['currency'],
                'reference' => $v['reference'],
            ],
            SourceType::Postings => $base + [
                'journal_id' => $v['journal_id'],
                'posting_date' => $v['posting_date'],
                'transaction_id' => $v['transaction_id'],
                'account' => $v['account'],
                'amount' => $v['amount'],
                'currency' => $v['currency'],
                'status' => $v['status'],
            ],
        };
    }

    private function insertQuarantine(SourceType $source, SourceBatch $batch, array $rows): void
    {
        $now = now();
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('quarantined_rows')->insert(array_map(fn (RowResult $row): array => [
                'batch_id' => $batch->id,
                'source' => $source->value,
                'business_date' => $batch->business_date->toDateString(),
                'row_number' => $row->rowNumber,
                'record_key' => mb_substr($row->recordKey, 0, 128),
                'reasons' => json_encode($row->errors, JSON_THROW_ON_ERROR),
                'raw' => json_encode($row->raw, JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ], $chunk));
        }
    }

    private function summary(BatchMeta $meta, ValidationOutcome $outcome): array
    {
        $received = count($outcome->rows);

        return [
            'rows_received' => $received,
            'rows_loaded' => count($outcome->valid()),
            'rows_quarantined' => count($outcome->invalid()),
            'reasons' => $outcome->reasonCounts(),
            'empty' => $received === 0,
            'mode' => $meta->mode->value,
            'origin' => $meta->origin->value,
        ];
    }

    private function table(SourceType $source): string
    {
        return match ($source) {
            SourceType::Sales => 'sales_records',
            SourceType::Payments => 'payment_records',
            SourceType::Postings => 'posting_records',
        };
    }
}
