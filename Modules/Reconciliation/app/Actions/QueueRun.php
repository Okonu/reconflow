<?php

declare(strict_types=1);

namespace Modules\Reconciliation\Actions;

use App\Contracts\AuditActor;
use App\Contracts\BusinessDateLock;
use App\Exceptions\DomainException;
use App\Support\BusinessCalendar;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogger;
use Modules\Reconciliation\DTOs\RunRequest;
use Modules\Reconciliation\Enums\ReconAuditAction;
use Modules\Reconciliation\Enums\RunStatus;
use Modules\Reconciliation\Models\ReconRun;
use Modules\Reconciliation\Services\RuleConfigService;

final class QueueRun
{
    public function __construct(
        private readonly RuleConfigService $configs,
        private readonly AuditLogger $audit,
        private readonly BusinessDateLock $locks,
    ) {}

    public function handle(RunRequest $request, AuditActor|string|null $actor): ReconRun
    {
        if ($this->locks->isLocked($request->businessDate)) {
            throw DomainException::conflict("{$request->businessDate} is signed off. A Finance Manager must reopen it before it can be re-run.");
        }

        return DB::transaction(function () use ($request, $actor): ReconRun {
            DB::select('select pg_advisory_xact_lock(?)', [crc32('recon-run:'.$request->businessDate)]);
            $config = $this->configs->current();
            $run = ReconRun::query()->create([
                'business_date' => $request->businessDate,
                'version' => (int) ReconRun::query()->forDate($request->businessDate)->max('version') + 1,
                'status' => RunStatus::Queued,
                'trigger' => $request->trigger,
                'triggered_by' => $actor instanceof AuditActor ? $actor->auditActorId() : null,
                'provisional' => ! BusinessCalendar::isClosed($request->businessDate),
                'rule_config_id' => $config->versionId,
                'rule_config' => $config->toArray(),
            ]);
            $this->audit->record(ReconAuditAction::RunQueued, $actor, 'recon_run', $run->id, [
                'business_date' => $request->businessDate,
                'version' => $run->version,
                'trigger' => $request->trigger->value,
                'provisional' => $run->provisional,
                'refresh_from_sources' => $request->refreshFromSources,
                'replace_manual_sources' => $request->replaceManualSources,
            ]);

            return $run;
        });
    }
}
