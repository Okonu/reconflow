<?php

declare(strict_types=1);

namespace Modules\Notifications\Console\Commands;

use Illuminate\Console\Command;
use Modules\Notifications\Enums\NotificationPermission;
use Modules\Notifications\Services\DailySummaryBuilder;
use Modules\Notifications\Services\Notifier;

final class SendDailySummaryCommand extends Command
{
    protected $signature = 'reconflow:daily-summary {--date= : Business date, defaults to the latest closed date}';

    protected $description = 'Send the daily reconciliation summary to subscribed users and Slack';

    public function handle(DailySummaryBuilder $builder, Notifier $notifier): int
    {
        $alert = $builder->build($this->option('date') ?: null);
        $sent = $notifier->send(NotificationPermission::DailySummary, $alert);
        $this->info("{$alert->title}: sent to {$sent} users");

        return self::SUCCESS;
    }
}
