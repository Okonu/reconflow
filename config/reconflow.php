<?php

declare(strict_types=1);

return [
    'display_timezone' => env('RECONFLOW_DISPLAY_TIMEZONE', 'Africa/Nairobi'),
    'payments_window_grace_hours' => (int) env('PAYMENTS_WINDOW_GRACE_HOURS', 6),
    'trusted_proxies' => env('TRUSTED_PROXIES', '127.0.0.1,172.16.0.0/12,10.0.0.0/8,192.168.0.0/16'),
];
