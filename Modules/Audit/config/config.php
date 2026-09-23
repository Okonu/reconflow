<?php

declare(strict_types=1);

return [
    'retention_years' => (int) env('RETENTION_AUDIT_YEARS', 7),
    'archive_path' => env('AUDIT_ARCHIVE_PATH', storage_path('app/audit-archive')),
    'archive_schedule' => env('AUDIT_ARCHIVE_CRON', '30 2 * * *'),
];
