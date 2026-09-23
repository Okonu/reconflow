<?php

declare(strict_types=1);

namespace Modules\Ingestion\DTOs;

use Modules\Ingestion\Support\Schema\RowResult;

final readonly class ValidationOutcome
{
    public function __construct(public array $rows) {}

    public function valid(): array
    {
        return array_values(array_filter($this->rows, fn (RowResult $r): bool => $r->isValid()));
    }

    public function invalid(): array
    {
        return array_values(array_filter($this->rows, fn (RowResult $r): bool => ! $r->isValid()));
    }

    public function reasonCounts(): array
    {
        $counts = [];
        foreach ($this->invalid() as $row) {
            foreach ($row->errors as $reason) {
                $counts[$reason] = ($counts[$reason] ?? 0) + 1;
            }
        }
        arsort($counts);

        return $counts;
    }

    public static function fromArray(array $rows): self
    {
        return new self(array_map(RowResult::fromArray(...), $rows));
    }

    public function toArray(): array
    {
        return array_map(fn (RowResult $r): array => $r->toArray(), $this->rows);
    }
}
