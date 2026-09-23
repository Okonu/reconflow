<?php

declare(strict_types=1);

namespace App\Contracts;

use Prometheus\CollectorRegistry;

interface MetricsCollector
{
    public function collect(CollectorRegistry $registry): void;
}
