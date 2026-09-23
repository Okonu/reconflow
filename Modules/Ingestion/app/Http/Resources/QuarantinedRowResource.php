<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DataProtection\Support\PersonalData;
use Modules\Ingestion\Models\QuarantinedRow;

final class QuarantinedRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        assert($row instanceof QuarantinedRow);

        return [
            'row_number' => $row->row_number,
            'record_key' => $row->record_key,
            'reasons' => $row->reasons,
            'values' => PersonalData::maskRecord($row->source->inventoryDataset(), $row->raw),
        ];
    }
}
