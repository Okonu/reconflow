<?php

declare(strict_types=1);

return [
    'pii_hash_salt' => env('PII_HASH_SALT'),
    'retention' => [
        'transactions_years' => (int) env('RETENTION_TRANSACTIONS_YEARS', 7),
        'ai_logs_months' => (int) env('RETENTION_AI_LOGS_MONTHS', 12),
    ],
];
