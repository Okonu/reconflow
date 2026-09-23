<?php

declare(strict_types=1);

namespace Modules\Ingestion\Services;

use Modules\Ingestion\Models\UploadStaging;
use Modules\Ingestion\Support\Schema\RowResult;

final class StagedUploadPreview
{
    public function __construct(private readonly ActiveDataset $dataset) {}

    public function page(UploadStaging $staging, bool $invalidOnly, int $page, int $perPage): array
    {
        $rows = array_map(RowResult::fromArray(...), (array) $staging->rows);
        if ($invalidOnly) {
            $rows = array_values(array_filter($rows, fn (RowResult $r): bool => ! $r->isValid()));
        }
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        return [
            'rows' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'total' => $total,
            'page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'append_conflicts' => $this->appendConflicts($staging),
        ];
    }

    private function appendConflicts(UploadStaging $staging): int
    {
        $column = $staging->source->schema()->uniqueKeyColumn();
        if ($column === null || $staging->rows === null) {
            return 0;
        }
        $existing = $this->dataset->existingKeys($staging->source, $staging->business_date->toDateString());
        if ($existing === []) {
            return 0;
        }
        $conflicts = 0;
        foreach ((array) $staging->rows as $row) {
            if (($row['errors'] ?? []) === [] && isset($existing[$row['raw'][$column] ?? ''])) {
                $conflicts++;
            }
        }

        return $conflicts;
    }
}
