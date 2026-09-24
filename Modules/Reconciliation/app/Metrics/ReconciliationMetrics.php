<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Metrics;

use App\Contracts\MetricsCollector;
use Modules\Reconciliation\Models\ReconRun;
use Prometheus\CollectorRegistry;

final class ReconciliationMetrics implements MetricsCollector
{
    public function collect(CollectorRegistry $registry): void
    {
        $run = ReconRun::query()->latestCompleted()->orderByDesc('business_date')->first();
        if ($run === null) {
            return;
        }
        $labels = [$run->business_date->toDateString()];
        $registry->getOrRegisterGauge('reconflow', 'run_duration_seconds', 'Duration of the latest completed run', ['business_date'])
            ->set(($run->duration_ms ?? 0) / 1000, $labels);
        $registry->getOrRegisterGauge('reconflow', 'match_rate_percent', 'Match rate of the latest completed run', ['business_date'])
            ->set((float) ($run->summary['match_rate'] ?? 0), $labels);
        $exceptions = $registry->getOrRegisterGauge('reconflow', 'result_items', 'Result items of the latest completed run by status', ['business_date', 'status']);
        foreach ((array) ($run->summary['by_status'] ?? []) as $status => $count) {
            $exceptions->set((float) $count, [...$labels, (string) $status]);
        }
    }
}
