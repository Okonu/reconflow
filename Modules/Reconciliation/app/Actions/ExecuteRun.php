<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Actions;

use App\Contracts\AuditActor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Audit\Services\AuditLogger;
use Modules\Ingestion\Actions\IngestFromSourceSystem;
use Modules\Ingestion\Enums\SourceType;
use Modules\Ingestion\Services\ActiveDataset;
use Modules\Reconciliation\DTOs\RuleConfig;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Engine\ReconciliationEngine;
use Modules\Reconciliation\Enums\ReconAuditAction;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Events\RunFinished;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\ItemStateLedger;
use Modules\Reconciliation\Services\ResultWriter;
use Modules\Reconciliation\Services\RunInputLoader;
use Modules\Reconciliation\Services\RunSummary;
use Throwable;

final class ExecuteRun
{
    public function __construct(
        private readonly IngestFromSourceSystem $ingest,
        private readonly ActiveDataset $dataset,
        private readonly RunInputLoader $loader,
        private readonly ReconciliationEngine $engine,
        private readonly ResultWriter $writer,
        private readonly RunSummary $summary,
        private readonly ItemStateLedger $ledger,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(ReconRun $run, RunRequest $request, AuditActor|string|null $actor = null): ReconRun
    {
        $started = hrtime(true);
        $run->update(['status' => RunStatus::Running, 'started_at' => now()]);
        $date = $run->business_date->toDateString();

        try {
            if ($request->refreshFromSources) {
                foreach (SourceType::cases() as $source) {
                    $this->ingest->handle($source, $date, $actor, in_array($source->value, $request->replaceManualSources, true));
                }
            }

            $batches = $this->batches($date);
            $missing = array_keys(array_filter($batches, fn ($b) => $b === null || $b['rows'] === 0));
            if ($missing !== [] && ! $run->provisional) {
                return $this->blocked($run, $batches, $missing, $started, $actor);
            }

            $config = RuleConfig::fromArray($run->rule_config, $run->rule_config_id);
            $batchIds = array_map(fn ($b) => $b['id'] ?? null, $batches);

            DB::transaction(function () use ($run, $date, $config, $batchIds, $batches, $started): void {
                DB::select('select pg_advisory_xact_lock(?)', [crc32('recon-run:'.$date)]);
                $previousRuns = ReconRun::query()->forDate($date)->whereKeyNot($run->id)->pluck('id')->all();
                $this->ledger->releaseDecisionsBy($previousRuns, $run);

                $output = $this->engine->reconcile($this->loader->load($date, $config, $batchIds));
                $priorResults = $this->writer->write($run, $output->items);
                $this->ledger->record($run, $priorResults, $output->escalations);

                ReconRun::query()->forDate($date)->latestCompleted()->whereKeyNot($run->id)->update(['superseded_by_id' => $run->id]);
                $run->update([
                    'status' => RunStatus::Completed,
                    'batches' => $batches,
                    'summary' => $this->summary->build($output->items, $output->escalations),
                    'finished_at' => now(),
                    'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
                ]);
                $this->markNextDayStale($run);
            });

            $this->audit->record(ReconAuditAction::RunCompleted, $actor, 'recon_run', $run->id, [
                'business_date' => $date,
                'version' => $run->version,
                'provisional' => $run->provisional,
                'duration_ms' => $run->duration_ms,
                'batches' => $batches,
                'match_rate' => $run->summary['match_rate'] ?? null,
                'items' => $run->summary['items'] ?? null,
            ]);
        } catch (Throwable $e) {
            $run->update(['status' => RunStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now(), 'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000)]);
            Log::error('reconciliation run failed', ['run_id' => $run->id, 'business_date' => $date, 'error' => $e->getMessage()]);
            $this->audit->record(ReconAuditAction::RunFailed, $actor, 'recon_run', $run->id, ['business_date' => $date, 'error' => mb_substr($e->getMessage(), 0, 500)]);
            RunFinished::dispatch($run->fresh());

            throw $e;
        }

        RunFinished::dispatch($run->fresh());

        return $run->fresh();
    }

    private function batches(string $date): array
    {
        $batches = [];
        foreach (SourceType::cases() as $source) {
            $batch = $this->dataset->activeBatch($source, $date);
            $batches[$source->value] = $batch === null ? null : [
                'id' => $batch->id,
                'version' => $batch->version,
                'mode' => $batch->mode->value,
                'manual' => $batch->manual,
                'rows' => $batch->rows_loaded,
                'quarantined' => $batch->rows_quarantined,
            ];
        }

        return $batches;
    }

    private function blocked(ReconRun $run, array $batches, array $missing, int $started, AuditActor|string|null $actor): ReconRun
    {
        $reason = 'No data for: '.implode(', ', $missing);
        $run->update([
            'status' => RunStatus::BlockedData,
            'batches' => $batches,
            'blocked_reason' => $reason,
            'finished_at' => now(),
            'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000),
        ]);
        $this->audit->record(ReconAuditAction::RunBlocked, $actor, 'recon_run', $run->id, [
            'business_date' => $run->business_date->toDateString(),
            'missing_sources' => $missing,
        ]);
        RunFinished::dispatch($run->fresh());

        return $run->fresh();
    }

    private function markNextDayStale(ReconRun $run): void
    {
        $next = ReconRun::query()->forDate($run->business_date->addDay()->toDateString())->latestCompleted()->whereNull('stale_at')->first();
        if ($next === null) {
            return;
        }
        $next->update(['stale_at' => now()]);
        $this->audit->record(ReconAuditAction::RunMarkedStale, AuditLogger::SYSTEM_ACTOR, 'recon_run', $next->id, [
            'business_date' => $next->business_date->toDateString(),
            'because_of_run_id' => $run->id,
            'reason' => 'The previous business date was re-run; re-run this date to pick up the change.',
        ]);
    }
}
