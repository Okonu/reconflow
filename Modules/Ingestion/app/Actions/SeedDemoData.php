<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use App\Contracts\AuditActor;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Events\DemoDataSeeded;

final class SeedDemoData
{
    public function __construct(
        private readonly SeedSyntheticData $seed,
        private readonly IngestFromSourceSystem $ingest,
    ) {}

    public function handle(int $days, int $salesPerDay, int $seed, ?string $lastDate = null, AuditActor|string|null $actor = null, bool $ingest = true): array
    {
        $last = CarbonImmutable::parse($lastDate ?? BusinessCalendar::latestClosedDate(), BusinessCalendar::timezone());
        $dates = $this->seed->handle($days, $salesPerDay, $seed, $last, $actor);

        if ($ingest) {
            foreach ($dates as $date) {
                foreach (SourceType::cases() as $source) {
                    $this->ingest->handle($source, $date, $actor);
                }
            }
            DemoDataSeeded::dispatch($dates);
        }

        return $dates;
    }
}
