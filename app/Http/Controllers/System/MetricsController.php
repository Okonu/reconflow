<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Contracts\MetricsCollector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Response;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

final class MetricsController
{
    public const COLLECTOR_TAG = 'reconflow.metrics';

    public function __invoke(Application $app): Response
    {
        $registry = new CollectorRegistry(new InMemory, false);

        foreach ($app->tagged(self::COLLECTOR_TAG) as $collector) {
            if ($collector instanceof MetricsCollector) {
                $collector->collect($registry);
            }
        }

        return response((new RenderTextFormat)->render($registry->getMetricFamilySamples()), 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }
}
