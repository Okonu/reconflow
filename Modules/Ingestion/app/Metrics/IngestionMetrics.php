<?php

declare(strict_types=1);

namespace Modules\Ingestion\Metrics;

use App\Contracts\MetricsCollector;
use Illuminate\Support\Facades\DB;
use Prometheus\CollectorRegistry;

final class IngestionMetrics implements MetricsCollector
{
    public function collect(CollectorRegistry $registry): void
    {
        $gauge = $registry->getOrRegisterGauge('reconflow', 'quarantined_rows', 'Quarantined source rows in active batches', ['source']);
        $rows = DB::table('source_batches')->where('status', 'active')->groupBy('source')->selectRaw('source, sum(rows_quarantined) as total')->get();
        foreach ($rows as $row) {
            $gauge->set((float) $row->total, [(string) $row->source]);
        }
    }
}
