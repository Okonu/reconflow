<?php

declare(strict_types=1);

namespace Modules\ExceptionManagement\Metrics;

use App\Contracts\MetricsCollector;
use Modules\ExceptionManagement\Services\QueueSummary;
use Prometheus\CollectorRegistry;

final class ExceptionMetrics implements MetricsCollector
{
    public function __construct(private readonly QueueSummary $summary) {}

    public function collect(CollectorRegistry $registry): void
    {
        $counts = $this->summary->counts();
        $registry->getOrRegisterGauge('reconflow', 'open_exceptions', 'Open exceptions', [])->set((float) $counts['open']);
        $registry->getOrRegisterGauge('reconflow', 'overdue_exceptions', 'Overdue exceptions', [])->set((float) $counts['overdue']);
    }
}
