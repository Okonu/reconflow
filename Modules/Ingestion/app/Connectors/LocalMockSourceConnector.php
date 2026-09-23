<?php

declare(strict_types=1);

namespace Modules\Ingestion\Connectors;

use Carbon\CarbonImmutable;
use Modules\Ingestion\DTOs\SourceExtract;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\MockSourceStore;

final class LocalMockSourceConnector implements SourceConnector
{
    public function __construct(private readonly MockSourceStore $store) {}

    public function fetch(SourceType $source, string $businessDate): SourceExtract
    {
        return new SourceExtract($source, $businessDate, $this->store->extract($source, $businessDate), CarbonImmutable::now(), 'simulated-'.$source->value);
    }
}
