<?php

declare(strict_types=1);

namespace Modules\Ingestion\DTOs;

use Carbon\CarbonImmutable;
use Modules\Ingestion\Enums\BatchOrigin;
use Modules\Ingestion\Enums\ImportMode;
use Modules\Ingestion\Enums\SourceType;

final readonly class BatchMeta
{
    public function __construct(
        public SourceType $source,
        public string $businessDate,
        public BatchOrigin $origin,
        public ImportMode $mode,
        public string $checksum,
        public ?string $filename = null,
        public ?CarbonImmutable $extractedAt = null,
        public ?int $createdBy = null,
    ) {}
}
