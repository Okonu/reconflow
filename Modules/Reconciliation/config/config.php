<?php

declare(strict_types=1);

return [
    'defaults' => [
        'tolerance' => env('RECON_TOLERANCE', '0.50'),
        'fuzzy_window_hours' => (int) env('RECON_FUZZY_WINDOW_HOURS', 24),
        'timing_cutoff' => env('RECON_TIMING_CUTOFF', '22:00'),
        'duplicate_window_minutes' => (int) env('RECON_DUPLICATE_WINDOW_MINUTES', 5),
        'timing_carry_days' => (int) env('RECON_TIMING_CARRY_DAYS', 1),
        'late_payment_lookback_days' => (int) env('RECON_LATE_PAYMENT_LOOKBACK_DAYS', 7),
        'schedule_time' => env('RECON_SCHEDULE_TIME', '06:00'),
        'blocked_retry_minutes' => (int) env('RECON_BLOCKED_RETRY_MINUTES', 30),
        'blocked_max_attempts' => (int) env('RECON_BLOCKED_MAX_ATTEMPTS', 12),
        'revenue_account_prefix' => env('RECON_REVENUE_ACCOUNT_PREFIX', '4000'),
        'adjustment_journal_prefix' => env('RECON_ADJUSTMENT_JOURNAL_PREFIX', 'ADJ-'),
    ],
];
