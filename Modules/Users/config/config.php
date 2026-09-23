<?php

declare(strict_types=1);

return [
    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 300),
    ],
    'demo' => [
        'seed' => (bool) env('SEED_DEMO_USERS', true),
        'password' => env('DEMO_PASSWORD'),
    ],
];
