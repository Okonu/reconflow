<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Ingestion\Enums\StagingState;
use Modules\Ingestion\Models\UploadStaging;

final class UploadStagingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staging = $this->resource;
        assert($staging instanceof UploadStaging);
        $open = $staging->state === StagingState::Staged && ! $staging->expires_at->isPast();

        return [
            'id' => $staging->id,
            'source' => $staging->source->value,
            'source_label' => $staging->source->label(),
            'business_date' => $staging->business_date->toDateString(),
            'filename' => $staging->filename,
            'size_bytes' => $staging->size_bytes,
            'rows_read' => $staging->rows_read,
            'rows_valid' => $staging->rows_valid,
            'rows_invalid' => $staging->rows_invalid,
            'header_check' => $staging->header_check,
            'duplicate_of_batch_id' => $staging->duplicate_of_batch_id,
            'date_has_data' => $staging->date_has_data,
            'state' => $staging->state->value,
            'mode' => $staging->mode?->value,
            'batch_id' => $staging->batch_id,
            'uploaded_by' => $staging->uploader?->getAttribute('name'),
            'created_at' => $staging->created_at?->toIso8601String(),
            'expires_at' => $staging->expires_at->toIso8601String(),
            'can' => [
                'confirm' => $open && ! $staging->isBlocked() && ($request->user()?->can('confirm', $staging) ?? false),
                'cancel' => $open && ($request->user()?->can('cancel', $staging) ?? false),
            ],
        ];
    }
}
