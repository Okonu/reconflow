<?php

declare(strict_types=1);

return [
    'severity' => [
        'medium_from' => env('EXCEPTION_MEDIUM_FROM', '50.00'),
        'high_from' => env('EXCEPTION_HIGH_FROM', '500.00'),
        'critical_from' => env('EXCEPTION_CRITICAL_FROM', '2000.00'),
    ],
    'sla_hours' => [
        'low' => (int) env('EXCEPTION_SLA_LOW_HOURS', 120),
        'medium' => (int) env('EXCEPTION_SLA_MEDIUM_HOURS', 72),
        'high' => (int) env('EXCEPTION_SLA_HIGH_HOURS', 24),
        'critical' => (int) env('EXCEPTION_SLA_CRITICAL_HOURS', 8),
    ],
    'minimum_medium_statuses' => ['DUPLICATE_PAYMENT', 'POSTING_MISMATCH', 'DUPLICATE_POSTING'],
];
