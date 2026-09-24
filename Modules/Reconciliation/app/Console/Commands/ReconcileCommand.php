<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Console\Commands;

use App\Support\BusinessCalendar;
use Illuminate\Console\Command;
use Modules\Reconciliation\Actions\ReconcileDate;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Enums\RunTrigger;

final class ReconcileCommand extends Command
{
    protected $signature = 'recon:run {date? : Business date (defaults to the latest closed business date)} {--refresh : Pull from source systems first}';

    protected $description = 'Reconcile a business date now';

    public function handle(ReconcileDate $reconcile): int
    {
        $date = (string) ($this->argument('date') ?? BusinessCalendar::latestClosedDate());
        $run = $reconcile->handle(new RunRequest($date, RunTrigger::Manual, (bool) $this->option('refresh')), 'console');
        $this->info("Run {$run->id} for {$date}: {$run->status->value} in {$run->duration_ms} ms, match rate ".($run->summary['match_rate'] ?? 'n/a').'%');

        return self::SUCCESS;
    }
}
