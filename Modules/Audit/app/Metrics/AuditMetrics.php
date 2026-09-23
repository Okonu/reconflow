<?php

declare(strict_types=1);

namespace Modules\Audit\Metrics;

use App\Contracts\MetricsCollector;
use Illuminate\Support\Facades\DB;
use Prometheus\CollectorRegistry;

final class AuditMetrics implements MetricsCollector
{
    public function collect(CollectorRegistry $registry): void
    {
        $registry->getOrRegisterGauge('reconflow', 'audit_events', 'Audit events currently in the live chain')
            ->set((float) DB::table('audit_events')->count());
    }
}
