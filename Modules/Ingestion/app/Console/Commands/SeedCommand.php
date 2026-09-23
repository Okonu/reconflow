<?php

declare(strict_types=1);

namespace Modules\Ingestion\Console\Commands;

use App\Support\BusinessCalendar;
use Illuminate\Console\Command;
use Modules\Ingestion\Actions\ExportSeededDay;
use Modules\Ingestion\Actions\SeedDemoData;

final class SeedCommand extends Command
{
    protected $signature = 'reconflow:seed
        {--days=14 : Number of business days to generate}
        {--sales-per-day=3000 : Sales per day}
        {--seed=42 : Random seed (same seed, same data)}
        {--end= : Last business date (defaults to the latest closed business date)}
        {--no-ingest : Only write the simulated source systems; do not ingest}
        {--export= : Export a seeded business date to template-format xlsx instead of seeding}
        {--out=./export : Directory for --export}';

    protected $description = 'Generate synthetic source data for the simulated source systems, or export a seeded day';

    public function handle(SeedDemoData $seed, ExportSeededDay $export): int
    {
        $exportDate = $this->option('export');
        if (is_string($exportDate) && $exportDate !== '') {
            foreach ($export->handle($exportDate, (string) $this->option('out')) as $file) {
                $this->line("Wrote {$file}");
            }

            return self::SUCCESS;
        }

        $end = $this->option('end');
        $dates = $seed->handle(
            (int) $this->option('days'),
            (int) $this->option('sales-per-day'),
            (int) $this->option('seed'),
            is_string($end) && $end !== '' ? $end : BusinessCalendar::latestClosedDate(),
            'console',
            ! $this->option('no-ingest'),
        );
        $this->info('Seeded '.count($dates).' business days: '.reset($dates).' to '.end($dates));

        return self::SUCCESS;
    }
}
