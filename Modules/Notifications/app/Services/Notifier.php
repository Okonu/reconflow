<?php

declare(strict_types=1);

namespace Modules\Notifications\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Modules\Notifications\Enums\NotificationPermission;
use Modules\Notifications\Notifications\Alert;
use Throwable;

final class Notifier
{
    public function __construct(
        private readonly RecipientResolver $recipients,
        private readonly SlackWebhook $slack,
    ) {}

    public function send(NotificationPermission $audience, Alert $alert): int
    {
        $users = $this->recipients->for($audience);
        try {
            Notification::send($users, $alert);
        } catch (Throwable $e) {
            Log::warning('notification delivery failed', ['kind' => $alert->kind, 'error' => $e->getMessage()]);
        }
        $this->slack->post($alert->slackText());

        return $users->count();
    }
}
