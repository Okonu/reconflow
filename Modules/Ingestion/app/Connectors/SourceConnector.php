<?php

declare(strict_types=1);

namespace Modules\Ingestion\Connectors;

use Modules\Ingestion\DTOs\SourceExtract;
use Modules\Ingestion\Enums\SourceType;

interface SourceConnector
{
    public function fetch(SourceType $source, string $businessDate): SourceExtract;
}
