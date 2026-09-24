<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ingestion\Models\SourceBatch;

final class SourceBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $batch = $this->resource;
        assert($batch instanceof SourceBatch);

        return [
            'id' => $batch->id,
            'source' => $batch->source->value,
            'source_label' => $batch->source->label(),
            'business_date' => $batch->business_date->toDateString(),
            'version' => $batch->version,
            'origin' => $batch->origin->value,
            'status' => $batch->status->value,
            'mode' => $batch->mode->value,
            'mode_label' => $batch->mode->label(),
            'manual' => $batch->manual,
            'parent_batch_id' => $batch->parent_batch_id,
            'rows_added' => $batch->rows_added,
            'filename' => $batch->filename,
            'checksum' => $batch->checksum,
            'rows_received' => $batch->rows_received,
            'rows_loaded' => $batch->rows_loaded,
            'rows_quarantined' => $batch->rows_quarantined,
            'quarantine_reasons' => $batch->dq_summary['reasons'] ?? [],
            'empty' => (bool) ($batch->dq_summary['empty'] ?? false),
            'extracted_at' => $batch->extracted_at?->toIso8601String(),
            'created_by' => $batch->creator?->getAttribute('name'),
            'superseded_by_id' => $batch->superseded_by_id,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }
}
