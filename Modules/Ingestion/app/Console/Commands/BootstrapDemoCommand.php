<?php

declare(strict_types=1);

namespace Modules\Ingestion\Console\Commands;

use Illuminate\Console\Command;
use Modules\Ingestion\Jobs\SeedDemoDataJob;
use Modules\Ingestion\Models\MockSourceRow;
use Modules\Ingestion\Models\SourceBatch;

final class BootstrapDemoCommand extends Command
{
    protected $signature = 'reconflow:bootstrap-demo';

    protected $description = 'Queue synthetic demo data on first boot when the database is empty';

    public function handle(): int
    {
        if (! config('ingestion.demo.auto_seed')) {
            $this->line('Demo auto-seed disabled.');

            return self::SUCCESS;
        }
        if (SourceBatch::query()->exists() || MockSourceRow::query()->exists()) {
            $this->line('Data already present; nothing to seed.');

            return self::SUCCESS;
        }
        SeedDemoDataJob::dispatch((int) config('ingestion.demo.days'), (int) config('ingestion.demo.sales_per_day'), (int) config('ingestion.demo.seed'), 'system:bootstrap');
        $this->info('Queued first-boot demo data.');

        return self::SUCCESS;
    }
}
