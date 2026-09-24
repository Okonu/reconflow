<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Listeners;

use Modules\Ingestion\Events\DemoDataSeeded;
use Modules\Reconciliation\Actions\ReconcileDate;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Enums\RunTrigger;

final class ReconcileSeededDates
{
    public function __construct(private readonly ReconcileDate $reconcile) {}

    public function handle(DemoDataSeeded $event): void
    {
        $dates = $event->dates;
        sort($dates);
        foreach ($dates as $date) {
            $this->reconcile->handle(new RunRequest($date, RunTrigger::Demo), 'system:demo-seed');
        }
    }
}
