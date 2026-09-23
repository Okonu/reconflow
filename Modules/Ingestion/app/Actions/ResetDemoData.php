<?php

declare(strict_types=1);

namespace Modules\Ingestion\Actions;

use App\Contracts\ResetsDemoData;
use Illuminate\Contracts\Foundation\Application;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Enums\IngestionAuditAction;
use Modules\Ingestion\Jobs\SeedDemoDataJob;
use Modules\Users\Models\User;

final class ResetDemoData
{
    public const RESETTER_TAG = 'reconflow.demo-reset';

    public function __construct(
        private readonly Application $app,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor): array
    {
        $resetters = iterator_to_array($this->app->tagged(self::RESETTER_TAG));
        usort($resetters, fn (ResetsDemoData $a, ResetsDemoData $b): int => $a->resetOrder() <=> $b->resetOrder());

        $tables = [];
        foreach ($resetters as $resetter) {
            array_push($tables, ...$resetter->truncateOperationalData());
        }

        $days = (int) config('ingestion.demo.days');
        $perDay = (int) config('ingestion.demo.sales_per_day');
        $seed = (int) config('ingestion.demo.seed');
        $this->audit->record(IngestionAuditAction::DemoReset, $actor, 'demo', payload: [
            'truncated_tables' => $tables,
            'reseed' => ['days' => $days, 'sales_per_day' => $perDay, 'seed' => $seed],
        ]);
        SeedDemoDataJob::dispatch($days, $perDay, $seed, $actor->auditActorLabel());

        return $tables;
    }
}
