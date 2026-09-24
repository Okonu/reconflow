<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\MockSourceRow;
use Modules\Ingestion\Support\Schema\ValidationContext;

final class MockSourceStore
{
    private const CHUNK = 1000;

    public function extract(SourceType $source, string $businessDate): array
    {
        $query = MockSourceRow::query()->where('source', $source->value);

        if ($source === SourceType::Payments) {
            $context = ValidationContext::forDate($businessDate);
            $query->where(function ($q) use ($businessDate, $context): void {
                $q->where(fn ($inner) => $inner->whereNull('occurred_at')->whereDate('record_date', $businessDate))
                    ->orWhere(fn ($inner) => $inner
                        ->where('occurred_at', '>=', $context->windowStart()->utc())
                        ->where('occurred_at', '<', $context->windowEnd()->utc()));
            });
            $query->orderBy('occurred_at')->orderBy('record_date')->orderBy('sequence');
        } else {
            $query->whereDate('record_date', $businessDate)->orderBy('sequence');
        }

        return $query->pluck('payload')->all();
    }

    public function replaceDay(SourceType $source, string $recordDate, array $rows): void
    {
        DB::table('mock_source_rows')->where('source', $source->value)->whereDate('record_date', $recordDate)->delete();
        $sequence = 0;
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('mock_source_rows')->insert(array_map(function (array $row) use ($source, $recordDate, &$sequence): array {
                $sequence++;

                return [
                    'source' => $source->value,
                    'record_date' => $recordDate,
                    'occurred_at' => $this->occurredAt($source, $row),
                    'sequence' => $sequence,
                    'payload' => json_encode($row, JSON_THROW_ON_ERROR),
                ];
            }, $chunk));
        }
    }

    public function appendRows(SourceType $source, string $recordDate, array $rows): void
    {
        $sequence = (int) DB::table('mock_source_rows')->where('source', $source->value)->whereDate('record_date', $recordDate)->max('sequence');
        foreach ($rows as $row) {
            $sequence++;
            DB::table('mock_source_rows')->insert([
                'source' => $source->value,
                'record_date' => $recordDate,
                'occurred_at' => $this->occurredAt($source, $row),
                'sequence' => $sequence,
                'payload' => json_encode($row, JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function reverseJournal(string $journalId): int
    {
        return DB::table('mock_source_rows')
            ->where('source', SourceType::Postings->value)
            ->whereRaw("payload->>'journal_id' = ?", [$journalId])
            ->update(['payload' => DB::raw("jsonb_set(payload, '{status}', '\"REVERSED\"')")]);
    }

    public function dates(): array
    {
        return MockSourceRow::query()->where('source', SourceType::Sales->value)
            ->distinct()->orderBy('record_date')->pluck('record_date')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())->all();
    }

    public function truncate(): void
    {
        DB::table('mock_source_rows')->truncate();
    }

    private function occurredAt(SourceType $source, array $row): ?string
    {
        if ($source !== SourceType::Payments) {
            return null;
        }
        $timestamp = $row['timestamp'] ?? null;
        if (! is_string($timestamp) || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp) !== 1) {
            return null;
        }
        $parsed = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $timestamp, (string) config('reconflow.display_timezone'));

        return $parsed === null ? null : $parsed->utc()->toIso8601String();
    }
}
