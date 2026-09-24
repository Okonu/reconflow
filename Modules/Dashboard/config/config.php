<?php

declare(strict_types=1);

return [
    'name' => 'Dashboard',
    'manual_seconds_per_item' => (float) env('DASHBOARD_MANUAL_SECONDS_PER_ITEM', 3.4),
    'trend_days' => 14,
];
