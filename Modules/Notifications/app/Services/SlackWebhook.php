<?php

declare(strict_types=1);

namespace Modules\Notifications\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SlackWebhook
{
    public function enabled(): bool
    {
        return filled(config('notifications.slack.webhook_url'));
    }

    public function post(string $text): bool
    {
        if (! $this->enabled()) {
            return false;
        }
        try {
            return Http::timeout((int) config('notifications.slack.timeout'))
                ->post((string) config('notifications.slack.webhook_url'), ['text' => $text])
                ->successful();
        } catch (Throwable $e) {
            Log::warning('slack notification failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
