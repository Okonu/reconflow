<?php

declare(strict_types=1);

return [
    'name' => 'AI',
    'enabled' => (bool) env('AI_ENABLED', true),
    'driver' => env('AI_DRIVER', 'claude'),
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
    'effort' => env('AI_EFFORT', 'medium'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 16000),
    'timeout' => (float) env('AI_TIMEOUT', 60),
    'max_retries' => 2,
    'refusal_fallbacks' => (bool) env('AI_REFUSAL_FALLBACKS', true),
    'prompt_version' => env('AI_PROMPT_VERSION', 'v1'),
    'log_retention_months' => (int) env('RETENTION_AI_LOGS_MONTHS', 12),
];
