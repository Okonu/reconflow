<?php

declare(strict_types=1);

namespace Modules\Notifications\Listeners;

use Modules\Notifications\Enums\AlertLevel;
use Modules\Notifications\Enums\NotificationPermission;
use Modules\Notifications\Notifications\Alert;
use Modules\Notifications\Services\Notifier;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Events\RunFinished;

final class NotifyRunProblems
{
    public function __construct(private readonly Notifier $notifier) {}

    public function handle(RunFinished $event): void
    {
        $run = $event->run;
        $date = $run->business_date->toDateString();
        $alert = match ($run->status) {
            RunStatus::Failed => new Alert('run_failed', "Reconciliation failed for {$date}", "Run v{$run->version} failed. Open the run for details and re-run once the cause is fixed.", route('runs.show', $run), AlertLevel::Critical),
            RunStatus::BlockedData => new Alert('run_blocked', "Reconciliation blocked for {$date}", (string) ($run->blocked_reason ?? 'Source data is missing.').'. The run will retry automatically; you can also upload the missing file.', route('runs.show', $run), AlertLevel::Warning),
            default => null,
        };
        if ($alert !== null) {
            $this->notifier->send(NotificationPermission::RunAlerts, $alert);
        }
    }
}
