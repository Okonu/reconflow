<?php

declare(strict_types=1);

return [
    'samples_path' => env('SAMPLES_PATH', base_path('samples')),
    'upload' => [
        'max_kilobytes' => (int) env('UPLOAD_MAX_KILOBYTES', 10240),
        'max_rows' => (int) env('UPLOAD_MAX_ROWS', 50000),
        'staging_ttl_hours' => (int) env('UPLOAD_STAGING_TTL_HOURS', 24),
        'preview_page_size' => 50,
    ],
    'sources' => [
        'driver' => env('SOURCE_SYSTEMS_DRIVER', 'local'),
        'base_url' => env('SOURCE_SYSTEMS_URL', env('APP_URL', 'http://127.0.0.1:8000')),
        'token' => env('SOURCE_SYSTEMS_TOKEN'),
        'timeout_seconds' => (int) env('SOURCE_SYSTEMS_TIMEOUT', 30),
    ],
    'generator' => [
        'rates' => [
            'amount_mismatch' => (float) env('SEED_RATE_AMOUNT_MISMATCH', 0.02),
            'reference_problem' => (float) env('SEED_RATE_REFERENCE_PROBLEM', 0.015),
            'split_payment' => (float) env('SEED_RATE_SPLIT_PAYMENT', 0.01),
            'duplicate_payment' => (float) env('SEED_RATE_DUPLICATE_PAYMENT', 0.005),
            'missing_payment' => (float) env('SEED_RATE_MISSING_PAYMENT', 0.01),
            'unknown_payment' => (float) env('SEED_RATE_UNKNOWN_PAYMENT', 0.007),
            'missing_posting' => (float) env('SEED_RATE_MISSING_POSTING', 0.01),
            'posting_mismatch' => (float) env('SEED_RATE_POSTING_MISMATCH', 0.003),
            'malformed_row' => (float) env('SEED_RATE_MALFORMED_ROW', 0.005),
            'rounding_difference' => (float) env('SEED_RATE_ROUNDING_DIFFERENCE', 0.015),
        ],
    ],
    'demo' => [
        'auto_seed' => (bool) env('DEMO_AUTO_SEED', true),
        'days' => (int) env('DEMO_DAYS', 14),
        'sales_per_day' => (int) env('DEMO_SALES_PER_DAY', 3000),
        'seed' => (int) env('DEMO_SEED', 42),
    ],
    'staging_purge_schedule' => env('STAGING_PURGE_CRON', '15 * * * *'),
];
