<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Services;

use App\Support\BusinessCalendar;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\ActiveDataset;

final class SourceReadiness
{
    public function __construct(private readonly ActiveDataset $dataset) {}

    public function forDate(string $date): array
    {
        $sources = [];
        foreach (SourceType::cases() as $source) {
            $batch = $this->dataset->activeBatch($source, $date);
            $sources[] = [
                'source' => $source->value,
                'label' => $source->label(),
                'batch_id' => $batch?->id,
                'version' => $batch?->version,
                'manual' => $batch !== null && $batch->manual,
                'mode_label' => $batch?->mode->label(),
                'rows' => $batch === null ? 0 : $batch->rows_loaded,
            ];
        }

        return ['date' => $date, 'closed' => BusinessCalendar::isClosed($date), 'sources' => $sources];
    }
}
