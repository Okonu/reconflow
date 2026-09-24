<?php

declare(strict_types=1);

return [
    'name' => 'Notifications',
    'mail' => [
        'enabled' => (bool) env('NOTIFY_MAIL_ENABLED', false),
    ],
    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
        'timeout' => 5,
    ],
    'daily_summary_time' => env('NOTIFY_DAILY_SUMMARY_TIME', '07:00'),
    'inbox_size' => 20,
];
