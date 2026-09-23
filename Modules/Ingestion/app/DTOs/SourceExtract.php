<?php

declare(strict_types=1);

namespace Modules\Ingestion\DTOs;

use Carbon\CarbonImmutable;
use Modules\Ingestion\Enums\SourceType;

final readonly class SourceExtract
{
    public function __construct(
        public SourceType $source,
        public string $businessDate,
        public array $rows,
        public CarbonImmutable $extractedAt,
        public string $system,
    ) {}

    public function checksum(): string
    {
        return hash('sha256', json_encode([$this->source->value, $this->businessDate, $this->rows], JSON_THROW_ON_ERROR));
    }

    public function numberedRows(): array
    {
        $numbered = [];
        foreach (array_values($this->rows) as $index => $row) {
            $numbered[$index + 1] = $row;
        }

        return $numbered;
    }
}
