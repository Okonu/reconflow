<?php

declare(strict_types=1);

namespace Modules\Ingestion\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\DataProtection\Support\PersonalData;
use Modules\Ingestion\Support\Schema\RowResult;

final class PreviewRowResource extends JsonResource
{
    public function __construct(RowResult $row, private readonly string $dataset)
    {
        parent::__construct($row);
    }

    public function toArray(Request $request): array
    {
        $row = $this->resource;
        assert($row instanceof RowResult);

        return [
            'row' => $row->rowNumber,
            'key' => $row->recordKey,
            'status' => $row->isValid() ? 'valid' : 'invalid',
            'errors' => $row->errors,
            'values' => PersonalData::maskRecord($this->dataset, $row->raw),
        ];
    }
}
