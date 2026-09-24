<?php

declare(strict_types=1);

use Modules\Ingestion\Actions\SeedDemoData;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Models\ReconRun;

it('reconciles 50,000 sales in under 60 seconds', function (): void {
    app(SeedDemoData::class)->handle(1, 50000, 50, '2026-09-22', ingest: true);
    $run = ReconRun::query()->latestCompleted()->firstOrFail();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->summary['sale_items'])->toBe(50000)
        ->and($run->duration_ms)->toBeLessThan(60000);
    fwrite(STDERR, "50k run: {$run->duration_ms} ms, match rate {$run->summary['match_rate']}%\n");
})->group('slow');
