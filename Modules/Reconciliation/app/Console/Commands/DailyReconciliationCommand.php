<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Console\Commands;

use Illuminate\Console\Command;
use Modules\Reconciliation\Jobs\DailyReconciliationJob;

final class DailyReconciliationCommand extends Command
{
    protected $signature = 'recon:daily';

    protected $description = 'Queue the scheduled reconciliation of the latest closed business date';

    public function handle(): int
    {
        DailyReconciliationJob::dispatch();
        $this->info('Queued the daily reconciliation.');

        return self::SUCCESS;
    }
}
