<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use Illuminate\Support\Facades\DB;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Models\SourceBatch;

final class ActiveDataset
{
    public function activeBatch(SourceType $source, string $date): ?SourceBatch
    {
        return SourceBatch::query()->active()->for($source, $date)->first();
    }

    public function hasData(SourceType $source, string $date): bool
    {
        return $this->activeBatch($source, $date) !== null;
    }

    public function activeBatchIds(SourceType $source, string $date): array
    {
        return SourceBatch::query()->active()->for($source, $date)->pluck('id')->all();
    }

    public function existingKeys(SourceType $source, string $date): array
    {
        $column = $source->schema()->uniqueKeyColumn();
        $batch = $this->activeBatch($source, $date);
        if ($column === null || $batch === null) {
            return [];
        }

        return DB::table(BatchWriter::table($source))
            ->where('batch_id', $batch->id)
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
