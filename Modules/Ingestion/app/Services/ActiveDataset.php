<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\SourceBatch;

final class ActiveDataset
{
    public function hasData(SourceType $source, string $date): bool
    {
        return SourceBatch::query()->active()->for($source, $date)->exists();
    }

    public function activeBatchIds(SourceType $source, string $date): array
    {
        return SourceBatch::query()->active()->for($source, $date)->pluck('id')->all();
    }

    public function existingKeys(SourceType $source, string $date): array
    {
        $column = $source->schema()->uniqueKeyColumn();
        if ($column === null) {
            return [];
        }
        $table = match ($source) {
            SourceType::Sales => 'sales_records',
            SourceType::Postings => 'posting_records',
            SourceType::Payments => 'payment_records',
        };

        return DB::table($table)
            ->whereIn('batch_id', $this->activeBatchIds($source, $date))
            ->pluck($column)
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    public function batchWithChecksum(SourceType $source, string $date, string $checksum): ?SourceBatch
    {
        return SourceBatch::query()->for($source, $date)->where('checksum', $checksum)->latest('id')->first();
    }
}
