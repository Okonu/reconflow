<?php

declare(strict_types=1);

namespace Modules\Ingestion\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Ingestion\Actions\SeedDemoData;

final class SeedDemoDataJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public readonly int $days,
        public readonly int $salesPerDay,
        public readonly int $seed,
        public readonly ?string $actorLabel = null,
    ) {}

    public function handle(SeedDemoData $seedDemoData): void
    {
        $seedDemoData->handle($this->days, $this->salesPerDay, $this->seed, actor: $this->actorLabel);
    }
}
